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

if (version_compare(PHP_VERSION, '5.4') < 0) {
    die('PHP version greater than 5.4 is required.');
}

function errorHandler($errno, $msg)
{
    if (!(error_reporting() & $errno)) {
        return FALSE;
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

if (!extension_loaded('intl')) {
    throw new Exception('PHP extension "intl" is required.');
}

abstract class ItemType
{
    const ALBUM = 'album';
    const IMAGE = 'image';
    const VIDEO = 'video';
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
    function __construct($type, $caption, $path)
    {
        $this->type = $type;
        $this->caption = $caption;
        $this->path = $path;
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
    public $path;

    function __construct($caption, $path)
    {
        parent::__construct(ItemType::IMAGE, $caption);
        $this->path = $path;
    }
}

class VideoGroup
{
    public $videos = [];
    public $poster = null;
}

class VideoEntry
{
    public $path;
    public $mimeType = null;
    function __construct($path)
    {
        $this->path = $path;
    }
}

class Video extends Item
{
    public $alternatives = [];
    public $posterPath = null;

    function __construct($caption)
    {
        parent::__construct(ItemType::VIDEO, $caption);
    }
}

class WAG
{
    private const APP_PATH = '/app.js';
    private const API_PATH = '/api';
    private const PHOTO_EXT = array(
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif'
    );
    private const VIDEO_EXT = array(
        'webm' => 'video/webm',
        'mp4' => 'video/mp4',
        'mpeg4' => 'video/mp4',
        'm4v' => 'video/mp4'
    );
    private const ASSET_DEFAULT_THUMBNAIL = 'default-thumbnail';

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
        if (!self::isAPICall($_SERVER['PATH_INFO'])) {
            throw new Exception('Invalid request path');
        }
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->pathSegments = explode('/', substr($_SERVER['PATH_INFO'], strlen(self::API_PATH) + 1));
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
        array($this, $handler)();
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

    public function itemGET()
    {
        $safePath = $this->getSafePathFromSegments($this->pathSegments);
        if ($this->isImage($safePath)) {
            $this->respondImage($safePath);
        } elseif ($this->isVideo($safePath)) {
            $this->respondVideo($safePath);
        } elseif ($this->isDir($safePath)) {
            $this->respondAlbum($safePath);
        }
    }

    private function respondAlbum($safePath)
    {
        $album = new Album($this->getAlbumCaption($safePath));
        $groups = [];
        foreach ($this->listDir($safePath) as $entry) {
            $key = pathinfo($entry, PATHINFO_FILENAME);
            if (!isset($groups[$key])) {
                $groups[$key] = array(ItemType::ALBUM => [], ItemType::IMAGE => [], ItemType::VIDEO => []);
            }
            if ($this->isImage($entry)) {
                array_push($groups[$key][ItemType::IMAGE], $entry);
            } else if ($this->isVideo($entry)) {
                array_push($groups[$key][ItemType::VIDEO], $entry);
            } else if ($this->isDir($entry)) {
                array_push($groups[$key][ItemType::ALBUM], $entry);
            }
        }
        foreach ($groups as $group) {
            foreach ($group[ItemType::ALBUM] as $entry) {
                array_push($album->albums, new AlbumEntry(ItemType::ALBUM, $this->getAlbumCaption($entry), $entry));
            }
            if (count($group[ItemType::VIDEO]) > 0) {
                $videoGroup = new VideoGroup();
                $videoGroup->videos = $group[ItemType::VIDEO];
                if (count($group[ItemType::IMAGE]) > 0) {
                    $videoGroup->poster = reset($group[ItemType::IMAGE]);
                }
                $entry = reset($group[ItemType::VIDEO]);
                array_push($album->media, new AlbumEntry(ItemType::VIDEO, $this->getVideoCaption($videoGroup), $entry));
            } else {
                foreach ($group[ItemType::IMAGE] as $entry) {
                    array_push($album->media, new AlbumEntry(ItemType::IMAGE, $this->getImageCaption($entry), $entry));
                }
            }
        }
        self::outputAsJSON($album);
    }

    private function respondImage($safePath)
    {
        $image = new Image($this->getImageCaption($safePath), $safePath);
        self::outputAsJSON($image);
    }

    private function respondVideo($safePath)
    {
        $video = new Video($this->getImageCaption($safePath));
        $videoGroup = $this->getVideoGroup($safePath);
        foreach ($videoGroup->videos as $path) {
            $entry = new VideoEntry($path);
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $entry->mimeType = self::VIDEO_EXT[$ext];
            array_push($video->alternatives, $entry);
        }
        $video->posterPath = $videoGroup->poster;
        self::outputAsJSON($video);
    }

    public function mediaGET()
    {
        $safePath = $this->getSafePathFromSegments($this->pathSegments);
        $ext = strtolower(pathinfo($safePath, PATHINFO_EXTENSION));
        if ($this->isImage($safePath)) {
            header('Content-Type: ' . self::PHOTO_EXT[$ext]);
        } else if ($this->isVideo($safePath)) {
            header('Content-Type: ' . self::VIDEO_EXT[$ext]);
        }
        $this->serveFile($safePath);
    }

    public function thumbnailsGET()
    {
        $safePath = $this->getSafePathFromSegments($this->pathSegments);
        $image = null;
        if ($this->isImage($safePath)) {
            $image = $safePath;
        } else if ($this->isVideo($safePath)) {
            $image = $this->getVideoGroup($safePath)->poster;
        } else if ($this->isDir($safePath)) {
            $image = $this->getFirstImage($safePath);
        }
        if ($image == null) {
            $this->pathSegments = array(self::ASSET_DEFAULT_THUMBNAIL);
            $this->assetsGET();
            return;
        }
        $ext = strtolower(pathinfo($image, PATHINFO_EXTENSION));
        header('Content-Type: ' . self::PHOTO_EXT[$ext]);
        $this->serveFile($image);
    }

    public function assetsGET()
    {
        $asset = self::Assets[$this->pathSegments[0]];
        if (!$asset) {
            throw new Exception('Invalid asset');
        }
        header('Content-Type: ' . self::PHOTO_EXT['gif']);
        echo (base64_decode($asset));
    }

    private function serveFile($safePath)
    {
        self::localServeFile($safePath);
    }

    private static function localServeFile($path)
    {
        $file = fopen(realpath($path), 'rb');
        fpassthru($file);
        fclose($file);
    }

    private function isDir($safePath)
    {
        return self::localIsDir($safePath);
    }

    private static function localIsDir($path)
    {
        return is_dir(realpath($path));
    }

    private function isImage($safePath)
    {
        return self::localIsImage($safePath);
    }

    private static function localIsImage($path)
    {
        $localPath = realpath($path);
        if (!is_file($localPath)) {
            return FALSE;
        }
        $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
        if (array_key_exists($ext, self::PHOTO_EXT)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    private function isVideo($safePath)
    {
        return self::localIsVideo($safePath);
    }

    private static function localIsVideo($path)
    {
        $localPath = realpath($path);
        if (!is_file($localPath)) {
            return FALSE;
        }
        $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
        if (array_key_exists($ext, self::VIDEO_EXT)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    private function getVideoGroup($safePath)
    {
        if (!$this->isVideo($safePath)) {
            return null;
        }
        $videoGroup = new VideoGroup();
        $videoName = pathinfo($safePath, PATHINFO_FILENAME);
        foreach ($this->listDir(dirname($safePath)) as $entry) {
            $name = pathinfo($entry, PATHINFO_FILENAME);
            if ($name !== $videoName) {
                continue;
            }
            if ($this->isVideo($entry)) {
                array_push($videoGroup->videos, $entry);
            } else if ($this->isImage($entry)) {
                $videoGroup->poster = $entry;
            }
        }
        return $videoGroup;
    }

    private static function outputAsJSON($thing)
    {
        header('Content-Type: application/json');
        echo json_encode($thing, JSON_UNESCAPED_UNICODE);
    }

    private function getAlbumCaption($path)
    {
        return basename($path);
    }

    private function getImageCaption($path)
    {
        return basename($path);
    }

    private function getVideoCaption($videoGroup)
    {
        return basename(reset($videoGroup->videos));
    }

    private function getFirstImage($safePath)
    {
        $entries = $this->listDir($safePath);
        foreach ($entries as $entry) {
            if ($this->isImage($entry)) {
                return $entry;
            }
        }
        return null;
    }

    private function getSafePathFromSegments($pathSegments)
    {
        return $this->getSafePath(implode('/', $pathSegments));
    }

    private function getSafePath($path)
    {
        return self::localSafePath($path);
    }

    private static function localSafePath($path)
    {
        $localPath = realpath(normalizer_normalize($path));
        $localPathLen = ($localPath !== FALSE ? strlen($localPath) : 0);
        $scriptDir = realpath(__DIR__);
        $scriptDirLen = strlen($scriptDir);
        if (
            $localPath === FALSE || $localPathLen < $scriptDirLen || strncmp($scriptDir, $localPath, $scriptDirLen) != 0 ||
            ($localPathLen > $scriptDirLen && $localPath[$scriptDirLen] !== '/')
        ) {
            throw new Exception("Invalid pointer to resource: " . $path);
        }
        return substr($localPath, $scriptDirLen + ($localPathLen > $scriptDirLen ? 1 : 0));
    }

    private function listDir($safePath)
    {
        return self::localListDir($safePath);
    }

    private static function localListDir($path)
    {
        $entries = [];
        foreach (scandir(realpath($path)) as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            array_push($entries, ($path !== '' ? $path . '/' : '') . $file);
        }
        return $entries;
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

    private const Assets = array(
        // ASSETS_IN_PHP
    );
}

(new WAG())->run();
