<?php
/*
 * Copyright (c) 2020, Lenko Grigorov
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *    * Redistributions of source code must retain the above copyright
 *      notice, this list of conditions and the following disclaimer.
 *    * Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in the
 *      documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDER ''AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

if (version_compare(PHP_VERSION, '5.6') < 0) {
    die('PHP version greater than 5.6 is required.');
}

function serveError($code, $msg = '')
{
    http_response_code($code);
    die(htmlspecialchars($msg));
}

function errorHandler($errno, $msg)
{
    if (!(error_reporting() & $errno)) {
        return false;
    }
    serveError(500, $msg);
}
set_error_handler('errorHandler');

function exceptionHandler($exception)
{
    errorHandler(E_ERROR, $exception->getMessage());
}
set_exception_handler('exceptionHandler');

if (!extension_loaded('curl') || !extension_loaded('intl')) {
    throw new Exception('PHP extensions "curl" and "intl" are required.');
}

abstract class ListingEntryType
{
    const ALBUM = 'album';
    const MEDIUM = 'medium';
}

class ListingEntry
{
    public $type;
    public $path;
    function __construct($type, $path)
    {
        $this->type = $type;
        $this->path = $path;
    }
}

class AlbumListing
{
    public $mediaURL;
    public $entries = [];
    function __construct($mediaURL)
    {
        $this->mediaURL = $mediaURL;
    }
}

interface Gallery
{
    public function getMediaURL();
    public function getSafePath($pathSegments);
    public function list($pathSegments);
}

class LocalGallery implements Gallery
{
    public function getMediaURL()
    {
        return WAG::getFullURL(WAG::getScriptURLPath() . WAG::API_PATH . '/media/');
    }

    public function getSafePath($pathSegments)
    {
        $normalPath = normalizer_normalize(implode('/', $pathSegments));
        $localPath = realpath($normalPath);
        $localPathLen = ($localPath !== false ? strlen($localPath) : 0);
        $scriptDir = realpath(__DIR__);
        $scriptDirLen = strlen($scriptDir);
        if (
            $localPath === false || $localPathLen < $scriptDirLen || strncmp($scriptDir, $localPath, $scriptDirLen) != 0 ||
            ($localPathLen > $scriptDirLen && $localPath[$scriptDirLen] !== '/')
        ) {
            serveError(404);
        }
        return substr($localPath, $scriptDirLen + ($localPathLen > $scriptDirLen ? 1 : 0));
    }

    public function list($pathSegments)
    {
        $safePath = $this->getSafePath($pathSegments);
        $entries = [];
        $localPath = realpath($safePath);
        if (!is_dir($localPath)) {
            serveError(404);
        }
        foreach (scandir($localPath) as $file) {
            if ($file === '.' || $file === '..' || $file === '.wag') {
                continue;
            }
            $entry = (strlen($safePath) > 0 ? $safePath . '/' : '') . $file;
            $localEntry = $localPath . '/' . $file;
            if (is_file($localEntry) && WAG::isMedium($localEntry)) {
                $type = ListingEntryType::MEDIUM;
            } else if (is_dir($localEntry) && !is_file($localEntry . '/password.txt')) {
                $type = ListingEntryType::ALBUM;
            } else {
                continue;
            }
            array_push($entries, new ListingEntry($type, $entry));
        }
        return $entries;
    }
}

class B2Gallery implements Gallery
{
    private $config;
    private $root;
    private $auth = null;

    public function __construct($config)
    {
        $this->config = $config['b2'];
        $this->root = array_key_exists('root', $this->config) ? $this->config['root'] : '';
    }

    public function getMediaURL()
    {
        return $this->config['url'] . WAG::urlencodeSegments($this->root);
    }

    public function getSafePath($pathSegments)
    {
        return normalizer_normalize(implode('/', $pathSegments));
    }

    public function list($pathSegments)
    {
        $this->b2Authorize();

        $path = $this->getSafePath($pathSegments);
        $lenPath = strlen($path);
        $lenRoot = strlen($this->root);
        $prefix = $this->root . $path . ($lenPath > 0 && $path[$lenPath - 1] != '/' ? '/' : '');

        $entries = [];

        $startFileName = null;
        $isNotDone = true;
        while ($isNotDone) {
            $request = array('bucketId' => $this->config['bucketId'], 'prefix' => $prefix, 'delimiter' => '/', 'startFileName' => $startFileName);
            $session = curl_init($this->auth['apiUrl'] .  '/b2api/v2/b2_list_file_names');
            curl_setopt($session, CURLOPT_POSTFIELDS, json_encode($request, JSON_UNESCAPED_UNICODE));
            $headers = array('Authorization: ' . $this->auth['authorizationToken']);
            curl_setopt($session, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($session, CURLOPT_POST, true);
            curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
            $output = curl_exec($session);
            curl_close($session);
            $response = json_decode($output, true);
            $startFileName = $response['nextFileName'];
            $isNotDone = $startFileName !== null;

            foreach (json_decode($output, true)['files'] as $file) {
                $entry = substr($file['fileName'], $lenRoot);
                $lenEntry = strlen($entry);
                if ($lenEntry > 0 && $entry[$lenEntry - 1] === '/') {
                    $entry = substr($entry, 0, $lenEntry - 1);
                }
                if (basename($entry) === '.wag') {
                    continue;
                }
                if ($file['action'] === 'upload' && WAG::isMedium($entry)) {
                    $type = ListingEntryType::MEDIUM;
                } else if ($file['action'] === 'folder') {
                    $type = ListingEntryType::ALBUM;
                } else {
                    continue;
                }
                array_push($entries, new ListingEntry($type, $entry));
            }
        }
        return $entries;
    }

    private function b2Authorize()
    {
        if ($this->auth !== null) {
            return;
        }
        if (strlen(session_id()) < 1) {
            session_start();
        }
        if (isset($_SESSION['time']) && abs(time() - $_SESSION['time']) < WAG::CACHE_MAX_AGE) {
            $this->auth = json_decode($_SESSION['auth'], true);
        } else {
            $session = curl_init('https://api.backblazeb2.com/b2api/v2/b2_authorize_account');
            // credentials = base64_encode(appkeyId . ':' . appkey);
            $headers = array('Accept: application/json', 'Authorization: Basic ' . $this->config['cred']);
            curl_setopt($session, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($session, CURLOPT_HTTPGET, true);
            curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
            $output = curl_exec($session);
            curl_close($session);
            $_SESSION['time'] = time();
            $_SESSION['auth'] = $output;
            $this->auth = json_decode($output, true);
        }
    }
}

class WAG
{
    const CONFIG_FILE = 'wag.config.json';
    const APP_PATH = '/app.js';
    const API_PATH = '/api';

    const MEDIA_EXT = array(
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webm' => 'video/webm',
        'mp4' => 'video/mp4',
        'mpeg4' => 'video/mp4',
        'm4v' => 'video/mp4'
    );
    const OTHER_EXT = array(
        'json' => 'application/json'
    );
    const CACHE_MAX_AGE = 3600;
    const WAG_DIR = '.wag';
    const METADATA_FILE = 'meta.json';
    const THUMBNAIL_FILE = 'tn.jpg';

    private $gallery;
    private $method;
    private $pathSegments;
    private $query = '';

    public function run()
    {
        if (empty($_SERVER['PATH_INFO']) || $_SERVER['PATH_INFO'] === '/') {
            $this->serveHTML();
            return;
        }
        if ($_SERVER['PATH_INFO'] === self::APP_PATH) {
            $this->serveScript();
            return;
        }
        $pathInfo = urldecode(substr($_SERVER['REQUEST_URI'], strlen($this->getScriptURLPath())));
        if (!self::isAPICall($pathInfo)) {
            serveError(404);
        }

        if (is_file(self::CONFIG_FILE)) {
            $config = json_decode(file_get_contents(self::CONFIG_FILE), true);
            if (array_key_exists('b2', $config)) {
                $this->gallery = new B2Gallery($config);
            } else {
                $this->gallery = new LocalGallery();
            }
        } else {
            $this->gallery = new LocalGallery();
        }
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->pathSegments = explode('/', substr($pathInfo, strlen(self::API_PATH) + 1));
        if (!empty($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $this->query);
        }
        if (count($this->pathSegments) < 1) {
            serveError(404, 'Invalid API call');
        }
        $handler = array_shift($this->pathSegments) . $this->method;
        if (!method_exists($this, $handler) || !((new ReflectionMethod($this, $handler))->isPublic())) {
            serveError(404, 'Invalid API call');
        }
        call_user_func(array($this, $handler));
    }

    public function albumsGET()
    {
        $album = new AlbumListing($this->gallery->getMediaURL());
        $album->entries = $this->gallery->list($this->pathSegments);
        self::serveResponse(json_encode($album, JSON_UNESCAPED_UNICODE), self::OTHER_EXT['json']);
    }

    public function mediaGET()
    {
        if (!($this->gallery instanceof LocalGallery)) {
            throw new Exception('Not configured for serving local resources.');
        }
        $safePath = $this->gallery->getSafePath($this->pathSegments);
        if (!is_file($safePath)) {
            serveError(404);
        }
        if (self::isMetaPath($safePath)) {
            switch (basename($safePath)) {
                case self::THUMBNAIL_FILE:
                case self::METADATA_FILE:
                    break;
                default:
                    serveError(404);
            }
        } else {
            if (!self::isMedium($safePath)) {
                serveError(404);
            }
        }
        self::serveFile($safePath);
    }

    public function assetsGET()
    {
        if (count($this->pathSegments) < 1 || !array_key_exists($this->pathSegments[0], self::Assets)) {
            serveError(404);
        }
        $asset = self::Assets[$this->pathSegments[0]];
        self::serveResponse(base64_decode($asset), self::MEDIA_EXT['gif']);
    }

    private static function isAPICall($path)
    {
        $apiPathLen = strlen(self::API_PATH);
        return !empty($path) && strlen($path) > $apiPathLen && substr($path, 0, $apiPathLen) === self::API_PATH && $path[$apiPathLen] === '/';
    }

    public static function getScriptURLPath()
    {
        $pathInfoLen = empty($_SERVER['PATH_INFO']) ? 0 : strlen($_SERVER['PATH_INFO']);
        $phpSelfLen = strlen($_SERVER['PHP_SELF']);
        return ($phpSelfLen > $pathInfoLen ? substr($_SERVER['PHP_SELF'], 0, $phpSelfLen - $pathInfoLen) : $_SERVER['PHP_SELF']);
    }

    public static function getFullURL($path)
    {
        $server = getenv('WAG_API_SERVER', true);
        if ($server === false) {
            $server = '';
        }
        return $server . $path;
    }

    private static function isMetaPath($safePath)
    {
        $lenPath = strlen($safePath);
        $lenWagDir = strlen(self::WAG_DIR);
        return $lenPath >= $lenWagDir && substr($safePath, 0, $lenWagDir) === self::WAG_DIR &&
            ($lenPath === $lenWagDir || $safePath[$lenWagDir] === '/');
    }

    public static function isMedium($path)
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return array_key_exists($ext, self::MEDIA_EXT);
    }

    public static function urlencodeSegments($url)
    {
        return implode('/', array_map('urlencode', explode('/', $url)));
    }

    private static function serveResponse($response, $mimeType)
    {
        header('Content-Type: ' . $mimeType);
        header('Cache-Control: private, max-age=' . self::CACHE_MAX_AGE);
        // bust the web server cache control if present
        header('Expires:');
        header('Pragma:');
        echo ($response);
    }

    private static function serveFile($safePath)
    {
        $ext = strtolower(pathinfo($safePath, PATHINFO_EXTENSION));
        if (array_key_exists($ext, self::MEDIA_EXT)) {
            $mimeType = self::MEDIA_EXT[$ext];
        } elseif (array_key_exists($ext, self::OTHER_EXT)) {
            $mimeType = self::OTHER_EXT[$ext];
        } else {
            $mimeType = null;
        }
        if ($mimeType !== null) {
            header('Content-Type: ' . $mimeType);
        }
        header('Cache-Control: private, max-age=' . self::CACHE_MAX_AGE);
        // bust the web server cache control if present
        header('Expires:');
        header('Pragma:');
        $file = fopen(realpath($safePath), 'rb');
        fpassthru($file);
        fclose($file);
    }

    private function serveHTML()
    {
        header('Content-Type: text/html');
        echo ('<html><head><script>const API_ENDPOINT = ' .
            json_encode($this->getScriptURLPath() . self::API_PATH) .
            ';</script><script src="' .
            htmlspecialchars($this->getScriptURLPath() . self::APP_PATH) .
            '"></script></head><body class="wagBody"><div id="wag"></div></body></html>');
    }

    private function serveScript()
    {
        header('Content-Type: application/javascript');
        echo ('//JS_SCRIPT_IN_PHP');
    }

    const Assets = array(
        // ASSETS_IN_PHP
    );
}

(new WAG())->run();
