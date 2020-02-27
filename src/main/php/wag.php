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

function errorHandler($errno, $msg)
{
    if (!(error_reporting() & $errno)) {
        return false;
    }
    http_response_code(500);
    die(htmlspecialchars($msg));
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

abstract class ItemType
{
    const ALBUM = 'album';
    const IMAGE = 'image';
    const VIDEO = 'video';
}

class PathEntry
{
    public $type;
    public $path;
    function __construct($type, $path)
    {
        $this->type = $type;
        $this->path = $path;
    }
    public function isAlbum()
    {
        return $this->type == ItemType::ALBUM;
    }
    public function isImage()
    {
        return $this->type == ItemType::IMAGE;
    }
    public function isVideo()
    {
        return $this->type == ItemType::VIDEO;
    }
}

class Item
{
    public $type;
    public $caption;
    function __construct($type, $caption)
    {
        $this->type = $type;
        $this->caption = $caption;
    }
}

class AlbumEntry
{
    public $type;
    public $caption;
    public $path;
    public $thumbnail;
    function __construct($type, $caption, $path, $thumbnail)
    {
        $this->type = $type;
        $this->caption = $caption;
        $this->path = $path;
        $this->thumbnail = $thumbnail;
    }
}

class Album extends Item
{
    public $albums = [];
    public $media = [];

    function __construct($caption)
    {
        parent::__construct(ItemType::ALBUM, $caption);
    }
}

class Image extends Item
{
    public $url;

    function __construct($caption, $url)
    {
        parent::__construct(ItemType::IMAGE, $caption);
        $this->url = $url;
    }
}

class VideoGroup
{
    public $videos = [];
    public $poster = null;
}

class VideoEntry
{
    public $url;
    public $mimeType = null;
    function __construct($url)
    {
        $this->url = $url;
    }
}

class Video extends Item
{
    public $alternatives = [];
    public $posterURL = null;

    function __construct($caption)
    {
        parent::__construct(ItemType::VIDEO, $caption);
    }
}

class WAG
{
    const CONFIG_FILE = 'wag.config.json';
    const CONFIG_LOCAL = 'local';
    const APP_PATH = '/app.js';
    const API_PATH = '/api';

    const IMAGE_EXT = array(
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif'
    );
    const VIDEO_EXT = array(
        'webm' => 'video/webm',
        'mp4' => 'video/mp4',
        'mpeg4' => 'video/mp4',
        'm4v' => 'video/mp4'
    );
    const CACHE_MAX_AGE = 3600;
    const WAG_DIR = '.wag';
    const METADATA_FILE = 'meta.json';
    const THUMBNAIL_FILE = 'tn.jpg';
    const ASSET_DEFAULT_THUMBNAIL = 'default-thumbnail';
    const META_ITEMS = 'items';
    const META_CAPTION = 'caption';
    const META_COPYRIGHT = 'copyright';
    const META_DATE = 'date';
    const META_WIDTH = 'width';
    const META_HEIGHT = 'height';
    const META_LAT = 'lat';
    const META_LON = 'lon';
    const META_SHUTTER = 'shutter';
    const META_APERTURE = 'aperture';
    const META_ISO = 'iso';
    const META_ZOOM = 'zoom';

    private $config;
    private $configType = self::CONFIG_LOCAL;
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
        if (is_file(self::CONFIG_FILE)) {
            $this->config = json_decode(self::localGetContent(self::CONFIG_FILE), true);
            if (array_key_exists('b2', $this->config)) {
                $this->configType = 'b2';
            }
        }
        $pathInfo = urldecode(substr($_SERVER['REQUEST_URI'], strlen($this->getScriptURLPath())));
        if (!self::isAPICall($pathInfo)) {
            throw new Exception('Invalid request path');
        }
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->pathSegments = explode('/', substr($pathInfo, strlen(self::API_PATH) + 1));
        if (!empty($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $this->query);
        }
        if (count($this->pathSegments) < 1) {
            throw new Exception('Invalid API path');
        }
        $handler = array_shift($this->pathSegments) . $this->method;
        if (!method_exists($this, $handler) || !((new ReflectionMethod($this, $handler))->isPublic())) {
            throw new Exception('Invalid API path');
        }
        call_user_func(array($this, $handler));
    }

    private static function isAPICall($path)
    {
        $apiPathLen = strlen(self::API_PATH);
        return !empty($path) && strlen($path) > $apiPathLen && substr($path, 0, $apiPathLen) === self::API_PATH && $path[$apiPathLen] === '/';
    }

    private function getScriptURLPath()
    {
        $pathInfoLen = empty($_SERVER['PATH_INFO']) ? 0 : strlen($_SERVER['PATH_INFO']);
        $phpSelfLen = strlen($_SERVER['PHP_SELF']);
        return ($phpSelfLen > $pathInfoLen ? substr($_SERVER['PHP_SELF'], 0, $phpSelfLen - $pathInfoLen) : $_SERVER['PHP_SELF']);
    }

    private static function getFullURL($path)
    {
        $server = getenv('WAG_API_SERVER', true);
        if ($server === false) {
            $server = '';
        }
        return $server . $path;
    }

    public function albumsGET()
    {
        $safePath = $this->getSafePathFromSegments($this->pathSegments);
        $itemsMeta = array();
        $albumCaption = basename($safePath);
        $metaFile = $this->getContent(self::getMetaDir($safePath) . '/' . self::METADATA_FILE);
        if ($metaFile != null) {
            $meta = json_decode($metaFile, true);
            if (array_key_exists(self::META_CAPTION, $meta)) {
                $albumCaption = $meta[self::META_CAPTION];
            }
            if (array_key_exists(self::META_ITEMS, $meta)) {
                $itemsMeta = $meta[self::META_ITEMS];
            }
        }
        $album = new Album($albumCaption);
        $groups = [];
        foreach ($this->listDir($safePath) as $entry) {
            $key = pathinfo($entry->path, PATHINFO_FILENAME);
            if (!isset($groups[$key])) {
                $groups[$key] = array(ItemType::ALBUM => [], ItemType::IMAGE => [], ItemType::VIDEO => []);
            }
            array_push($groups[$key][$entry->type], $entry->path);
        }
        foreach ($groups as $group) {
            foreach ($group[ItemType::ALBUM] as $entry) {
                $itemId = self::getMetaId($entry);
                $caption = array_key_exists($itemId, $itemsMeta) && array_key_exists(self::META_CAPTION, $itemsMeta[$itemId]) ?
                    $itemsMeta[$itemId][self::META_CAPTION] : basename($entry);
                array_push($album->albums, new AlbumEntry(ItemType::ALBUM, $caption, $entry, $this->getThumbnailURL($entry)));
            }
            if (count($group[ItemType::VIDEO]) > 0) {
                $videoGroup = new VideoGroup();
                $videoGroup->videos = $group[ItemType::VIDEO];
                if (count($group[ItemType::IMAGE]) > 0) {
                    $videoGroup->poster = reset($group[ItemType::IMAGE]);
                }
                $entry = reset($group[ItemType::VIDEO]);
                $itemId = self::getMetaId($entry);
                $caption = array_key_exists($itemId, $itemsMeta) && array_key_exists(self::META_CAPTION, $itemsMeta[$itemId]) ?
                    $itemsMeta[$itemId][self::META_CAPTION] : basename($entry);
                array_push($album->media, new AlbumEntry(ItemType::VIDEO, $caption, $entry, $this->getThumbnailURL($entry)));
            } else {
                foreach ($group[ItemType::IMAGE] as $entry) {
                    $itemId = self::getMetaId($entry);
                    $caption = array_key_exists($itemId, $itemsMeta) && array_key_exists(self::META_CAPTION, $itemsMeta[$itemId]) ?
                        $itemsMeta[$itemId][self::META_CAPTION] : basename($entry);
                    array_push($album->media, new AlbumEntry(ItemType::IMAGE, $caption, $entry, $this->getThumbnailURL($entry)));
                }
            }
        }
        self::outputResponse($album);
    }

    public function itemsGET()
    {
        $safePath = $this->getSafePathFromSegments($this->pathSegments);
        $type = self::guessMediaType($safePath);
        if ($type === ItemType::IMAGE) {
            $this->respondImage($safePath);
        } elseif ($type === ItemType::VIDEO) {
            $this->respondVideo($safePath);
        } else {
            throw new Exception('Item not found.');
        }
    }

    private function respondImage($safePath)
    {
        $image = new Image($this->getItemCaption($safePath), $this->getMediumURL($safePath));
        self::outputResponse($image);
    }

    private function respondVideo($safePath)
    {
        $videoGroup = $this->getVideoGroup($safePath);
        $video = new Video($this->getVideoCaption($videoGroup));
        foreach ($videoGroup->videos as $path) {
            $entry = new VideoEntry($this->getMediumURL($path));
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $entry->mimeType = self::VIDEO_EXT[$ext];
            array_push($video->alternatives, $entry);
        }
        if ($videoGroup->poster != null) {
            $video->posterURL =  $this->getMediumURL($videoGroup->poster);
        }
        self::outputResponse($video);
    }

    public function mediaGET()
    {
        if ($this->configType !== self::CONFIG_LOCAL) {
            throw new Exception('Not configured for serving media.');
        }
        $safePath = $this->getSafePathFromSegments($this->pathSegments);
        $ext = strtolower(pathinfo($safePath, PATHINFO_EXTENSION));
        switch (self::guessMediaType($safePath)) {
            case ItemType::IMAGE:
                header('Content-Type: ' . self::IMAGE_EXT[$ext]);
                break;
            case ItemType::VIDEO:
                header('Content-Type: ' . self::VIDEO_EXT[$ext]);
                break;
            default:
                throw new Exception('Unrecognized media type.');
        }
        header('Cache-Control: max-age=' . self::CACHE_MAX_AGE);
        self::localServeFile($safePath);
    }

    public function thumbnailsGET()
    {
        if ($this->configType !== self::CONFIG_LOCAL) {
            throw new Exception('Not configured for serving media.');
        }
        $safePath = $this->getSafePathFromSegments($this->pathSegments);
        $thumbnail = self::getMetaDir($safePath) . '/' . self::THUMBNAIL_FILE;
        if (!is_file(realpath($thumbnail))) {
            $this->pathSegments = array(self::ASSET_DEFAULT_THUMBNAIL);
            $this->assetsGET();
            return;
        }
        header('Content-Type: ' . self::IMAGE_EXT['jpg']);
        header('Cache-Control: max-age=' . self::CACHE_MAX_AGE);
        self::localServeFile($thumbnail);
    }

    public function assetsGET()
    {
        $asset = self::Assets[$this->pathSegments[0]];
        if (!$asset) {
            throw new Exception('Invalid asset');
        }
        header('Content-Type: ' . self::IMAGE_EXT['gif']);
        header('Cache-Control: max-age=' . self::CACHE_MAX_AGE);
        echo (base64_decode($asset));
    }

    private static function localServeFile($path)
    {
        $file = fopen(realpath($path), 'rb');
        fpassthru($file);
        fclose($file);
    }

    private static function guessMediaType($path)
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (array_key_exists($ext, self::IMAGE_EXT)) {
            return ItemType::IMAGE;
        } else if (array_key_exists($ext, self::VIDEO_EXT)) {
            return ItemType::VIDEO;
        } else {
            return null;
        }
    }

    private function getVideoGroup($safePath)
    {
        $videoGroup = new VideoGroup();
        $videoName = pathinfo($safePath, PATHINFO_FILENAME);
        foreach ($this->listDir(dirname($safePath)) as $entry) {
            $name = pathinfo($entry->path, PATHINFO_FILENAME);
            if ($name !== $videoName) {
                continue;
            }
            if ($entry->type === ItemType::VIDEO) {
                array_push($videoGroup->videos, $entry->path);
            } else if ($entry->type === ItemType::IMAGE) {
                $videoGroup->poster = $entry->path;
            }
        }
        if (count($videoGroup->videos) > 0) {
            return $videoGroup;
        } else {
            return null;
        }
    }

    private static function outputResponse($thing)
    {
        header('Content-Type: application/json');
        header('Cache-Control: private, max-age=' . self::CACHE_MAX_AGE);
        // bust the web server cache control if present
        header('Expires:');
        header('Pragma:');
        echo json_encode($thing, JSON_UNESCAPED_UNICODE);
    }

    private function getItemCaption($path)
    {
        $metaFile = $this->getContent(self::getMetaDir($path) . '/' . self::METADATA_FILE);
        if ($metaFile != null) {
            $meta = json_decode($metaFile, true);
            if (array_key_exists(self::META_CAPTION, $meta)) {
                return $meta[self::META_CAPTION];
            }
        }
        return basename($path);
    }

    private function getVideoCaption($videoGroup)
    {
        if ($videoGroup->poster != null) {
            return $this->getItemCaption($videoGroup->poster);
        }
        return $this->getItemCaption(reset($videoGroup->videos));
    }

    private function getSafePathFromSegments($pathSegments)
    {
        return $this->getSafePath(implode('/', $pathSegments));
    }

    private function getSafePath($path)
    {
        $normalPath = normalizer_normalize($path);
        $lenNormalPath = strlen($normalPath);
        $lenWagDir = strlen(self::WAG_DIR);
        if (
            $lenNormalPath >= $lenWagDir && substr($normalPath, 0, $lenWagDir) === self::WAG_DIR &&
            ($lenNormalPath === $lenWagDir || $normalPath[$lenWagDir] === '/')
        ) {
            throw new Exception('Invalid resource.');
        }
        switch ($this->configType) {
            case self::CONFIG_LOCAL:
                return self::localSafePath($normalPath);
            case 'b2':
                return $this->b2SafePath($normalPath);
            default:
                throw new Exception('Unrecognized config type ' . $this->configType);
        }
    }

    private static function localSafePath($normalPath)
    {
        $localPath = realpath($normalPath);
        $localPathLen = ($localPath !== false ? strlen($localPath) : 0);
        $scriptDir = realpath(__DIR__);
        $scriptDirLen = strlen($scriptDir);
        if (
            $localPath === false || $localPathLen < $scriptDirLen || strncmp($scriptDir, $localPath, $scriptDirLen) != 0 ||
            ($localPathLen > $scriptDirLen && $localPath[$scriptDirLen] !== '/')
        ) {
            throw new Exception("Invalid pointer to resource: " . $normalPath);
        }
        return substr($localPath, $scriptDirLen + ($localPathLen > $scriptDirLen ? 1 : 0));
    }

    private function b2SafePath($normalPath)
    {
        return $normalPath;
    }

    private function listDir($safePath)
    {
        switch ($this->configType) {
            case self::CONFIG_LOCAL:
                return self::localListDir($safePath);
            case 'b2':
                return $this->b2ListDir($safePath);
            default:
                throw new Exception('Unrecognized config type ' . $this->configType);
        }
    }

    private static function localListDir($path)
    {
        $entries = [];
        $localPath = realpath($path);
        if (!is_dir($localPath)) {
            throw new Exception('Not an album');
        }
        foreach (scandir($localPath) as $file) {
            if ($file === '.' || $file === '..' || $file === '.wag') {
                continue;
            }
            $entry = (strlen($path) > 0 ? $path . '/' : '') . $file;
            $localEntry = $localPath . '/' . $file;
            $type = null;
            if (is_file($localEntry)) {
                $type = self::guessMediaType($localEntry);
            } else if (is_dir($localEntry) && !is_file($localEntry . '/password.txt')) {
                $type = ItemType::ALBUM;
            }
            if ($type === null) {
                continue;
            }
            array_push($entries, new PathEntry($type, $entry));
        }
        return $entries;
    }

    private function b2ListDir($path)
    {
        $this->b2Authorize();

        $root = array_key_exists('root', $this->config['b2']) ? $this->config['b2']['root'] : '';
        $lenPath = strlen($path);
        $lenRoot = strlen($root);
        $prefix = $root . $path . ($lenPath > 0 && $path[$lenPath - 1] != '/' ? '/' : '');

        $entries = [];

        $startFileName = null;
        $isNotDone = true;
        while ($isNotDone) {
            $request = array('bucketId' => $this->config['b2']['bucketId'], 'prefix' => $prefix, 'delimiter' => '/', 'startFileName' => $startFileName);
            $session = curl_init($this->config['b2']['auth']['apiUrl'] .  '/b2api/v2/b2_list_file_names');
            curl_setopt($session, CURLOPT_POSTFIELDS, json_encode($request, JSON_UNESCAPED_UNICODE));
            $headers = array('Authorization: ' . $this->config['b2']['auth']['authorizationToken']);
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
                $type = null;
                if ($file['action'] === 'upload') {
                    $type = self::guessMediaType($entry);
                } else if ($file['action'] === 'folder') {
                    $type = ItemType::ALBUM;
                }
                if ($type === null) {
                    continue;
                }
                array_push($entries, new PathEntry($type, $entry));
            }
        }
        return $entries;
    }

    private function getContent($safePath)
    {
        switch ($this->configType) {
            case self::CONFIG_LOCAL:
                return self::localGetContent($safePath);
            case 'b2':
                return $this->b2GetContent($safePath);
            default:
                throw new Exception('Unrecognized config type ' . $this->configType);
        }
    }

    private static function localGetContent($path)
    {
        if (!is_file($path)) {
            return null;
        }
        return file_get_contents($path);
    }

    private function b2GetContent($path)
    {
        $root = array_key_exists('root', $this->config['b2']) ? $this->config['b2']['root'] : '';
        $session = curl_init($this->config['b2']['url'] . $root . $path);
        curl_setopt($session, CURLOPT_HTTPGET, true);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($session);
        curl_close($session);
        return $output;
    }

    private function getMediumURL($path)
    {
        switch ($this->configType) {
            case self::CONFIG_LOCAL:
                return $this->localGetMediumURL($path);
            case 'b2':
                return $this->b2GetMediumURL($path);
            default:
                throw new Exception('Unrecognized config type ' . $this->configType);
        }
    }

    private function localGetMediumURL($path)
    {
        return self::getFullURL($this->getScriptURLPath() . self::API_PATH . '/media/' . $path);
    }

    private function b2GetMediumURL($path)
    {
        $root = array_key_exists('root', $this->config['b2']) ? $this->config['b2']['root'] : '';
        return $this->config['b2']['url'] . implode('/', array_map('urlencode', explode('/', $root . $path)));
    }

    private function getThumbnailURL($path)
    {
        switch ($this->configType) {
            case self::CONFIG_LOCAL:
                return $this->localGetThumbnailURL($path);
            case 'b2':
                return $this->b2GetThumbnailURL($path);
            default:
                throw new Exception('Unrecognized config type ' . $this->configType);
        }
    }

    private function localGetThumbnailURL($path)
    {
        return self::getFullURL($this->getScriptURLPath() . self::API_PATH . '/thumbnails/' . $path);
    }

    private function b2GetThumbnailURL($path)
    {
        $root = array_key_exists('root', $this->config['b2']) ? $this->config['b2']['root'] : '';
        return $this->config['b2']['url'] . $root . self::WAG_DIR . '/' . self::getMetaId($path) . '/' . self::THUMBNAIL_FILE;
    }

    private static function getMetaDir($path)
    {
        return self::WAG_DIR . '/' . self::getMetaId($path);
    }

    private static function getMetaId($path)
    {
        return md5($path);
    }

    private function b2Authorize()
    {
        if (array_key_exists('auth', $this->config['b2'])) {
            return;
        }
        if (strlen(session_id()) < 1) {
            session_start();
        }
        if (isset($_SESSION['time']) && abs(time() - $_SESSION['time']) < self::CACHE_MAX_AGE) {
            $this->config['b2']['auth'] = json_decode($_SESSION['auth'], true);
        } else {
            $session = curl_init('https://api.backblazeb2.com/b2api/v2/b2_authorize_account');
            // credentials = base64_encode(appkeyId . ':' . appkey);
            $headers = array('Accept: application/json', 'Authorization: Basic ' . $this->config['b2']['cred']);
            curl_setopt($session, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($session, CURLOPT_HTTPGET, true);
            curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
            $output = curl_exec($session);
            curl_close($session);
            $_SESSION['time'] = time();
            $_SESSION['auth'] = $output;
            $this->config['b2']['auth'] = json_decode($output, true);
        }
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
