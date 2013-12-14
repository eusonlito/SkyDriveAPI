<?php
class SkyDrive
{
    const authUrl = 'https://login.live.com/oauth20_authorize.srf';
    const codeUrl = 'https://login.live.com/oauth20_token.srf';
    const baseUrl = 'https://apis.live.net/v5.0/';

    const settings_contents_limit = 1000;
    const settings_contents_sort_by = 'name';
    const settings_contents_sort_order = 'ascending';

    const settings_cookie_name = 'skydrive-php-api';
    const settings_cookie_expire = 2592000; // 3600 * 24 * 30
    const settings_cookie_path = '/';

    const settings_scope = 'wl.basic wl.skydrive wl.skydrive_update wl.offline_access';

    private $token = '';
    private $settings = array();
    private $api = array();
    private $contents = array();

    public function __construct ($settings)
    {
        if (function_exists('curl_init') === false) {
            throw new Exception('cURL PHP extension is required');
        }

        $settings = $this->setSettings($settings);
        $cookie = $this->cookie();

        if (isset($cookie['access_token'])) {
            $this->token = $cookie['access_token'];
        }

        if (empty($this->token) && isset($_GET['code'])) {
            $this->getAccessToken($_GET['code']);
        } else if ($this->token && $this->isTokenExpired()) {
            $this->refreshAccessToken();
        }

        if (empty($this->token)) {
            $this->authenticate();
        }
    }

    public function setSettings ($settings)
    {
        if (empty($settings['client_id'])) {
            throw new Exception('"client_id" is a required parameter to settings');
        }

        if (empty($settings['client_secret'])) {
            throw new Exception('"client_secret" is a required parameter to settings');
        }

        if (empty($settings['contents_limit'])) {
            $settings['contents_limit'] = self::settings_contents_limit;
        }

        if (empty($settings['contents_sort_by'])) {
            $settings['contents_sort_by'] = self::settings_contents_sort_by;
        }

        if (empty($settings['contents_sort_order'])) {
            $settings['contents_sort_order'] = self::settings_contents_sort_order;
        }

        if (empty($settings['redirect_uri'])) {
            $settings['redirect_uri'] = self::uri();
        }

        if (empty($settings['scope'])) {
            $settings['scope'] = self::settings_scope;
        }

        if (empty($settings['cookie_expire'])) {
            $settings['cookie_expire'] = time() + self::settings_cookie_expire;
        } else if ($settings['cookie_expire'] <= time()) {
            $settings['cookie_expire'] += time();
        }

        if (empty($settings['cookie_name'])) {
            $settings['cookie_name'] = self::settings_cookie_name;
        }

        if (empty($settings['cookie_path'])) {
            $settings['cookie_path'] = self::settings_cookie_path;
        }

        return $this->settings = $settings;
    }

    private function authenticate ()
    {
        $this->cookie(true);

        $url = self::authUrl.'?'.http_build_query(array(
            'redirect_uri' => $this->settings['redirect_uri'],
            'client_id' => $this->settings['client_id'],
            'scope' => $this->settings['scope'],
            'response_type' => 'code'
        ));

        die(header('Location: '.$url));
    }

    public function isTokenExpired ()
    {
        return ($this->cookie('expires_in') < (time() + 30));
    }

    private function getAccessToken ($code)
    {
        $query = array(
            'client_id' => $this->settings['client_id'],
            'client_secret' => $this->settings['client_secret'],
            'redirect_uri' => $this->settings['redirect_uri'],
            'code' => $code,
            'grant_type' => 'authorization_code'
        );

        $this->cookie(true);

        try {
            $response = $this->curl(self::codeUrl.'?'.http_build_query($query));
        } catch (Exception $e) {
            throw new Exception('Sorry but your login haven\'t been authorized to use SkyDrive. Error: '.$e->getMessage());
        }

        $response['code'] = $code;
        $response['expires_in'] += time();

        $this->cookie($response);

        die(header('Location: '.self::uri()));
    }

    private function refreshAccessToken ()
    {
        $query = array(
            'client_id' => $this->settings['client_id'],
            'client_secret' => $this->settings['client_secret'],
            'redirect_uri' => $this->settings['redirect_uri'],
            'refresh_token' => $this->cookie('refresh_token'),
            'grant_type' => 'refresh_token'
        );

        $this->cookie(true);

        try {
            $response = $this->curl(self::codeUrl.'?'.http_build_query($query));
        } catch (Exception $e) {
            throw new Exception('Sorry but your login haven\'t been authorized to use SkyDrive. Error: '.$e->getMessage());
        }

        $response['expires_in'] += time();

        $this->cookie($response);

        return $this->token = $response['access_token'];
    }

    public function api ($cmd, $refresh = false)
    {
        if (isset($this->api[$cmd]) && ($refresh === false)) {
            return $this->api[$cmd];
        }

        return $this->api[$cmd] = $this->curl(self::baseUrl.$cmd);
    }

    public function me ($cmd = 'me', $refresh = false)
    {
        switch ($cmd) {
            case 'me':
                return $this->api('me');

            case 'quota':
                return $this->api('me/skydrive/quota');

            case 'permissions':
                return $this->api('me/permissions');
        }

        throw new Exception(sprintf('"%s" command is not available', $cmd));
    }

    public function folderContents ($path = '')
    {
        $path = $path ?: 'me/skydrive';

        if (isset($this->contents[$path])) {
            return $this->contents[$path];
        }

        $location = array(array(
            'id' => '',
            'name' => 'Home'
        ));

        if ($path) {
            $current = $this->curl(self::baseUrl.$path);

            if (strstr($current['parent_id'], '!')) {
                $location[] = array(
                    'id' => $current['parent_id'],
                    'name' => '...'
                );
            }

            $location[] = $current;
        }

        $this->contents[$path] = array(
            'location' => $location,
            'folders' => array(),
            'files' => array()
        );

        if (empty($path) || (isset($current) && isset($current['count']) && ($current['count'] > 0))) {
            $contents = $this->curl(self::baseUrl.$path.'/files?'.http_build_query(array(
                'sort_by' => $this->settings['contents_sort_by'],
                'sort_order' => $this->settings['contents_sort_order'],
                'limit' => $this->settings['contents_limit']
            )));
        }

        if (empty($contents['data'])) {
            return $this->contents[$path];
        }

        $folder = $file = array();

        foreach ($contents['data'] as $row) {
            if (in_array($row['type'], array('folder', 'file'), true)) {
                ${$row['type']}[] = $row;
            }
        }

        $this->contents[$path]['folders'] = $folder;
        $this->contents[$path]['files'] = $file;

        return  $this->contents[$path];
    }

    public function newFolder ($path, $name, $description = '')
    {
        $path = $path ?: 'me/skydrive';

        return $this->curl(self::baseUrl.$path, array(
            'name' => $name,
            'description' => $description
        ));
    }

    public function getFile ($file)
    {
        return $this->curl(self::baseUrl.$file.'/content?download=true', array(), false);
    }

    public function putFile ($file, $name, $path)
    {
        if (!is_file($file)) {
            throw new Exception(sprintf('"%s" not exists', $file));
        }

        $path = $path ?: 'me/skydrive';

        return $this->curl(self::baseUrl.$path.'/files/'.urlencode($name), array(
            'file' => $file
        ));
    }

    private function cookie ($key = null, $value = null)
    {
        $s = $this->settings;

        if (isset($_COOKIE[$s['cookie_name']]) && $_COOKIE[$s['cookie_name']]) {
            $cookie = unserialize(gzinflate(base64_decode($_COOKIE[$s['cookie_name']])));
        } else {
            $cookie = array();
        }

        if (is_null($key)) {
            return $cookie;
        }

        if (is_string($key) && is_null($value)) {
            return isset($cookie[$key]) ? $cookie[$key] : null;
        }

        if ($key === true) {
            return setcookie($s['cookie_name'], '', -3600, $s['cookie_path']);
        }

        if (is_array($key)) {
            $cookie = $key;
        } else {
            $cookie[$key] = $value;
        }

        $value = base64_encode(gzdeflate(serialize($cookie)));

        setcookie($s['cookie_name'], $value, $s['cookie_expire'], $s['cookie_path']);

        return $cookie;
    }

    private function curl ($url, $post = array(), $json = true)
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            ($post ? 'Content-Type: application/json' : ''),
            ($this->token ? ('Authorization: Bearer '.$this->token) : '')
        ));

        if ($post && isset($post['file']) && is_file($post['file'])) {
            $pointer = fopen($post['file'], 'r');

            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($curl, CURLOPT_PUT, true);
            curl_setopt($curl, CURLOPT_INFILE, $pointer);
        } else if ($post) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($post));
        }

        $response = curl_exec($curl);
        $response = ($response && $json) ? json_decode($response, true) : $response;

        if (preg_match('/^20/', curl_getinfo($curl, CURLINFO_HTTP_CODE))) {
            return (empty($response) && $json) ? array() : $response;
        }

        if (isset($response['error_description'])) {
            $error = $response['error_description'];
        } else if (isset($response['error']['message'])) {
            $error = $response['error']['message'];
        } else {
            $error = 'Unknown error';
        }

        throw new Exception($error);
    }

    static function uri ()
    {
        $https = (empty($_SERVER['HTTPS']) || ($_SERVER['HTTPS'] !== 'on')) ? false : true;
        $port = intval(getenv('SERVER_PORT'));

        if ($https) {
            $port = ($port && ($port !== 443)) ? (':'.$port) : '';
        } else {
            $port = ($port && ($port !== 80)) ? (':'.$port) : '';
        }

        $uri = preg_replace('/(^|\W)code=[^&]+/', '$1', getenv('REQUEST_URI'));
        $uri = preg_replace('/(\?|\&)$/', '', $uri);

        return 'http'.($https ? 's' : '').'://'.getenv('SERVER_NAME').$port.$uri;
    }
}
