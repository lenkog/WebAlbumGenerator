<?php

/*
 * Copyright (c) 2014, Lenko Grigorov
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

/**
 * The goal of this software is to serve as a simple drop-in image gallery script.
 * The following describes the intended functionality in more detail.
 *
 * When placed in a folder, it will render a web page with all the images
 * and videos in the folder. All subfolders will be rendered as sub-albums.
 *
 * The script does not require anything besides PHP5 with the GD extension.
 * Furthermore, the script will not make any modifications to the content of
 * the folder where it is placed. In fact, it does not require write access at all.
 *
 * The supported image formats are JPEG, PNG and GIF. The supported video
 * formats are WEBM, MP4, and M4V. The script is not capable of extracting thumbnails
 * from video files.
 *
 * Note: This scripts generates all web pages, thumbnails, etc. on the go for
 * every separate site access. Thus, the script is not suitable for sites
 * with a high volume of traffic or with an exceptionally large collection of
 * images.
 *
 * The captions of the albums will be retrieved from the folder names.
 * The captions of the images and videos will be retrieved from the file names
 * unless an image has IPTC metadata and the "Caption" field in the metadata is
 * not empty. In such a case, the IPTC "Caption" field will be used for the
 * image caption.
 *
 * The albums, images and videos will be sorted according to the folder/file names.
 * If images have to be sorted differently, it is necessary to rename the files
 * accordingly. For example, to sort by capture date, you can use the exiftool to
 * rename the image files: exiftool "-FileName<CreateDate" *
 *
 * If there are multiple videos with the same file name (e.g., "video.webm" and "video.mp4"),
 * then these files will be considered as alternative encodings of the same video.
 * If there is an images with the same file name as a video (e.g., "video.jpg" and "video.mp4"),
 * then the image will be considered as a snapshot/preview of the video and will be
 * used to generate a thumbnail for the video. Also, the metadata from the image file
 * will be used for the video. For example, the IPTC "Caption" field of the image
 * file will be used as the caption for the video.
 *
 * If the content of a folder should be protected from public access via the script,
 * you can place a file called "password.txt" in the folder. Then, the script will
 * request the password contained in the file to access the album content. The same
 * password will apply to all subfolders unless a subfolder contains another "password.txt"
 * file with a different password. A "password.txt" file can contain multiple lines
 * with alternative passwords. An empty "password.txt" file will reset the folder and
 * subfolders for public access. The protection described here will work only when accessing
 * the content via the gallery script and will have no effect on direct access to the content.
 *
 * After entering the password, the URL for a protected folder will contain a "key=..."
 * portion. Such a URL can be sent to others to view the protected content without
 * requiring password entry.
 *
 * Note: The pasword protection scheme offered by this script is not secure and
 * must not be used to protect very sensitive material. Once a URL with the "key=..."
 * portion is known to others, it is possible to recover the password used to access
 * the content. Furthermore, the protection scheme works only when using the script;
 * it does not block access of the content through direct URLs. It should be considered
 * only in conjunction with other access restriction schemes, such as .htaccess files:
 * <files wag.php>
 * allow from all
 * </files>
 * order allow,deny
 * deny from all
 */
class WAG
{
    // --- Styling ----------------------------------------
    public $FG_COLOR = '#d0d0d0';

    public $BG_COLOR = '#808080';

    public $THUMB_FG_COLOR = '#000000';

    public $THUMB_BG_COLOR = '#ffffff';

    public $THUMB_SIZE = 125;

    public $ICON_SIZE = 20;

    public $IMG_MIN_SIZE = 200;

    public $IMG_MAX_SIZE = 1024;

    protected $CSS = '';

    protected function init_css()
    {
        $this->CSS = '
    .wagContainer {
        margin: 0px;
        padding: 0px;
        color: ' . $this->FG_COLOR . ';
        background-color: ' . $this->BG_COLOR . ';
        font-family: sans-serif;
    }
    .wagContainer a img {
        border: none;
    }
    .wagErrorMsg {
        color: #ff' . substr($this->BG_COLOR, 3) . ';
        font-weight: bold;
        margin: 0.5ex;
    }
    .wagMsg {
        font-weight: bold;
        margin-top: 0.5ex;
        margin-bottom: 0.5ex;
    }
    .wagLink {
        display: inline-block;
        vertical-align: top;
        padding: 1px 3px;
        color: ' . $this->FG_COLOR . ';
        background-color: ' . $this->BG_COLOR . ';
        border: 1px solid ' . $this->FG_COLOR . ';
        border-radius: 3px;
        text-decoration: none;
        cursor: pointer;
    }
    .wagLink:hover {
        color: ' . $this->BG_COLOR . ';
        background-color: ' . $this->FG_COLOR . ';
    }
    .wagSlabAlbum, .wagSlabPhoto {
        display: inline-block;
		text-align: center;
		vertical-align: top;
        width: ' . ($this->THUMB_SIZE + 8) . 'px;
        margin: 2px;
        padding: 0px;
        font-size: small;
    }
    .wagSlabLink {
        margin: 0px;
        padding: 3px;
    }
    .wagSlabThumbnail {
        display: block;
        width: ' . $this->THUMB_SIZE . 'px;
        height: ' . $this->THUMB_SIZE . 'px;
        margin: 0px;
        border: 0px;
    }
    .wagLinkThumb, .wagEmptyLinkThumb {
        vertical-align: bottom;
    }
    .wagFlipLink {
        min-width: 9ex;
    }
    .wagEmptyLinkThumb {
        height: ' . floor($this->ICON_SIZE / 2) . 'px;
        border-top: 1px dotted ' . $this->FG_COLOR . ';
    }
    .wagItemAction {
        display: inline-block;
        text-align: center;
        vertical-align: bottom;
    }
    .wagItemCaptionDummy {
        display: inline-block;
        height: ' . ($this->ICON_SIZE + 4) . 'px;
        vertical-align: bottom;
    }
    #wagBreadcrumbs {
        padding: 0.5ex;
        font-size: small;
    }
    #wagAuth {
        margin: 0.5ex;
    }
    #wagItemHeader {
        overflow: hidden;
        margin-bottom: 0.25ex;
        padding: 0.25ex 0.5ex;
        border-bottom: 1px solid ' . $this->FG_COLOR . ';
    }
    #wagItemCaption {
        font-weight: bold;
    }
    #wagItemPosition {
        display: inline-block;
        margin: 0ex 1ex;
        vertical-align: bottom;
        font-size: small;
    }
    #wagItemActions {
        display: inline-block;
        float: right;
    }
    #wagSlabs {
        padding: 1ex;
    }
    #wagItemContainer {
        position: relative;
        text-align: center;
    }
    #wagActionInfo {
        font-family: serif;
        font-weight: bold;
        min-width: ' . $this->ICON_SIZE . 'px;
    }
    #wagInfoBox {
        display: none;
        position: absolute;
        top: 0px;
        right: 0px;
        padding: 1em;
        background-color: #000000;
        background-color: rgba(0, 0, 0, .8);
        border: 1px solid ' . $this->FG_COLOR . ';
    }
    #wagCopyright {
        font-size: small;
    }
    #wagInfoList {
        display: inline-block;
        text-align: left;
        vertical-align: top;
        margin-right: 2em;
    }
    #wagInfoList table, #wagInfoList tr, #wagInfoList td {
        text-align: left;
        border: none;
    }
    #wagInfoList .wagInfoRowHead {
        text-align: right;
        padding-right: 2ex; 
        font-weight: bold;
    }
    #wagInfoTable {
        margin-top: 0.5em;
    }
    #wagInfoGeo {
        display: inline-block;
        margin: 0.5ex;
        vertical-align: top;
    }
    #wagInfoClose {
        float: right;
        margin-left: 2em;
    }
';
    }
    // --- JavaScript ----------------------------------------
    protected $JS = '';

    protected function init_js()
    {
        $sshw_js = '';
        if ($this->action == 'sshw' && $this->item_next_slide !== NULL) {
            $act = 'sshw';
            if ($this->item_next === NULL) {
                $act = 'show';
            }
            $sshw_js = '
            var next_slide = "' . $this->script . '?act=' . $act . $this->url_arg_target($this->item_next_slide) . $this->url_arg_key() . '"; 
            var timeout = setTimeout(function(){ location.href = next_slide; }, 5000);
            var video = document.getElementById("wagVideo");
            if(video !== null && typeof(video) !== "undefined") {
                video.addEventListener("ended", function(){ location.href = next_slide; });
                video.addEventListener("error", function(){ location.href = next_slide; });
                video.play();
                clearTimeout(timeout);
            }
';
        }
        $this->JS = '
        <!--
        var wag_original_onload = window.onload;
        var wag_original_onresize = window.onresize;

        function wag_init() {
            if(typeof wag_original_onload === "function") {
                wag_original_onload();
            }
            wag_resize_img();' . $sshw_js . '
        }

        function wag_toggle_info_box(visible) {
            var info_box = document.getElementById("wagInfoBox");
            if(info_box) {
                if(visible) {
                    info_box.style.display = "block";
                } else {
                    info_box.style.display = "none";
                }
            }
        }

        function wag_window_size() {
            // idea courtesy of Mark "Tarquin" Wilton-Jones
            if(typeof(window.innerWidth) === "number") {
                return {width: window.innerWidth, height: window.innerHeight};
            } else if(document.documentElement && (document.documentElement.clientWidth || document.documentElement.clientHeight)) {
                // IE 6+
                return {width: document.documentElement.clientWidth, height: document.documentElement.clientHeight};
            } else if(document.body && (document.body.clientWidth || document.body.clientHeight)) {
                // IE 4
                return {width: document.body.clientWidth, height: document.body.clientHeight};
            } else {
                return {width: 100, height: 100};
            }
        }

        function wag_img_size(img) {
            // idea courtesy of Jack Moore
            if(typeof(img.naturalHeight) === "number" ) {
                return {width: img.naturalWidth, height: img.naturalHeight};
            } else {
                //IE 8-
                var dummy = new Image();
                dummy.src = img.src;
                return {width: dummy.width, height: dummy.height};
            }
        }

        function wag_resize_img() {
            if(typeof wag_original_onresize === "function") {
                wag_original_onresize();
            }
            var img = document.getElementById("wagPhoto");
            if(img !== null && typeof(img) !== "undefined") {
                var img_bounds = img.getBoundingClientRect();
                var img_size = wag_img_size(img);
                var img_props = img_size.width / img_size.height;
                var avail_size = wag_window_size();
                avail_size.width = Math.max(' . $this->IMG_MIN_SIZE . ', Math.min(avail_size.width, avail_size.width - img_bounds.left - 5));
                avail_size.height = Math.max(' . $this->IMG_MIN_SIZE . ', Math.min(avail_size.height, avail_size.height - img_bounds.top - 5));
                var avail_props = avail_size.width / avail_size.height;
                var final_width, final_height;
                if(img_props > avail_props) {
                    if(img_size.width <= avail_size.width) {
                        final_width = img_size.width;
                        final_height = img_size.height;
                    } else {
                        final_width = Math.round(avail_size.width);
                        final_height = Math.round(avail_size.width / img_props);
                    }
                } else {
                    if(img_size.height <= avail_size.height) {
                        final_width = img_size.width;
                        final_height = img_size.height;
                    } else {
                        final_width = Math.round(img_props * avail_size.height);
                        final_height = Math.round(avail_size.height);
                    }
                }
                img.style.width = "" + final_width + "px";
                img.style.height = "" + final_height + "px";
            }
        }

        window.onload = wag_init;
        window.onresize = wag_resize_img;
        // -->
';
    }
    // --- Localization ----------------------------------------
    static $LBL_AUTH_TITLE = 'Please authenticate';

    static $LBL_AUTH_MSG = 'This content is protected. Please authenticate.';

    static $LBL_AUTH_KEY = 'Password';

    static $LBL_AUTH_GO = 'Go';

    static $LBL_ERROR_TITLE = 'Error';

    static $LBL_ERROR_AUTH = 'Incorrect password.';

    static $LBL_ERROR_DISPLAY = 'Cannot display item.';

    static $LBL_ERROR_INVALID = 'Internal error.';

    static $LBL_ERROR_VIDEO = 'This browser does not support the playback of HTML5 videos.';

    static $LBL_EMPTY = 'This album is empty.';

    static $LBL_ACT_PREV = 'Previous';

    static $LBL_ACT_NEXT = 'Next';

    static $LBL_ACT_CLOSE = 'Close';

    static $LBL_ACT_INFO = 'Info';

    static $LBL_ACT_SSHW = 'Slideshow';

    static $LBL_DOWNLOAD_VIDEO = 'Click here to download the video.';

    static $LBL_INFO_DATE = 'Date';

    static $LBL_INFO_SHUTTER = 'Shutter speed';

    static $LBL_INFO_APERTURE = 'f-stop';

    static $LBL_INFO_ISO = 'ISO';

    static $LBL_INFO_ZOOM = 'Focal length (35mm)';

    static $LBL_INFO_LOCATION = 'Location';
    
    // --- API ----------------------------------------
    /*
     * The script can be run from another PHP script which gives the opportunity to
     * embed the content in another web page.
     * 
     * To use the script, add the following lines to the calling script:
     *  include 'wag.php';
     *  $wag->run();
     *  
     * After executing $wag->run(), the $wag instance will have the $output_... variables
     * set with the relevant content (see the comments of the $output_... variables).
     * This information can then be embeded in the output of the calling script.
     * 
     * The HTML content from $wag->output_content has to be placed in a component
     * with the CSS class "wagContainer". Due to the way image scaling works, any content
     * at the bottom of an image or to the right of an image will be pushed off-screen.
     * 
     * The following is an example of a simple calling script:
     * 
     *  <?php
     *  include 'wag.php';
     *  $wag->run();
     *  
     *  header('Content-type: text/html; charset=UTF-8');
     *  echo <<<HTML
     *  <!DOCTYPE html>
     *  <html>
     *  <head>
     *  <title>{$wag->output_title}</title>
     *  	<style type="text/css">{$wag->output_css}</style>
     *  	<script type="text/javascript">{$wag->output_js}</script>
     *  </head>
     *  <body>
     *      My own site.
     *      <div class="wagContainer">
     *          {$wag->output_content}
     *      </div>
     *  </body>
     *  </html>
     *  HTML;
     *  ?>
     * 
     * By default, the script will not delegate the output of images, videos, thumbnails
     * and icons to the calling script. Only HTML output will be delegated. In special
     * circumstances, when the calling script needs to have full control of the output,
     * set the $delegate_output_types variable accordingly before calling $wag->run():
     *  $wag->delegate_output_types = WAG::TYPE_HTML | WAG::TYPE_IMAGE | WAG::TYPE_FILE;
     * In such cases, the calling script has to check what is the output type before
     * handling the $wag->output_content variable; e.g., the variable may contain HTML
     * code or a GD handle or a file name.
     */
    
    /**
     * Version of this script.
     */
    const VERSION = "1.2";

    /**
     * Output content is a string containing HTML code.
     */
    const TYPE_HTML = 1;

    /**
     * Output content is a GD image handle (which has to be released after use).
     */
    const TYPE_IMAGE = 2;

    /**
     * Output content is a string containing the name of the file with the actual content.
     */
    const TYPE_FILE = 4;

    /**
     * Specifies what is in $output_content (one of TYPE_HTML, TYPE_IMAGE, TYPE_FILE).
     */
    public $output_type = self::TYPE_HTML;

    /**
     * The MIME type of the output.
     */
    public $output_mime_type = '';

    /**
     * The title of the output page (if TYPE_HTML).
     */
    public $output_title = '';

    /**
     * An array with the breadcrumbs of the output page (if TYPE_HTML).
     *
     * The array has the following form:
     * array(array('name' => root_caption, 'url' => root_url), array('name' => subalbum_caption, 'url' => subalbum_url), ...)
     */
    public $output_breadcurmbs = array();

    /**
     * The output content (of the type given in $output_type).
     */
    public $output_content = '';

    /**
     * The CSS for the output page (if TYPE_HTML).
     */
    public $output_css = '';

    /**
     * The JavaScript for the output page (if TYPE_HTML).
     */
    public $output_js = '';

    /**
     * The output types which will be handled by the caller script (OR-ed values of TYPE_...).
     */
    public $delegate_output_types = self::TYPE_HTML;

    /**
     * If not empty, will be used as the caption of the root album.
     */
    public $root_album_caption = '';

    /**
     * Executes this script and populates the $output_...
     * variables.
     *
     * The output types which will not be handled by the caller script
     * will be written to the HTTP response directly.
     */
    public function run()
    {
        if ($this->action == 'show' || $this->action == 'sshw') {
            $this->show();
        } else 
            if ($this->action == 'thmb') {
                $this->thumb();
            } else 
                if ($this->action == 'rndr') {
                    $this->render();
                } else 
                    if ($this->action == 'inxt' || $this->action == 'iprv' || $this->action == 'icls') {
                        $this->icon();
                    } else {
                        $this->show_error(self::$LBL_ERROR_INVALID);
                    }
        if (! ($this->output_type & $this->delegate_output_types)) {
            $this->output();
            exit(0);
        }
    }
    // --- Private code ----------------------------------------
    const TARGET_UNKNOWN = 0;

    const TARGET_PHOTO = 1;

    const TARGET_VIDEO = 2;

    const TARGET_ALBUM = 4;

    protected static $PHOTO_EXT = array(
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif'
    );

    protected static $VIDEO_EXT = array(
        'webm' => 'video/webm',
        'mp4' => 'video/mp4',
        'mpeg4' => 'video/mp4',
        'm4v' => 'video/mp4'
    );

    protected static $IMG_MAX_AGE = 3600;

    protected $script = NULL;

    public $is_managed = FALSE;

    protected $action = NULL;

    protected $target = NULL;

    protected $key = NULL;

    protected $has_access = FALSE;

    protected $target_type = self::TARGET_UNKNOWN;

    protected $item_caption = "";

    protected $items_in_album = array();

    protected $video_sets_in_album = array();

    protected $albums_in_album = array();

    protected $item_w = 0;

    protected $item_h = 0;

    protected $item_idx = 0;

    protected $item_prev = NULL;

    protected $item_next = NULL;

    protected $item_next_slide = NULL;

    protected $item_copyright = '';

    protected $item_date = '';

    protected $item_latitude = '';

    protected $item_longitude = '';

    protected $item_shutter = '';

    protected $item_aperture = '';

    protected $item_ISO = '';

    protected $item_zoom = '';

    public function __construct()
    {
        $this->script = basename($_SERVER['PHP_SELF']);
        $this->is_managed = basename(__FILE__) != $this->script;
        $this->process_passkey();
        $this->parse_params();
        $this->has_access = $this->is_valid_key();
    }

    protected static function mangle_key($key)
    {
        return base64_encode(strrev($key) . '0');
    }

    protected static function unmangle_key($key)
    {
        $s = base64_decode($key);
        return strrev(substr($s, 0, max(strlen($s) - 1, 0)));
    }

    protected function process_passkey()
    {
        if (isset($_GET['passkey'])) {
            $params = $_GET;
            unset($params['passkey']);
            $params['key'] = self::mangle_key($_GET['passkey']);
            header("Location: {$this->script}?" . http_build_query($params));
            exit(0);
        }
    }

    protected function parse_params()
    {
        if (isset($_GET['key'])) {
            $this->key = self::unmangle_key($_GET['key']);
        }
        if (isset($_GET['trg'])) {
            $this->target = self::decode_target($_GET['trg']);
        } else {
            $this->target = self::decode_target('');
        }
        if (isset($_GET['act'])) {
            $this->action = $_GET['act'];
        } else {
            $this->action = 'show';
        }
    }

    protected function is_valid_key()
    {
        $dir = $this->target;
        while ($dir && $dir != dirname($dir) && ! is_dir($dir)) {
            $dir = dirname($dir);
        }
        $key_file = NULL;
        while (! $key_file && $dir) {
            if (file_exists($dir . '/password.txt')) {
                $key_file = $dir . '/password.txt';
            } else {
                if ($dir != dirname($dir)) {
                    $dir = dirname($dir);
                } else {
                    $dir = NULL;
                }
            }
        }
        if (! $key_file) {
            return TRUE;
        } else {
            $keys = file($key_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (count($keys) < 1) {
                return TRUE;
            } else {
                return in_array($this->key, $keys);
            }
        }
    }

    protected static function decode_target($target)
    {
        $target = str_replace('\\', '/', $target);
        $target = str_replace('\"', '', $target);
        $partsIn = explode('/', $target);
        $partsOut = array();
        foreach ($partsIn as $part) {
            if ($part != '' && $part != '.' && $part != '..') {
                array_push($partsOut, $part);
            }
        }
        $parsed_target = '.' . '/' . implode('/', $partsOut);
        if ($parsed_target == './') {
            $parsed_target = '.';
        }
        if (file_exists($parsed_target)) {
            return $parsed_target;
        } else {
            return NULL;
        }
    }

    protected static function encode_target($target)
    {
        if ($target == NULL || $target == '.') {
            return "";
        }
        if ($target[0] == '.' && $target[1] == '/') {
            $target = substr($target, 2);
        }
        return $target;
    }

    protected function url_arg_target($target)
    {
        $encoded = self::encode_target($target);
        if ($encoded == '') {
            return '';
        } else {
            return '&trg=' . urlencode($encoded);
        }
    }

    protected function url_arg_key()
    {
        if ($this->key === NULL) {
            return '';
        } else {
            return '&key=' . self::mangle_key($this->key);
        }
    }
    
    // deal with PHP pathinfo bug with non-Latin symbols (thanks to Pietro Baricco)
    protected static function get_file_name($file, $with_ext = FALSE)
    {
        preg_match('%^(.*?)[\\\\/]*(([^/\\\\]*?)((\.)([^\.\\\\/]*?)|))[\\\\/]*$%im', $file, $matches);
        if ($with_ext) {
            return $matches[2];
        } else {
            return $matches[3];
        }
    }

    protected static function is_photo($file)
    {
        if (! is_file($file)) {
            return FALSE;
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (array_key_exists($ext, self::$PHOTO_EXT)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    protected static function is_video($file)
    {
        if (! is_file($file)) {
            return FALSE;
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (array_key_exists($ext, self::$VIDEO_EXT)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    protected static function get_video_type($video)
    {
        $ext = strtolower(pathinfo($video, PATHINFO_EXTENSION));
        return self::$VIDEO_EXT[$ext];
    }

    protected static function is_video_set($files)
    {
        $has_video = FALSE;
        foreach ($files as $file) {
            if (self::is_video($file)) {
                $has_video = TRUE;
                break;
            }
        }
        return $has_video;
    }

    protected function get_video_set($item)
    {
        foreach ($this->video_sets_in_album as $video_set) {
            if (in_array($item, $video_set)) {
                return $video_set;
            }
        }
        return NULL;
    }

    protected static function get_video_rep($video_set)
    {
        $rep = NULL;
        foreach ($video_set as $video) {
            if (self::is_photo($video)) {
                $rep = $video;
                break;
            }
        }
        if ($rep === NULL) {
            foreach ($video_set as $video) {
                if (self::is_video($video)) {
                    $rep = $video;
                    break;
                }
            }
        }
        return $rep;
    }

    protected function get_album_caption($dir)
    {
        $caption = self::get_file_name($dir, TRUE);
        if ($caption == '.') {
            if ($this->root_album_caption === FALSE || $this->root_album_caption === NULL || trim($this->root_album_caption) === '') {
                $caption = self::get_file_name(getcwd(), TRUE);
            } else {
                $caption = $this->root_album_caption;
            }
        }
        return $caption;
    }

    protected static function calculate_exif_gps_number($n)
    {
        $div = strpos($n, '/');
        if ($div === FALSE) {
            return floatval($n);
        } else {
            return intval(substr($n, 0, $div)) / intval(substr($n, $div + 1));
        }
    }

    protected static function calculate_exif_shutter($n)
    {
        $div = strpos($n, '/');
        if ($div === FALSE) {
            return floatval($n);
        } else {
            return "1/" . (intval(substr($n, $div + 1)) / intval(substr($n, 0, $div)));
        }
    }

    protected static function roundedrectangle($img, $x1, $y1, $x2, $y2, $r, $color, $thickness = 0, $fill_color = NULL)
    {
        imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledarc($img, $x1 + $r, $y1 + $r, 2 * $r, 2 * $r, 180, 270, $color, FALSE);
        imagefilledarc($img, $x2 - $r, $y1 + $r, 2 * $r, 2 * $r, 270, 360, $color, FALSE);
        imagefilledarc($img, $x1 + $r, $y2 - $r, 2 * $r, 2 * $r, 90, 180, $color, FALSE);
        imagefilledarc($img, $x2 - $r, $y2 - $r, 2 * $r, 2 * $r, 0, 90, $color, FALSE);
        if ($thickness > 0) {
            imagefilledrectangle($img, $x1 + $thickness, $y1 + $r + $thickness, $x2 - $thickness, $y2 - $r - $thickness, $fill_color);
            imagefilledrectangle($img, $x1 + $r + $thickness, $y1 + $thickness, $x2 - $r - $thickness, $y2 - $thickness, $fill_color);
            imagefilledarc($img, $x1 + $r + $thickness, $y1 + $r + $thickness, 2 * $r, 2 * $r, 180, 270, $fill_color, FALSE);
            imagefilledarc($img, $x2 - $r - $thickness, $y1 + $r + $thickness, 2 * $r, 2 * $r, 270, 360, $fill_color, FALSE);
            imagefilledarc($img, $x1 + $r + $thickness, $y2 - $r - $thickness, 2 * $r, 2 * $r, 90, 180, $fill_color, FALSE);
            imagefilledarc($img, $x2 - $r - $thickness, $y2 - $r - $thickness, 2 * $r, 2 * $r, 0, 90, $fill_color, FALSE);
        }
    }

    protected static function rotate_image($file, $img)
    {
        if (extension_loaded('exif')) {
            $exif = @exif_read_data($file, 'IFD0');
            if ($exif) {
                $orient = $exif['Orientation'];
                switch ($orient) {
                    case 3:
                        $img_rotated = imagerotate($img, 180, 0);
                        imagedestroy($img);
                        $img = $img_rotated;
                        break;
                    case 6:
                        $img_rotated = imagerotate($img, - 90, 0);
                        imagedestroy($img);
                        $img = $img_rotated;
                        break;
                    case 8:
                        $img_rotated = imagerotate($img, 90, 0);
                        imagedestroy($img);
                        $img = $img_rotated;
                        break;
                }
            }
        }
        return $img;
    }

    protected static function load_image($file)
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $img;
        if ($ext == 'png') {
            $img = imagecreatefrompng($file);
        } else 
            if ($ext == 'gif') {
                $img = imagecreatefromgif($file);
            } else {
                $img = imagecreatefromjpeg($file);
            }
        return self::rotate_image($file, $img);
    }

    protected static function scale_photo($file, $max_size)
    {
        $original_img = self::load_image($file);
        $original_w = imagesx($original_img);
        $original_h = imagesy($original_img);
        $scale = $max_size / max($original_w, $original_h);
        if ($scale >= 1) {
            return $original_img;
        }
        $scl_img = imagecreatetruecolor($original_w * $scale, $original_h * $scale);
        imagecopyresized($scl_img, $original_img, 0, 0, 0, 0, $original_w * $scale, $original_h * $scale, $original_w, $original_h);
        imagedestroy($original_img);
        return $scl_img;
    }

    protected function build_thumbnail_photo($file, $tn_size)
    {
        $original_img = self::load_image($file);
        $original_w = imagesx($original_img);
        $original_h = imagesy($original_img);
        $original_dim = min($original_w, $original_h);
        $original_dx = ($original_w - $original_dim) / 2;
        $original_dy = ($original_h - $original_dim) / 2;
        $tn_img = imagecreatetruecolor($tn_size, $tn_size);
        imagecopyresized($tn_img, $original_img, 0, 0, $original_dx, $original_dy, $tn_size, $tn_size, $original_dim, $original_dim);
        imagedestroy($original_img);
        return $tn_img;
    }

    protected function build_thumbnail_video($file, $tn_size)
    {
        $tn_img;
        $fg_color;
        $bg_color;
        if (self::is_photo($file)) {
            $tn_img = $this->build_thumbnail_photo($file, $tn_size);
            $fg_color = imagecolorallocate($tn_img, intval(substr($this->THUMB_FG_COLOR, 1, 2), 16), intval(substr($this->THUMB_FG_COLOR, 3, 2), 16), intval(substr($this->THUMB_FG_COLOR, 5, 2), 16));
            $bg_color = imagecolorallocate($tn_img, intval(substr($this->THUMB_BG_COLOR, 1, 2), 16), intval(substr($this->THUMB_BG_COLOR, 3, 2), 16), intval(substr($this->THUMB_BG_COLOR, 5, 2), 16));
        } else {
            $tn_img = imagecreatetruecolor($tn_size, $tn_size);
            $fg_color = imagecolorallocate($tn_img, intval(substr($this->THUMB_FG_COLOR, 1, 2), 16), intval(substr($this->THUMB_FG_COLOR, 3, 2), 16), intval(substr($this->THUMB_FG_COLOR, 5, 2), 16));
            $bg_color = imagecolorallocate($tn_img, intval(substr($this->THUMB_BG_COLOR, 1, 2), 16), intval(substr($this->THUMB_BG_COLOR, 3, 2), 16), intval(substr($this->THUMB_BG_COLOR, 5, 2), 16));
            imagefill($tn_img, 0, 0, $bg_color);
            $thickness = max($tn_size / 20, 1);
            $v = array(
                $tn_size / 3,
                $tn_size / 3,
                $tn_size / 3,
                2 * $tn_size / 3,
                2 * $tn_size / 3,
                $tn_size / 2
            );
            imagefilledpolygon($tn_img, $v, 3, $fg_color);
        }
        $strip_width = ceil($tn_size / 8);
        imagefilledrectangle($tn_img, 0, 0, $tn_size, $strip_width, $fg_color);
        imagefilledrectangle($tn_img, 0, $tn_size - $strip_width, $tn_size, $tn_size, $fg_color);
        $box_size = max(min(floor($strip_width / 2), $strip_width - 2), 0);
        $total_boxes = floor($tn_size / (2 * $box_size));
        $box_spacing = floor(($tn_size - $total_boxes * $box_size) / ($total_boxes + 1));
        $lead_space = floor(($tn_size - $box_spacing - $total_boxes * ($box_size + $box_spacing)) / 2);
        $box_marginy = ceil(($strip_width - $box_size) / 2);
        foreach (range(0, $total_boxes - 1) as $i) {
            self::roundedrectangle($tn_img, $lead_space + $box_spacing + $i * ($box_size + $box_spacing), $box_marginy, $lead_space + ($i + 1) * ($box_size + $box_spacing), $strip_width - $box_marginy, floor($box_size / 8), $bg_color);
            self::roundedrectangle($tn_img, $lead_space + $box_spacing + $i * ($box_size + $box_spacing), $tn_size - $strip_width + $box_marginy, $lead_space + ($i + 1) * ($box_size + $box_spacing), $tn_size - $box_marginy, floor($box_size / 8), $bg_color);
        }
        return $tn_img;
    }

    protected function build_thumbnail_subalbums($tn_size)
    {
        $tn_img = imagecreatetruecolor($tn_size, $tn_size);
        $fg_color = imagecolorallocate($tn_img, intval(substr($this->THUMB_FG_COLOR, 1, 2), 16), intval(substr($this->THUMB_FG_COLOR, 3, 2), 16), intval(substr($this->THUMB_FG_COLOR, 5, 2), 16));
        $bg_color = imagecolorallocate($tn_img, intval(substr($this->THUMB_BG_COLOR, 1, 2), 16), intval(substr($this->THUMB_BG_COLOR, 3, 2), 16), intval(substr($this->THUMB_BG_COLOR, 5, 2), 16));
        imagefill($tn_img, 0, 0, $bg_color);
        $thickness = max($tn_size / 40, 1);
        imagesetthickness($tn_img, $thickness);
        $y_squeeze = $tn_size / 10;
        $offset = 2 * $thickness;
        self::roundedrectangle($tn_img, 0 + $offset, $tn_size / 10 + $y_squeeze + $offset, $tn_size - 3 * $thickness + $offset, $tn_size - 3 * $thickness - $y_squeeze / 2 + $offset, 1, $fg_color, $thickness, $bg_color);
        $offset = 0;
        self::roundedrectangle($tn_img, 0 + $offset, $tn_size / 10 + $y_squeeze + $offset, $tn_size - 3 * $thickness + $offset, $tn_size - 3 * $thickness - $y_squeeze / 2 + $offset, 1, $fg_color, $thickness, $bg_color);
        self::roundedrectangle($tn_img, $tn_size / 10 + $offset, 0 + $y_squeeze + $offset, 4 * $tn_size / 10 + $offset, $tn_size / 10 + $y_squeeze + $offset, 1, $fg_color, $thickness, $bg_color);
        imagefilledrectangle($tn_img, $tn_size / 10 + $offset + $thickness, $tn_size / 10 + $y_squeeze + $offset - $thickness, 4 * $tn_size / 10 + $offset - $thickness, $tn_size / 10 + $y_squeeze + $offset + $thickness, $bg_color);
        return $tn_img;
    }

    protected function build_thumbnail_locked($tn_size)
    {
        $tn_img = imagecreatetruecolor($tn_size, $tn_size);
        $fg_color = imagecolorallocate($tn_img, intval(substr($this->THUMB_FG_COLOR, 1, 2), 16), intval(substr($this->THUMB_FG_COLOR, 3, 2), 16), intval(substr($this->THUMB_FG_COLOR, 5, 2), 16));
        $bg_color = imagecolorallocate($tn_img, intval(substr($this->THUMB_BG_COLOR, 1, 2), 16), intval(substr($this->THUMB_BG_COLOR, 3, 2), 16), intval(substr($this->THUMB_BG_COLOR, 5, 2), 16));
        imagefill($tn_img, 0, 0, $bg_color);
        $thickness = max($tn_size / 20, 1);
        imagesetthickness($tn_img, $thickness);
        imagefilledellipse($tn_img, (5 * $tn_size) / 16, $tn_size / 2, $tn_size / 8, $tn_size / 3.5, $fg_color);
        imagefilledellipse($tn_img, (5 * $tn_size) / 16, $tn_size / 2, ($tn_size / 8) - (2 * $thickness), ($tn_size / 3.5) - (2 * $thickness), $bg_color);
        imageline($tn_img, (3 * $tn_size) / 8, $tn_size / 2, (3 * $tn_size) / 4, $tn_size / 2, $fg_color);
        imageline($tn_img, (5 * $tn_size) / 8, ($tn_size / 2) + $thickness, ((3 * $tn_size) / 4) - $thickness, ($tn_size / 2) + $thickness, $fg_color);
        imageline($tn_img, (5 * $tn_size) / 8, ($tn_size / 2), (5 * $tn_size) / 8, ($tn_size / 2) + (2.5 * $thickness), $fg_color);
        imageline($tn_img, ((3 * $tn_size) / 4) - $thickness, ($tn_size / 2), ((3 * $tn_size) / 4) - $thickness, ($tn_size / 2) + (2.5 * $thickness), $fg_color);
        return $tn_img;
    }

    protected function build_thumbnail_unknown($tn_size)
    {
        $img = imagecreatetruecolor($tn_size, $tn_size);
        $fg_color = imagecolorallocate($img, intval(substr($this->THUMB_FG_COLOR, 1, 2), 16), intval(substr($this->THUMB_FG_COLOR, 3, 2), 16), intval(substr($this->THUMB_FG_COLOR, 5, 2), 16));
        $bg_color = imagecolorallocate($img, intval(substr($this->THUMB_BG_COLOR, 1, 2), 16), intval(substr($this->THUMB_BG_COLOR, 3, 2), 16), intval(substr($this->THUMB_BG_COLOR, 5, 2), 16));
        imagefill($img, 0, 0, $bg_color);
        $dx = ($tn_size - imagefontwidth(5)) / 2;
        $dy = ($tn_size - imagefontheight(5)) / 2;
        $scale = (imagefontheight(5) / $tn_size) * 2;
        imagestring($img, 5, $dx, $dy, '?', $fg_color);
        $tn_img = imagecreatetruecolor($tn_size, $tn_size);
        imagecopyresized($tn_img, $img, 0, 0, ($tn_size - $tn_size * $scale) / 2, ($tn_size - $tn_size * $scale) / 2, $tn_size, $tn_size, $tn_size * $scale, $tn_size * $scale);
        imagedestroy($img);
        return $tn_img;
    }

    protected function load_album_items($album)
    {
        $file_map = array();
        foreach (scandir($album) as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            $path = $album . '/' . $file;
            if (is_dir($path)) {
                array_push($this->albums_in_album, $path);
            } else {
                $file_no_ext = self::get_file_name($file);
                if (! array_key_exists($file_no_ext, $file_map)) {
                    $file_map[$file_no_ext] = array();
                }
                array_push($file_map[$file_no_ext], $path);
            }
        }
        foreach (array_keys($file_map) as $file_name) {
            $files = $file_map[$file_name];
            if (! self::is_video_set($files)) {
                foreach ($files as $file) {
                    if (self::is_photo($file)) {
                        array_push($this->items_in_album, $file);
                    }
                }
            } else {
                $video_set = array();
                foreach ($files as $file) {
                    if (self::is_video($file) || self::is_photo($file)) {
                        array_push($video_set, $file);
                    }
                }
                array_push($this->video_sets_in_album, $video_set);
                $video = self::get_video_rep($video_set);
                if ($video !== NULL) {
                    array_push($this->items_in_album, $video);
                }
            }
        }
    }

    protected function load_item_position($item)
    {
        $count = 0;
        $prev_sibling = NULL;
        $collect_next_sibling = FALSE;
        foreach ($this->items_in_album as $sibling) {
            if ($collect_next_sibling) {
                $this->item_next = $sibling;
                $collect_next_sibling = FALSE;
            }
            if ($item === $sibling || (self::is_video($item) && self::get_file_name($item) === self::get_file_name($sibling))) {
                $this->item_idx = $count;
                $this->item_prev = $prev_sibling;
                $collect_next_sibling = TRUE;
            }
            ++ $count;
            $prev_sibling = $sibling;
        }
        if ($this->item_next !== NULL) {
            $this->item_next_slide = $this->item_next;
        } else {
            $this->item_next_slide = dirname($this->target);
        }
    }

    protected function load_breadcrumbs()
    {
        $this->output_breadcurmbs = array();
        $path = $this->target;
        while (dirname($path) != $path) {
            $path = dirname($path);
            $crumb = array(
                'name' => $this->get_album_caption($path),
                'url' => $this->script . '?act=show' . $this->url_arg_target($path) . $this->url_arg_key()
            );
            array_unshift($this->output_breadcurmbs, $crumb);
        }
    }

    protected function load_album_info($album)
    {
        $this->item_caption = $this->get_album_caption($album);
    }

    protected function load_photo_info($photo)
    {
        list ($this->item_w, $this->item_h) = getimagesize($photo, $info);
        if (isset($info["APP13"])) {
            $iptc = iptcparse($info["APP13"]);
            $this->item_caption = $iptc["2#120"][0];
            $this->item_copyright = $iptc["2#116"][0];
        }
        if ($this->item_caption === FALSE || $this->item_caption === NULL || trim($this->item_caption) === '') {
            $this->item_caption = self::get_file_name($photo);
        }
        if (extension_loaded('exif')) {
            $exif = @exif_read_data($photo, 'IFD0');
            if ($exif) {
                $orient = $exif['Orientation'];
                switch ($orient) {
                    case 6:
                    case 8:
                        $this->item_h += $this->item_w;
                        $this->item_w = $this->item_h - $this->item_w;
                        $this->item_h = $this->item_h - $this->item_w;
                        break;
                }
                $this->item_date = $exif['DateTimeOriginal'];
                $lon = $exif['GPSLongitude'];
                if ($lon) {
                    $this->item_longitude = self::calculate_exif_gps_number($lon[0]) + self::calculate_exif_gps_number($lon[1]) / 60 + self::calculate_exif_gps_number($lon[2]) / 3600;
                    if (strtoupper($exif['GPSLongitudeRef']) == 'W') {
                        $this->item_longitude = - $this->item_longitude;
                    }
                }
                $lat = $exif['GPSLatitude'];
                if ($lat) {
                    $this->item_latitude = self::calculate_exif_gps_number($lat[0]) + self::calculate_exif_gps_number($lat[1]) / 60 + self::calculate_exif_gps_number($lat[2]) / 3600;
                    if (strtoupper($exif['GPSLatitudeRef']) == 'S') {
                        $this->item_latitude = - $this->item_latitude;
                    }
                }
                if ($exif['ExposureTime']) {
                    $this->item_shutter = self::calculate_exif_shutter($exif['ExposureTime']);
                }
                if ($exif['FNumber']) {
                    $this->item_aperture = self::calculate_exif_gps_number($exif['FNumber']);
                }
                $this->item_ISO = $exif['ISOSpeedRatings'];
                $this->item_zoom = $exif['FocalLengthIn35mmFilm'];
            }
        }
        if (! $this->item_date) {
            $this->item_date = date("Y-m-d H:i:s", filemtime($photo));
        }
    }

    protected function load_video_info($video_set)
    {
        $rep = self::get_video_rep($video_set);
        if ($rep !== NULL) {
            if (self::is_photo($rep)) {
                $this->load_photo_info($rep);
            } else {
                $this->item_caption = self::get_file_name($rep);
                $this->item_date = date("Y-m-d H:i:s", filemtime($rep));
            }
        }
    }

    protected function load_target_info()
    {
        if (is_dir($this->target)) {
            $this->target_type = self::TARGET_ALBUM;
            $this->load_album_items($this->target);
            $this->load_album_info($this->target);
        } else 
            if (self::is_photo($this->target) || self::is_video($this->target)) {
                $this->load_album_items(dirname($this->target));
                $this->load_item_position($this->target);
                $video_set = $this->get_video_set($this->target);
                if ($video_set !== NULL) {
                    $this->target_type = self::TARGET_VIDEO;
                    $this->load_video_info($video_set);
                } else {
                    $this->target_type = self::TARGET_PHOTO;
                    $this->load_photo_info($this->target);
                }
            } else {
                $this->target_type = self::TARGET_UNKNOWN;
            }
    }

    protected function generate_slab($item, $is_album = FALSE, $caption = NULL)
    {
        $css_class;
        if ($is_album) {
            $css_class = "wagSlabAlbum";
        } else {
            $css_class = "wagSlabPhoto";
        }
        $caption_html = "";
        if ($caption !== NULL) {
            $caption_html = "<br>" . $caption;
        }
        return <<<HTML

        <div class="{$css_class}">
            <a href="{$this->script}?act=show{$this->url_arg_target($item)}{$this->url_arg_key()}" class="wagLink wagSlabLink">
                <img src="{$this->script}?act=thmb{$this->url_arg_target($item)}{$this->url_arg_key()}" class="wagSlabThumbnail" >
            </a>{$caption_html}
        </div>

HTML;
    }

    protected function generate_breadcrumbs_nav()
    {
        if (! count($this->output_breadcurmbs)) {
            return "";
        }
        $content = '
    <div id="wagBreadcrumbs">
';
        $first = TRUE;
        foreach ($this->output_breadcurmbs as $crumb) {
            if (! $first) {
                $content = $content . ' : ';
            } else {
                $first = FALSE;
            }
            $content = $content . '<a href="' . $crumb['url'] . '" class="wagLink">' . $crumb['name'] . '</a>';
        }
        $content = $content . '
    </div>
';
        return $content;
    }

    protected function generate_item_actions()
    {
        $printable_idx = $this->item_idx + 1;
        $total_items = count($this->items_in_album);
        $info_box = '<div class="wagItemAction" ><a href="#" id="wagActionInfo" class="wagLink" onclick="wag_toggle_info_box(true)">i</a></div>';
        $prev_box = '<div class="wagItemAction" >';
        if ($this->item_prev !== NULL) {
            $prev_box = $prev_box . '<a href="' . $this->script . '?act=show' . $this->url_arg_target($this->item_prev) . $this->url_arg_key() . '" class="wagLink wagFlipLink"><img src="' . $this->script . '?act=iprv" alt="' . self::$LBL_ACT_PREV . '" class="wagLinkThumb" > ' . self::$LBL_ACT_PREV . '</a>';
        } else {
            $prev_box = $prev_box . '<div class="wagEmptyLinkThumb wagFlipLink"></div>';
        }
        $prev_box = $prev_box . '</div>';
        $next_box = '<div class="wagItemAction" >';
        if ($this->item_next !== NULL) {
            $next_box = $next_box . '<a href="' . $this->script . '?act=show' . $this->url_arg_target($this->item_next) . $this->url_arg_key() . '" class="wagLink wagFlipLink">' . self::$LBL_ACT_NEXT . ' <img src="' . $this->script . '?act=inxt" alt="' . self::$LBL_ACT_NEXT . '" class="wagLinkThumb" ></a>';
        } else {
            $next_box = $next_box . '<div class="wagEmptyLinkThumb wagFlipLink"></div>';
        }
        $next_box = $next_box . '</div>';
        return '
        <div id="wagItemActions"><div class="wagItemCaptionDummy"></div><div id="wagItemPosition">' . $printable_idx . '/' . $total_items . '</div>
            ' . $info_box . '
            ' . $prev_box . '
            ' . $next_box . '
            <div class="wagItemAction"><a href="' . $this->script . '?act=show' . $this->url_arg_target(dirname($this->target)) . $this->url_arg_key() . '" class="wagLink"><img src="' . $this->script . '?act=icls" alt="' . self::$LBL_ACT_CLOSE . '" title="' . self::$LBL_ACT_CLOSE . '" class="wagLinkThumb" ></a></div>
        </div>
';
    }

    protected function generate_album_actions()
    {
        if (! count($this->items_in_album)) {
            return '';
        }
        $sshw_box = '<div class="wagItemAction" ><a href="' . $this->script . '?act=sshw' . $this->url_arg_target($this->items_in_album[0]) . $this->url_arg_key() . '" class="wagLink">' . self::$LBL_ACT_SSHW . '</a></div>';
        return '
        <div id="wagItemActions">
            ' . $sshw_box . '
        </div>
';
    }

    protected function generate_item_header()
    {
        $printable_idx = $this->item_idx + 1;
        $total_items = count($this->items_in_album);
        return '
    <div id="wagItemHeader">' . $this->generate_item_actions() . '
        <div class="wagItemCaptionDummy"></div><span id="wagItemCaption">' . $this->item_caption . '</span>
    </div>
';
    }

    protected function generate_album_header()
    {
        return '
    <div id="wagItemHeader">' . $this->generate_album_actions() . '
        <div class="wagItemCaptionDummy"></div><span id="wagItemCaption">' . $this->item_caption . '</span>
    </div>
';
    }

    protected function generate_item_info_box()
    {
        $copyright = $this->item_copyright;
        if (strlen($copyright) > 0) {
            if ($copyright[0] == chr(169)) {
                $copyright = "&copy;" . substr($copyright, 1);
            } else {
                $copyright = "&copy; " . $copyright;
            }
        }
        $date = '';
        if ($this->item_date) {
            $date = '<tr><td class="wagInfoRowHead">' . self::$LBL_INFO_DATE . '</td><td>' . $this->item_date . '</td></tr>';
        }
        $shutter = '';
        if ($this->item_shutter !== '' && $this->item_shutter !== NULL) {
            $shutter = '<tr><td class="wagInfoRowHead">' . self::$LBL_INFO_SHUTTER . '</td><td>' . $this->item_shutter . '</td></tr>';
        }
        $aperture = '';
        if ($this->item_aperture) {
            $aperture = '<tr><td class="wagInfoRowHead">' . self::$LBL_INFO_APERTURE . '</td><td>' . $this->item_aperture . '</td></tr>';
        }
        $iso = '';
        if ($this->item_ISO) {
            $iso = '<tr><td class="wagInfoRowHead">' . self::$LBL_INFO_ISO . '</td><td>' . $this->item_ISO . '</td></tr>';
        }
        $zoom = '';
        if ($this->item_zoom) {
            $zoom = '<tr><td class="wagInfoRowHead">' . self::$LBL_INFO_ZOOM . '</td><td>' . $this->item_zoom . '</td></tr>';
        }
        $geo = '';
        if ($this->item_longitude !== '' && $this->item_latitude !== '') {
            $geo = '<a href="http://www.openstreetmap.org/?mlat=' . $this->item_latitude . '&mlon=' . $this->item_longitude . '" class="wagLink wagSlabLink"><img alt="' . self::$LBL_INFO_LOCATION . '" src="http://staticmap.openstreetmap.de/staticmap.php?zoom=8&size=200x200&center=' . $this->item_latitude . ',' . $this->item_longitude . '&markers=' . $this->item_latitude . ',' . $this->item_longitude . ',red-pushpin" class="wagLinkThumb"></a>';
        }
        return '
        <div id="wagInfoBox">
            <div id="wagInfoClose" class="wagItemAction">
                <a href="#" class="wagLink" onclick="wag_toggle_info_box(false)"><img src="' . $this->script . '?act=icls" alt="' . self::$LBL_ACT_CLOSE . '" title="' . self::$LBL_ACT_CLOSE . '" class="wagLinkThumb" ></a>
            </div>
            <div id="wagInfoList">
                <span id="wagCopyright">' . $copyright . '</span><br>
                <table id="wagInfoTable">
                    ' . $date . '
                    ' . $shutter . '
                    ' . $aperture . '
                    ' . $iso . '
                    ' . $zoom . '
                </table>
            </div>
            <div id="wagInfoGeo">
                ' . $geo . '
            </div>
        </div>
';
    }

    protected function show_photo()
    {
        $this->output_type = self::TYPE_HTML;
        $this->output_title = $this->item_caption;
        $this->output_content = $this->generate_item_header() . <<<HTML
    
    <div id="wagItemContainer">
        {$this->generate_item_info_box()}
        <img src="{$this->script}?act=rndr{$this->url_arg_target($this->target)}{$this->url_arg_key()}" id="wagPhoto" >
    </div>
    
HTML;
    }

    protected function show_video()
    {
        $this->output_type = self::TYPE_HTML;
        $this->output_title = $this->item_caption;
        $video_set = $this->get_video_set($this->target);
        $poster = "";
        $rep = self::get_video_rep($video_set);
        if (self::is_photo($rep)) {
            $poster = ' poster="' . $this->script . '?act=rndr' . $this->url_arg_target($rep) . $this->url_arg_key() . '"';
        }
        $this->output_content = $this->generate_item_header() . <<<HTML
    
    <div id="wagItemContainer">
        {$this->generate_item_info_box()}
        <video id="wagVideo" controls="controls" preload="none"{$poster} >
HTML;
        $first_video = NULL;
        foreach ($video_set as $video) {
            if (self::is_video($video)) {
                if ($first_video === NULL) {
                    $first_video = $video;
                }
                $this->output_content = $this->output_content . '<source type="' . self::get_video_type($video) . '" src="' . $this->script . '?act=rndr' . $this->url_arg_target($video) . $this->url_arg_key() . '" >' . "\n";
            }
        }
        $this->output_content = $this->output_content . '
            <div class="wagErrorMsg">' . self::$LBL_ERROR_VIDEO . '</div>
            <a href="' . $this->script . '?act=rndr' . $this->url_arg_target($first_video) . $this->url_arg_key() . '" class="wagLink">' . self::$LBL_DOWNLOAD_VIDEO . '</a>
		</video>
	</div>
';
    }

    protected function show_album()
    {
        $this->output_type = self::TYPE_HTML;
        $this->output_title = $this->item_caption;
        $this->output_content = $this->generate_album_header();
        $this->output_content = $this->output_content . '<div id="wagSlabs">';
        $is_empty = TRUE;
        foreach ($this->albums_in_album as $album) {
            $is_empty = FALSE;
            $caption = $this->get_album_caption($album);
            $this->output_content = $this->output_content . $this->generate_slab($album, TRUE, $caption);
        }
        foreach ($this->items_in_album as $item) {
            $is_empty = FALSE;
            $this->output_content = $this->output_content . $this->generate_slab($item);
        }
        if ($is_empty) {
            $this->output_content = $this->output_content . self::$LBL_EMPTY;
        }
        $this->output_content = $this->output_content . '</div>';
    }

    protected function show_auth_form()
    {
        $this->output_type = self::TYPE_HTML;
        $this->output_title = self::$LBL_AUTH_TITLE;
        $encoded_target = self::encode_target($this->target);
        $err = '';
        if ($this->key !== NULL) {
            $err = '<div class="wagErrorMsg">' . self::$LBL_ERROR_AUTH . '</div>';
        }
        $lbl_msg = self::$LBL_AUTH_MSG;
        $lbl_key = self::$LBL_AUTH_KEY;
        $lbl_go = self::$LBL_AUTH_GO;
        $this->output_content = <<<HTML
    
    <div id="wagAuth">
        {$err}
        <div class="wagMsg">{$lbl_msg}</div>
        <form action="{$this->script}" method="get">
            <input type="hidden" name="act" value="{$this->action}" >
            <input type="hidden" name="trg" value="{$encoded_target}" >
            {$lbl_key}: <input type="password" name="passkey" >
            <input type="submit" value="{$lbl_go}" class="wagLink" >
    </form>
    </div>
    
HTML;
    }

    protected function show_error($msg)
    {
        $this->output_type = self::TYPE_HTML;
        $this->output_title = self::$LBL_ERROR_TITLE;
        $this->output_content = <<<HTML

    <div class="wagErrorMsg">{$msg}</div>

HTML;
    }

    protected function show()
    {
        $this->init_css();
        $this->output_css = $this->CSS;
        if (! $this->has_access) {
            $this->init_js();
            $this->output_js = $this->JS;
            $this->show_auth_form();
            return;
        }
        $this->load_target_info();
        $this->init_js();
        $this->output_js = $this->JS;
        if ($this->target_type == self::TARGET_PHOTO) {
            $this->show_photo();
        } else 
            if ($this->target_type == self::TARGET_VIDEO) {
                $this->show_video();
            } else 
                if ($this->target_type == self::TARGET_ALBUM) {
                    $this->load_breadcrumbs();
                    $this->show_album();
                } else {
                    $this->show_error(self::$LBL_ERROR_DISPLAY);
                }
    }

    protected function thumb_photo()
    {
        return $this->build_thumbnail_photo($this->target, $this->THUMB_SIZE);
    }

    protected function thumb_video()
    {
        return $this->build_thumbnail_video($this->target, $this->THUMB_SIZE);
    }

    protected function thumb_album()
    {
        $tn_img = imagecreatetruecolor($this->THUMB_SIZE, $this->THUMB_SIZE);
        $fg_color = imagecolorallocate($tn_img, intval(substr($this->THUMB_FG_COLOR, 1, 2), 16), intval(substr($this->THUMB_FG_COLOR, 3, 2), 16), intval(substr($this->THUMB_FG_COLOR, 5, 2), 16));
        $bg_color = imagecolorallocate($tn_img, intval(substr($this->THUMB_BG_COLOR, 1, 2), 16), intval(substr($this->THUMB_BG_COLOR, 3, 2), 16), intval(substr($this->THUMB_BG_COLOR, 5, 2), 16));
        imagefill($tn_img, 0, 0, $bg_color);
        $slot_size = $this->THUMB_SIZE / 3;
        $slot_tn_size = $slot_size - 2;
        $tn_idx = 0;
        if (count($this->albums_in_album) > 0) {
            $tn = $this->build_thumbnail_subalbums($slot_tn_size);
            $tn_dx = $tn_idx % 3;
            $tn_dy = (int) ($tn_idx / 3);
            imagecopyresized($tn_img, $tn, ($tn_dx * $slot_size) + 1, ($tn_dy * $slot_size) + 1, 0, 0, $slot_tn_size, $slot_tn_size, $slot_tn_size, $slot_tn_size);
            imagedestroy($tn);
            $tn_idx ++;
        }
        foreach ($this->items_in_album as $item) {
            $tn;
            $video_set = $this->get_video_set($item);
            if ($video_set !== NULL) {
                $tn = $this->build_thumbnail_video($item, $slot_tn_size);
            } else {
                $tn = $this->build_thumbnail_photo($item, $slot_tn_size);
            }
            $tn_dx = $tn_idx % 3;
            $tn_dy = (int) ($tn_idx / 3);
            imagecopyresized($tn_img, $tn, ($tn_dx * $slot_size) + 1, ($tn_dy * $slot_size) + 1, 0, 0, $slot_tn_size, $slot_tn_size, $slot_tn_size, $slot_tn_size);
            imagedestroy($tn);
            $tn_idx ++;
            if ($tn_idx > 8) {
                break;
            }
        }
        if ($tn_idx == 0) {
            $thickness = max($this->THUMB_SIZE / 40, 1);
            imagesetthickness($tn_img, $thickness);
            imageline($tn_img, $this->THUMB_SIZE / 3, $this->THUMB_SIZE / 2, 2 * $this->THUMB_SIZE / 3, $this->THUMB_SIZE / 2, $fg_color);
        }
        return $tn_img;
    }

    protected function thumb()
    {
        if (! $this->has_access) {
            $this->output_type = self::TYPE_IMAGE;
            $this->output_content = $this->build_thumbnail_locked($this->THUMB_SIZE);
            return;
        }
        $this->load_target_info();
        $thumb;
        if ($this->target_type == self::TARGET_PHOTO) {
            $thumb = $this->thumb_photo();
        } else 
            if ($this->target_type == self::TARGET_VIDEO) {
                $thumb = $this->thumb_video();
            } else 
                if ($this->target_type == self::TARGET_ALBUM) {
                    $thumb = $this->thumb_album();
                } else {
                    $thumb = $this->build_thumbnail_unknown($this->THUMB_SIZE);
                }
        $this->output_type = self::TYPE_IMAGE;
        $this->output_content = $thumb;
    }

    protected function render()
    {
        if (! $this->has_access) {
            $this->output_type = self::TYPE_IMAGE;
            $this->output_content = $this->build_thumbnail_locked($this->THUMB_SIZE);
            return;
        }
        if (self::is_photo($this->target)) {
            $this->output_type = self::TYPE_IMAGE;
            $this->output_content = self::scale_photo($this->target, $this->IMG_MAX_SIZE);
        } else {
            if (self::is_video($this->target)) {
                $this->output_type = self::TYPE_FILE;
                $this->output_mime_type = self::get_video_type($this->target);
                $this->output_content = $this->target;
            } else {
                $this->output_type = self::TYPE_IMAGE;
                $this->output_content = $this->build_thumbnail_unknown($this->THUMB_SIZE);
            }
        }
    }

    protected function icon_next()
    {
        $icon = imagecreatetruecolor(floor($this->ICON_SIZE * 0.65), $this->ICON_SIZE);
        $transparent = imagecolorallocatealpha($icon, 0, 0, 0, 127);
        imagefill($icon, 0, 0, $transparent);
        imagesavealpha($icon, TRUE);
        $fg_color = imagecolorallocate($icon, intval(substr($this->BG_COLOR, 1, 2), 16), intval(substr($this->BG_COLOR, 3, 2), 16), intval(substr($this->BG_COLOR, 5, 2), 16));
        $bg_color = imagecolorallocate($icon, intval(substr($this->FG_COLOR, 1, 2), 16), intval(substr($this->FG_COLOR, 3, 2), 16), intval(substr($this->FG_COLOR, 5, 2), 16));
        $offset = floor($this->ICON_SIZE * 0.1);
        imagefilledpolygon($icon, array(
            floor($this->ICON_SIZE / 4) + 2 - $offset,
            floor($this->ICON_SIZE / 4) - 2,
            floor($this->ICON_SIZE / 4) - 2 - $offset,
            floor($this->ICON_SIZE / 4) + 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 2) - 2 + 2 - $offset,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 2) + 2 + 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 2) + 2 + 2 - $offset,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 2) - 2 + 2
        ), 4, $fg_color);
        imagefilledpolygon($icon, array(
            floor($this->ICON_SIZE / 4) - 2 - $offset,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 2,
            floor($this->ICON_SIZE / 4) + 2 - $offset,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) + 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 2) + 2 + 2 - $offset,
            floor($this->ICON_SIZE / 2) + 2 - 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 2) - 2 + 2 - $offset,
            floor($this->ICON_SIZE / 2) - 2 - 2
        ), 4, $fg_color);
        imagefilledpolygon($icon, array(
            floor($this->ICON_SIZE / 4) + 1 + 1 - $offset,
            floor($this->ICON_SIZE / 4) + 1 - 1,
            floor($this->ICON_SIZE / 4) + 1 - 1 - $offset,
            floor($this->ICON_SIZE / 4) + 1 + 1,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 2) - 1 - 1 + 2 - $offset,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 2) - 1 + 1 + 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 2) - 1 + 1 + 2 - $offset,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 2) - 1 - 1 + 2
        ), 4, $bg_color);
        imagefilledpolygon($icon, array(
            floor($this->ICON_SIZE / 4) + 1 - 1 - $offset,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 1 - 1,
            floor($this->ICON_SIZE / 4) + 1 + 1 - $offset,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 1 + 1,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 2) - 1 + 1 + 2 - $offset,
            floor($this->ICON_SIZE / 2) + 1 + 1 - 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 2) - 1 - 1 + 2 - $offset,
            floor($this->ICON_SIZE / 2) + 1 - 1 - 2
        ), 4, $bg_color);
        return $icon;
    }

    protected function icon_prev()
    {
        $icon = imagecreatetruecolor(floor($this->ICON_SIZE * 0.65), $this->ICON_SIZE);
        $transparent = imagecolorallocatealpha($icon, 0, 0, 0, 127);
        imagefill($icon, 0, 0, $transparent);
        imagesavealpha($icon, TRUE);
        $fg_color = imagecolorallocate($icon, intval(substr($this->BG_COLOR, 1, 2), 16), intval(substr($this->BG_COLOR, 3, 2), 16), intval(substr($this->BG_COLOR, 5, 2), 16));
        $bg_color = imagecolorallocate($icon, intval(substr($this->FG_COLOR, 1, 2), 16), intval(substr($this->FG_COLOR, 3, 2), 16), intval(substr($this->FG_COLOR, 5, 2), 16));
        imagefilledpolygon($icon, array(
            floor($this->ICON_SIZE / 2) + 2 - floor($this->ICON_SIZE / 4) - 2,
            floor($this->ICON_SIZE / 2) - 2 - 2,
            floor($this->ICON_SIZE / 2) - 2 - floor($this->ICON_SIZE / 4) - 2,
            floor($this->ICON_SIZE / 2) + 2 - 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 2 - floor($this->ICON_SIZE / 4),
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) + 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) + 2 - floor($this->ICON_SIZE / 4),
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 2
        ), 4, $fg_color);
        imagefilledpolygon($icon, array(
            floor($this->ICON_SIZE / 2) - 2 - floor($this->ICON_SIZE / 4) - 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 2) - 2 + 2,
            floor($this->ICON_SIZE / 2) + 2 - floor($this->ICON_SIZE / 4) - 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 2) + 2 + 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) + 2 - floor($this->ICON_SIZE / 4),
            floor($this->ICON_SIZE / 4) + 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 2 - floor($this->ICON_SIZE / 4),
            floor($this->ICON_SIZE / 4) - 2
        ), 4, $fg_color);
        imagefilledpolygon($icon, array(
            floor($this->ICON_SIZE / 2) + 1 + 1 - floor($this->ICON_SIZE / 4) - 2,
            floor($this->ICON_SIZE / 2) + 1 - 1 - 2,
            floor($this->ICON_SIZE / 2) + 1 - 1 - floor($this->ICON_SIZE / 4) - 2,
            floor($this->ICON_SIZE / 2) + 1 + 1 - 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 1 - 1 - floor($this->ICON_SIZE / 4),
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 1 + 1,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 1 + 1 - floor($this->ICON_SIZE / 4),
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 1 - 1
        ), 4, $bg_color);
        imagefilledpolygon($icon, array(
            floor($this->ICON_SIZE / 2) + 1 - 1 - floor($this->ICON_SIZE / 4) - 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 2) - 1 - 1 + 2,
            floor($this->ICON_SIZE / 2) + 1 + 1 - floor($this->ICON_SIZE / 4) - 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 2) - 1 + 1 + 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 1 + 1 - floor($this->ICON_SIZE / 4),
            floor($this->ICON_SIZE / 4) + 1 + 1,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 1 - 1 - floor($this->ICON_SIZE / 4),
            floor($this->ICON_SIZE / 4) + 1 - 1
        ), 4, $bg_color);
        return $icon;
    }

    protected function icon_close()
    {
        $icon = imagecreatetruecolor($this->ICON_SIZE, $this->ICON_SIZE);
        $transparent = imagecolorallocatealpha($icon, 0, 0, 0, 127);
        imagefill($icon, 0, 0, $transparent);
        imagesavealpha($icon, TRUE);
        $fg_color = imagecolorallocate($icon, intval(substr($this->BG_COLOR, 1, 2), 16), intval(substr($this->BG_COLOR, 3, 2), 16), intval(substr($this->BG_COLOR, 5, 2), 16));
        $bg_color = imagecolorallocate($icon, intval(substr($this->FG_COLOR, 1, 2), 16), intval(substr($this->FG_COLOR, 3, 2), 16), intval(substr($this->FG_COLOR, 5, 2), 16));
        imagefilledpolygon($icon, array(
            floor($this->ICON_SIZE / 4) + 2,
            floor($this->ICON_SIZE / 4) - 2,
            floor($this->ICON_SIZE / 4) - 2,
            floor($this->ICON_SIZE / 4) + 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) + 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) + 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 2
        ), 4, $fg_color);
        imagefilledpolygon($icon, array(
            floor($this->ICON_SIZE / 4) - 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 2,
            floor($this->ICON_SIZE / 4) + 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) + 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) + 2,
            floor($this->ICON_SIZE / 4) + 2,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 2,
            floor($this->ICON_SIZE / 4) - 2
        ), 4, $fg_color);
        imagefilledpolygon($icon, array(
            floor($this->ICON_SIZE / 4) + 1 + 1,
            floor($this->ICON_SIZE / 4) + 1 - 1,
            floor($this->ICON_SIZE / 4) + 1 - 1,
            floor($this->ICON_SIZE / 4) + 1 + 1,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 1 - 1,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 1 + 1,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 1 + 1,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 1 - 1
        ), 4, $bg_color);
        imagefilledpolygon($icon, array(
            floor($this->ICON_SIZE / 4) + 1 - 1,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 1 - 1,
            floor($this->ICON_SIZE / 4) + 1 + 1,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 1 + 1,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 1 + 1,
            floor($this->ICON_SIZE / 4) + 1 + 1,
            $this->ICON_SIZE - floor($this->ICON_SIZE / 4) - 1 - 1,
            floor($this->ICON_SIZE / 4) + 1 - 1
        ), 4, $bg_color);
        return $icon;
    }

    protected function icon()
    {
        $thumb;
        if ($this->action == 'inxt') {
            $thumb = $this->icon_next();
        } else 
            if ($this->action == 'iprv') {
                $thumb = $this->icon_prev();
            } else 
                if ($this->action == 'icls') {
                    $thumb = $this->icon_close();
                } else {
                    $thumb = $this->build_thumbnail_unknown($this->ICON_SIZE);
                }
        $this->output_type = self::TYPE_IMAGE;
        $this->output_mime_type = self::$PHOTO_EXT['png'];
        $this->output_content = $thumb;
    }

    protected function write_html()
    {
        header('Content-type: text/html; charset=UTF-8');
        echo <<<HTML
    
<!DOCTYPE html>
<html style="width: 100%; height: 100%; margin: 0px; padding: 0px;">
<head>
	<title>{$this->output_title}</title>
	<style type="text/css">
{$this->CSS}
	</style>
	<script type="text/javascript">
{$this->JS}
	</script>
</head>
<body class="wagContainer">
{$this->generate_breadcrumbs_nav()}
{$this->output_content}
</body>
</html>
    
HTML;
    }

    protected function write_image()
    {
        header('Content-type: ' . $this->output_mime_type);
        header('Cache-Control: max-age=' . self::$IMG_MAX_AGE);
        header('Content-Disposition: inline; filename="' . urlencode(self::get_file_name($this->target, TRUE)) . '"');
        if ($this->output_mime_type == self::$PHOTO_EXT['png']) {
            imagepng($this->output_content);
        } else 
            if ($this->output_mime_type == self::$PHOTO_EXT['gif']) {
                imagegif($this->output_content);
            } else {
                imagejpeg($this->output_content);
            }
        imagedestroy($this->output_content);
    }

    protected function write_file()
    {
        header('Content-type: ' . self::get_video_type($this->output_content));
        header('Content-Length: ' . filesize($this->output_content));
        header('Cache-Control: max-age=' . self::$IMG_MAX_AGE);
        header('Content-Disposition: inline; filename="' . urlencode(self::get_file_name($this->target, TRUE)) . '"');
        ob_clean();
        flush();
        readfile($this->output_content);
    }

    protected function output()
    {
        if ($this->output_type == self::TYPE_HTML) {
            $this->write_html();
        } else 
            if ($this->output_type == self::TYPE_IMAGE) {
                $this->write_image();
            } else 
                if ($this->output_type == self::TYPE_FILE) {
                    $this->write_file();
                } else {
                    $this->show_error(self::$LBL_ERROR_INVALID);
                    $this->write_html();
                }
    }
}

$wag = new WAG();
if (! $wag->is_managed) {
    $wag->delegate_output_types = 0;
    $wag->run();
}
?>