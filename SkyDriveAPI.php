<?php
namespace SkyDriveAPI;

use Exception;

class SkyDriveAPI
{
    const URL_AUTH = 'https://login.live.com/oauth20_authorize.srf';
    const URL_TOKEN = 'https://login.live.com/oauth20_token.srf';
    const URL_API = 'https://apis.live.net/v5.0/';

    const SETTINGS_CONTENTS_LIMIT = 1000;
    const SETTINGS_CONTENTS_SORT_BY = 'name';
    const SETTINGS_CONTENTS_SORT_ORDER = 'ascending';

    const SETTINGS_COOKIE_NAME = 'skydrive-php-api';
    const SETTINGS_COOKIE_EXPIRE = 2592000; // 3600 * 24 * 30
    const SETTINGS_COOKIE_PATH = '/';

    const SETTINGS_SCOPE = 'wl.basic wl.skydrive wl.skydrive_update wl.offline_access';

    private $token = '';
    private $settings = array();
    private $api = array();
    private $contents = array();

    public function __construct ($settings)
    {
        if (function_exists('curl_init') === false) {
            throw new Exception('cURL PHP extension is required');
        }

        $this->setSettings($settings);

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

    public function setSettings ($s)
    {
        if (empty($s['client_id'])) {
            throw new Exception('"client_id" is a required parameter to settings');
        }

        if (empty($s['client_secret'])) {
            throw new Exception('"client_secret" is a required parameter to settings');
        }

        if (empty($s['contents_limit'])) {
            $s['contents_limit'] = self::SETTINGS_CONTENTS_LIMIT;
        }

        if (empty($s['contents_sort_by'])) {
            $s['contents_sort_by'] = self::SETTINGS_CONTENTS_SORT_BY;
        }

        if (empty($s['contents_sort_order'])) {
            $s['contents_sort_order'] = self::SETTINGS_CONTENTS_SORT_ORDER;
        }

        if (empty($s['redirect_uri'])) {
            $s['redirect_uri'] = self::uri();
        }

        if (empty($s['scope'])) {
            $s['scope'] = self::SETTINGS_SCOPE;
        }

        if (empty($s['cookie_expire'])) {
            $s['cookie_expire'] = time() + self::SETTINGS_COOKIE_EXPIRE;
        } else if ($s['cookie_expire'] <= time()) {
            $s['cookie_expire'] += time();
        }

        if (empty($s['cookie_name'])) {
            $s['cookie_name'] = self::SETTINGS_COOKIE_NAME;
        }

        if (empty($s['cookie_path'])) {
            $s['cookie_path'] = self::SETTINGS_COOKIE_PATH;
        }

        return $this->settings = $s;
    }

    private function authenticate ()
    {
        $this->cookie(true);

        $url = self::URL_AUTH.'?'.http_build_query(array(
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
            $response = $this->curl('GET', self::URL_TOKEN.'?'.http_build_query($query));
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
            $response = $this->curl('GET', self::URL_TOKEN.'?'.http_build_query($query));
        } catch (Exception $e) {
            throw new Exception('Sorry but your login haven\'t been authorized to use SkyDrive. Error: '.$e->getMessage());
        }

        $response['expires_in'] += time();

        $this->cookie($response);

        return $this->token = $response['access_token'];
    }

    public function api ($method, $cmd, $data = array(), $json = true, $refresh = false)
    {
        $key = $method.$cmd.serialize($data);

        if (isset($this->api[$key]) && ($refresh === false)) {
            return $this->api[$key];
        }

        return $this->api[$key] = $this->curl($method, self::URL_API.$cmd, $data, $json);
    }

    public function me ($cmd = 'me')
    {
        switch ($cmd) {
            case 'me':
                return $this->api('GET', 'me');

            case 'quota':
                return $this->api('GET', 'me/skydrive/quota');

            case 'permissions':
                return $this->api('GET', 'me/permissions');
        }

        throw new Exception(sprintf('"%s" command is not available', $cmd));
    }

    public function createFolder ($path, $name, $description = '')
    {
        $path = $path ?: 'me/skydrive';

        return $this->api('POST', $path, array(
            'name' => $name,
            'description' => $description
        ));
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
            $current = $this->api('GET', $path);

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
            $contents = $this->api('GET', $path.'/files?'.http_build_query(array(
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

    public function downloadFile ($file)
    {
        return $this->api('GET', $file.'/content?download=true', array(), false);
    }

    public function uploadFile ($file, $name, $path)
    {
        if (!is_file($file)) {
            throw new Exception(sprintf('"%s" not exists', $file));
        }

        $path = $path ?: 'me/skydrive';

        return $this->api('PUT', $path.'/files/'.urlencode($name), array(
            'file' => $file
        ));
    }

    public function delete ($current)
    {
        if (empty($current)) {
            throw new Exception('This file or folder can not be deleted');
        }

        return $this->api('DELETE', $current);
    }

    public function copy ($original, $path)
    {
        if (empty($original)) {
            throw new Exception('This file can not be deleted');
        }

        return $this->api('COPY', $original, array(
            'destination' => $path
        ));
    }

    public function move ($original, $path)
    {
        if (empty($original)) {
            throw new Exception('This file can not be deleted');
        }

        return $this->api('MOVE', $original, array(
            'destination' => $path
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

    private function curl ($request, $url, $data = array(), $json = true)
    {
        $request = strtoupper($request);
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $request);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            ($data ? 'Content-Type: application/json' : ''),
            ($this->token ? ('Authorization: Bearer '.$this->token) : '')
        ));

        if (defined('CURLOPT_'.$request)) {
            curl_setopt($curl, constant('CURLOPT_'.$request), true);
        }

        if ($data && isset($data['file']) && is_file($data['file'])) {
            curl_setopt($curl, CURLOPT_INFILE, fopen($data['file'], 'r'));
            unset($data['file']);
        }

        if ($data) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
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

        $uri = preg_replace('/(^|\W)code=[^&]*/', '$1', getenv('REQUEST_URI'));
        $uri = preg_replace('/(\?|&)$/', '', $uri);

        return 'http'.($https ? 's' : '').'://'.getenv('SERVER_NAME').$port.$uri;
    }
}
