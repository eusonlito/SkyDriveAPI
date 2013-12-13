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
    const settings_cookie_expire = 3600;
    const settings_cookie_path = '/';

    const settings_scope = 'wl.basic wl.skydrive wl.skydrive_update';

    private $token = '';
    private $settings = array();
    private $info = array();
    private $contents = array();

    public function __construct ($settings)
    {
        if (function_exists('curl_init') === false) {
            throw new Exception('cURL PHP extension is required');
        }

        $settings = $this->setSettings($settings);

        if (isset($_COOKIE[$settings['cookie_name']])) {
            $this->token = $_COOKIE[$settings['cookie_name']];
        }

        if (empty($this->token) && isset($_GET['code'])) {
            $this->getAccessToken($_GET['code']);
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
            $settings['cookie_expire'] = self::settings_cookie_expire;
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
        $url = self::authUrl.'?'.http_build_query(array(
            'redirect_uri' => $this->settings['redirect_uri'],
            'client_id' => $this->settings['client_id'],
            'scope' => $this->settings['scope'],
            'response_type' => 'code'
        ));

        die(header('Location: '.$url));
    }

    private function getAccessToken ($code)
    {
        $query = array(
            'client_id' => $this->settings['client_id'],
            'client_secret' => $this->settings['client_secret'],
            'redirect_uri' => $this->settings['redirect_uri']
        );

        if (empty($this->token)) {
            $query['code'] = $code;
            $query['grant_type'] = 'authorization_code';
        } else {
            $query['code'] = $this->token;
            $query['grant_type'] = 'refresh_token';
        }

        $this->cookie('', true);

        try {
            $response = $this->curl(self::codeUrl.'?'.http_build_query($query));
        } catch (Exception $e) {
            throw new Exception('Sorry but your login haven\'t been authorized to use SkyDrive. Error: '.$e->getMessage());
        }

        $this->cookie($response['access_token']);

        die(header('Location: '.self::uri()));
    }

    public function accountInfo ($refresh = null)
    {
        if ($this->info && ($refresh === null)) {
            return $this->info;
        }

        return $this->info = $this->curl(self::baseUrl.'me');
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

    public function getFile ($file) {
        return $this->curl(self::baseUrl.$file.'/content?download=true', array(), false);
    }

    public function putFile ($file, $name, $path) {
        $path = $path ?: 'me/skydrive';

        return $this->curl(self::baseUrl.$path.'/files/'.urlencode($name), array(
            'file' => $file
        ), true, 201);
    }

    private function cookie ($value, $unset = false)
    {
        return setcookie(
            $this->settings['cookie_name'],
            $value,
            ($unset ? -3600 : (time() + $this->settings['cookie_expire'])),
            $this->settings['cookie_path']
        );
    }

    private function curl ($url, $post = array(), $json = true, $code = 200)
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            ($this->token ? '' : 'Content-Type: application/json'),
            ($this->token ? ('Authorization: Bearer '.$this->token) : '')
        ));

        if ($post && isset($post['file'])) {
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

        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) !== $code) {
            if (isset($response['error_description'])) {
                $error = $response['error_description'];
            } else if (isset($response['error']['message'])) {
                $error = $response['error']['message'];
            } else {
                $error = 'Unknown error';
            }

            throw new Exception($error);
        }

        return (empty($response) && $json) ? array() : $response;
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
        $uri = preg_replace('/\?$/', '', $uri);

        return 'http'.($https ? 's' : '').'://'.getenv('SERVER_NAME').$port.$uri;
    }
}
