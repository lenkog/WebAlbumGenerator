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
    // --- Styling and config -----------------------------
    public $FG_COLOR = '#000000';

    public $BG_COLOR = '#f0f0f0';

    public $HILITE_COLOR = '#1eb9a3';

    public $ITEM_FG_COLOR = '#ffffff';

    public $ITEM_BG_COLOR = '#808080';

    public $THUMB_FG_COLOR = '#000000';

    public $THUMB_BG_COLOR = '#ffffff';

    public $THUMB_SIZE = 125;

    public $IMG_MAX_SIZE = 1024;

    public $SLIDESHOW_INTERVAL = 5000;

    public $ITEM_BORDER = 10;

    protected $CSS = '';

    protected function init_css()
    {
        $this->CSS = '<style type="text/css">
    .wagContainer {
        color: ' . $this->FG_COLOR . ';
        background-color: ' . $this->BG_COLOR . ';
        font-family: sans-serif;
    }
    .wagContainer a img {
        border: none;
    }
    .wagErrorMsg {
        color: #ff0000;
        font-weight: bold;
        margin: 1ex 0px;
    }
    .wagMsg {
        font-weight: bold;
        margin: 1ex 0px;
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
        color: ' . $this->FG_COLOR . ';
        background-color: ' . $this->HILITE_COLOR . ';
    }
    .wagSpacer {
        padding-left: 5px;
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
        background-color: ' . $this->THUMB_BG_COLOR . ';
    }
    .wagSlabThumbnail {
        display: block;
        width: ' . $this->THUMB_SIZE . 'px;
        height: ' . $this->THUMB_SIZE . 'px;
        margin: 0px;
        border: 0px;
    }
    .wagLinkThumb {
        vertical-align: bottom;
    }
    .wagActions {
        text-align: right;
    }
    .wagAction {
        display: inline-block;
        margin: 2px 0px 2px 2px;
        padding: 0px;
        text-align: center;
        vertical-align: middle;
        border: none;
    }
    #wagBreadcrumbs {
        margin-bottom: 1ex;
    }
    #wagAuth {
        margin: 0.5ex;
    }
    #wagAlbumHeader {
        margin-bottom: 1ex;
    }
    #wagAlbumCaption {
        display: inline-block;
        margin: 0px 1em;
        vertical-align: middle;
        font-weight: bold;
    }
    #wagItemOverlay {
        display: none;
        position: fixed;
        top: 0px;
        bottom: 0px;
        left: 0px;
        right: 0px;
        z-index: 99;
        text-align: center;
        background-color: rgb(128, 128, 128);
        background-color: rgba(0, 0, 0, .75);
    }
    #wagItemContainer {
        display: inline-block;
        position: relative;
        padding: 0px ' . $this->ITEM_BORDER . 'px ' . $this->ITEM_BORDER . 'px;
        color: ' . $this->ITEM_FG_COLOR . ';
        background-color: ' . $this->ITEM_BG_COLOR . ';
    }
    #wagItemContainer .wagErrorMsg {
        color: #ff8080;
    }
    #wagItemContainer .wagLink {
        color: ' . $this->ITEM_FG_COLOR . ';
        background-color: ' . $this->ITEM_BG_COLOR . ';
        border: 1px solid ' . $this->ITEM_FG_COLOR . ';
    }
    #wagItemContainer .wagLink:hover {
        color: ' . $this->ITEM_FG_COLOR . ';
        background-color: ' . $this->HILITE_COLOR . ';
    }
    #wagItemCaption {
        position: absolute;
        top: 2px;
        left: ' . $this->ITEM_BORDER . 'px;
        right: ' . (150 + $this->ITEM_BORDER) . 'px;
        height: 38px;
        overflow: hidden;
        text-align: left;
        font-size: small;
    }
    #wagItemContainer .wagAction {
        border: none;
    }
    #wagPhoto, #wagVideo {
        vertical-align: bottom;
    }
    #wagTrackMap {
        background-color: ' . $this->ITEM_BG_COLOR . ';
    }
    #wagInfoBox {
        display: none;
        position: fixed;
        top: 40px;
        right: 5px;
        padding: 1em;
        z-index: 101;
        color: #ffffff;
        background-color: #000000;
        background-color: rgba(0, 0, 0, .9);
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
    #wagItemLoading {
        display: inline-block;
        background-color: #FFFFFF;
        padding: 0px 2px;
    }
    #wagItemLoading div {
        display: inline-block;
        width: 10px;
        height: 16px;
        margin: 4px;
        vertical-align: middle;
        background-color: #F0F0F0;
    }
</style>';
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

    static $LBL_ERROR_MAP = 'There is a problem initializing Open Street Map.<br>Reload this album, wait until it finishes loading and try again.';

    static $LBL_EMPTY = 'This album is empty.';

    static $LBL_ACT_PREV = 'Previous';

    static $LBL_ACT_NEXT = 'Next';

    static $LBL_ACT_CLOSE = 'Close';

    static $LBL_ACT_INFO = 'Info';

    static $LBL_ACT_UP = 'Parent album';

    static $LBL_ACT_SSHW = 'Slideshow';

    static $LBL_ACT_VIEW = 'View';

    static $LBL_ACT_LOCATION = 'Locations';

    static $LBL_ACT_TRACK = 'GPS Tracks';

    static $LBL_DOWNLOAD_VIDEO = 'Click here to download the video.';

    static $LBL_LOCATIONS = 'Locations';

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
     * with the CSS class "wagContainer".
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
     *  	{$wag->output_css}
     *  	{$wag->output_js}
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
    const VERSION = "2";

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
     * array(array('caption' => root_caption, 'url' => root_url), array('caption' => subalbum_caption, 'url' => subalbum_url), ...)
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
        if ($this->action == 'show') {
            $this->show();
        } else 
            if ($this->action == 'thmb') {
                $this->thumb();
            } else 
                if ($this->action == 'rndr') {
                    $this->render();
                } else 
                    if (count($this->action) > 0 && $this->action[0] == 'i') {
                        $this->icon();
                    } else {
                        $this->show_error(self::$LBL_ERROR_INVALID);
                    }
        if (! ($this->output_type & $this->delegate_output_types)) {
            $this->output();
            exit(0);
        }
    }
    
    // --- JavaScript ----------------------------------------
    protected $JS = '';

    protected function init_js()
    {
        $libs = '';
        $map = '';
        $map_resize = '';
        if (count($this->item->tracks()) > 0 || $this->item->has_geolocation()) {
            $libs = $libs . '
<script src="http://www.openlayers.org/api/OpenLayers.js"></script>
<script src="http://www.openstreetmap.org/openlayers/OpenStreetMap.js"></script>                
';
            $pins = '';
            $items = $this->item->items();
            for ($i = 0; $i < count($items); ++ $i) {
                if ($items[$i]->longitude && $items[$i]->latitude) {
                    $pins = $pins . '
            lonlat = new OpenLayers.Geometry.Point(' . $items[$i]->longitude . ',' . $items[$i]->latitude . ').transform(gps_proj, map_proj);
            markers.addFeatures([new OpenLayers.Feature.Vector(lonlat, {thumb: wag_items[' . $i . '].icon, caption: wag_items[' . $i . '].caption}, markerStyle)]);
            bounds.extend(lonlat);';
                }
            }
            if (count($pins) > 0) {
                $pins = '
            var markerStyle = {externalGraphic: "' . $this->script . '?act=ipin", graphicHeight: 28, graphicWidth: 18, graphicXOffset: -3, graphicYOffset: -28};
            var lonlat;
            var markers = new OpenLayers.Layer.Vector("Markers", {projection: gps_proj});' . $pins . '
            wag_map.addLayer(markers);
            wag_map.zoomToExtent(bounds);
            var popper = new OpenLayers.Control.SelectFeature(markers, {
                    onSelect: function(feature) {
                        popup = new OpenLayers.Popup.FramedCloud("Thumbnail", feature.geometry.getBounds().getCenterLonLat(), null, "<img src=\"" + feature.attributes.thumb + "\" alt=\"" + feature.attributes.caption + "\" title=\"" + feature.attributes.caption + "\">", null, true,
                            function() {
                                this.destroy();
                                popper.unselect(feature);
                            });
                        wag_map.addPopup(popup, true);
                    },
                });
            wag_map.addControl(popper);
            popper.activate();
';
            }
            $tracks = $this->item->tracks();
            $track_js = '';
            for ($i = 0; $i < count($tracks); ++ $i) {
                $track_js = $track_js . '
            var lgpx' . $i . ' = new OpenLayers.Layer.Vector("' . $tracks[$i]->caption . '", {
                strategies: [new OpenLayers.Strategy.Fixed()],
				protocol: new OpenLayers.Protocol.HTTP({
                    url: "' . $this->url("rndr", $tracks[$i]->resource()) . '",
					format: new OpenLayers.Format.GPX()
				}),
				style: {strokeColor: "blue", strokeWidth: 5, strokeOpacity: 0.5},
				projection: gps_proj
            });
            lgpx' . $i . '.events.register("loadend", lgpx' . $i . ', function(event) {
                    bounds.extend(lgpx' . $i . '.getDataExtent());
                    wag_map.zoomToExtent(bounds);
            });
            wag_map.addLayer(lgpx' . $i . ');
';
            }
            $map = '
        var wag_map;

        function wag_display_map() {
            var container = document.getElementById("wagItemContainer");
            if(container === null || typeof(container) === "undefined") {
                return;
            }
            var html = "<div class=\"wagActions\">"
                + "<a href=\"javascript:void(0)\" class=\"wagLink wagAction\" onclick=\"wag_hide_item()\"><img src=\"' . $this->script . '?act=icls\" alt=\"' . self::$LBL_ACT_CLOSE . '\" title=\"' . self::$LBL_ACT_CLOSE . '\" class=\"wagLinkThumb\" ></a>"
                + "</div>"
                + "<div id=\"wagItemCaption\">' . self::$LBL_LOCATIONS . '</div>";
            if(typeof(OpenLayers) === "undefined") {
                html += "<div class=\"wagErrorMsg\">' . self::$LBL_ERROR_MAP . '</div>";
                container.innerHTML = html;
                wag_toggle_item(true);
            } else {
                html += "<div id=\"wagTrackMap\"></div>";
            }
            container.innerHTML = html;
            wag_resize_img();
            wag_toggle_item(true);
            wag_map = new OpenLayers.Map("wagTrackMap", {
                controls:[
                    new OpenLayers.Control.Navigation(),
					new OpenLayers.Control.PanZoomBar(),
					new OpenLayers.Control.LayerSwitcher(),
					new OpenLayers.Control.Attribution()],
				projection: new OpenLayers.Projection("EPSG:4326")
            });

            var layerMapnik = new OpenLayers.Layer.OSM.Mapnik("Mapnik");
            wag_map.addLayer(layerMapnik);

            var gps_proj = new OpenLayers.Projection("EPSG:4326");
            var map_proj = wag_map.getProjectionObject();
            var bounds = new OpenLayers.Bounds();
' . $pins . '
' . $track_js . '
        }
';
            $map_resize = '
            var map = document.getElementById("wagTrackMap");
            if(map !== null && typeof(map) !== "undefined") {
                var screen = wag_window_size();
                map.style.height = "" + (screen.height - ' . (2 * $this->ITEM_BORDER + 40) . ') + "px";
                map.style.width = "" + (screen.width - ' . (4 * $this->ITEM_BORDER) . ') + "px";
            }
';
        }
        $this->JS = $libs . '<script type="text/javascript">
        <!--
        var wag_original_onresize = window.onresize;
        var wag_original_onload = window.onload;

        var wag_loading_div = 0;
        var wag_loading_anim = setInterval(function() {
            var loading = document.getElementById("wagItemLoading" + wag_loading_div);
            if(loading !== null && typeof(loading) !== "undefined") {
                loading.style.backgroundColor = "#F0F0F0";
            }
            wag_loading_div = (wag_loading_div + 1) % 4;
            loading = document.getElementById("wagItemLoading" + wag_loading_div);
            if(loading !== null && typeof(loading) !== "undefined") {
                loading.style.backgroundColor = "#808080";
            }
        }, 300);

        var wag_items = ' . $this->item->items_json() . ';
        var wag_sshow_timeout = null;

        function wag_display_item(index, autoadvance) {
            if(typeof(autoadvance) === "undefined") {
                autoadvance = false;
            }
            if(!autoadvance) {
                wag_cancel_sshow();
            }
            var container = document.getElementById("wagItemContainer");
            if(container === null || typeof(container) === "undefined") {
                return;
            }
            wag_toggle_div(document.getElementById("wagInfoBox"), false);
            var info = wag_items[index];
            var next = (index + 1) % wag_items.length;
            var prev = index - 1;
            if(prev < 0) {
                prev = wag_items.length - 1;
            }
            var autoadvance_func = function(){ wag_hide_item(); };
            if(next > index) {
                autoadvance_func = function(){ wag_display_item(next, true); };
            }
            wag_populate_info_box(info);
            var html = "<div class=\"wagActions\">"
                + "<a href=\"javascript:void(0)\" class=\"wagLink wagAction\" onclick=\"wag_toggle_info_box(true)\"><img src=\"' . $this->script . '?act=iinf\" alt=\"' . self::$LBL_ACT_INFO . '\" title=\"' . self::$LBL_ACT_INFO . '\" class=\"wagLinkThumb\" ></a>"
                + "<span class=\"wagSpacer\"></span>"
                + "<a href=\"javascript:void(0)\" class=\"wagLink wagAction\" onclick=\"wag_display_item(" + prev + ")\"><img src=\"' . $this->script . '?act=iprv\" alt=\"' . self::$LBL_ACT_PREV . '\" title=\"' . self::$LBL_ACT_PREV . '\" class=\"wagLinkThumb\" ></a>"
                + "<a href=\"javascript:void(0)\" id=\"wagActNext\" class=\"wagLink wagAction\" onclick=\"wag_display_item(" + next + ")\"><img src=\"' . $this->script . '?act=inxt\" alt=\"' . self::$LBL_ACT_NEXT . '\" title=\"' . self::$LBL_ACT_NEXT . '\" class=\"wagLinkThumb\" ></a>"
                + "<span class=\"wagSpacer\"></span>"
                + "<a href=\"javascript:void(0)\" id=\"wagActClose\" class=\"wagLink wagAction\" onclick=\"wag_hide_item()\"><img src=\"' . $this->script . '?act=icls\" alt=\"' . self::$LBL_ACT_CLOSE . '\" title=\"' . self::$LBL_ACT_CLOSE . '\" class=\"wagLinkThumb\" ></a>"
                + "</div>"
                + "<div id=\"wagItemCaption\">" + info.caption + "</div>";
            if(info.type == "photo") {
                container.innerHTML = html + "<img src=\"" + info.srcs[0] + "\" id=\"wagPhoto\" alt=\"" + info.caption + "\" title=\"" + info.caption + "\">";
                document.getElementById("wagPhoto").onload = wag_resize_img;
                wag_toggle_item(true);
            } else if(info.type == "video") {
                html += "<video id=\"wagVideo\" controls=\"controls\" preload=\"none\"" + (info.poster ? " poster=\"" + info.poster + "\"" : "" ) + ">";
                for(var i = 0; i < info.srcs.length; ++ i) {
                    html += "<source type=\"" + info.mimes[i] + "\" src=\"" + info.srcs[i] + "\">";
                }
                html += "<div class=\"wagErrorMsg\">' . self::$LBL_ERROR_VIDEO . '</div>"
                    + "<a href=\"" + info.srcs[0] + "\" class=\"wagLink\">' . self::$LBL_DOWNLOAD_VIDEO . '</a>"
                    + "</video>";
		        container.innerHTML = html;
                wag_toggle_item(true);
                var video = document.getElementById("wagVideo");
                if(video !== null && typeof(video) !== "undefined" && typeof(video.play) !== "undefined") {
                    if(autoadvance && typeof(video.addEventListener) !== "undefined") {
                        video.addEventListener("playing", function(){ wag_cancel_sshow(); });
                        video.addEventListener("ended", autoadvance_func);
                        video.addEventListener("error", autoadvance_func);
                    }
                    // fix for Chrome - cannot play right away
                    setTimeout(function(){ video.play(); }, 50);
                }
            }
            if(autoadvance) {
                wag_sshow_timeout = setTimeout(autoadvance_func, ' . $this->SLIDESHOW_INTERVAL . ');
            }
        }

        function wag_hide_item() {
            wag_cancel_sshow();
            wag_toggle_div(document.getElementById("wagInfoBox"), false);
            wag_toggle_item(false);
            var container = document.getElementById("wagItemContainer");
            if(container === null || typeof(container) === "undefined") {
                return;
            }
            container.innerHTML = "";
        }

        function wag_cancel_sshow() {
            if(wag_sshow_timeout !== null) {
                clearTimeout(wag_sshow_timeout);
                wag_sshow_timeout = null;
            }
        }

        function wag_populate_info_box(info) {
            var element;
            var copyright = "";
            if(typeof(info.copyright) !== "undefined" && info.copyright !== null) {
                copyright = info.copyright;
            }
            if(copyright.length > 0) {
                if (copyright[0] == String.fromCharCode(169)) {
                    copyright = "&copy;" + copyright.substring(1);
                } else {
                    copyright = "&copy; " + copyright;
                }
            }
            element = document.getElementById("wagCopyright");
            if(element) {
                element.innerHTML = copyright;
            }
            var table_html = "<table id=\"wagInfoTable\">";            
            if(info.date) {
                table_html += "<tr><td class=\"wagInfoRowHead\">' . self::$LBL_INFO_DATE . '</td><td>" + info.date + "</td></tr>";
            }
            if(info.shutter) {
                table_html += "<tr><td class=\"wagInfoRowHead\">' . self::$LBL_INFO_SHUTTER . '</td><td>" + info.shutter + "</td></tr>";
            }
            if(info.aperture) {
                table_html += "<tr><td class=\"wagInfoRowHead\">' . self::$LBL_INFO_APERTURE . '</td><td>" + info.aperture + "</td></tr>";
            }
            if(info.ISO) {
                table_html += "<tr><td class=\"wagInfoRowHead\">' . self::$LBL_INFO_ISO . '</td><td>" + info.ISO + "</td></tr>";
            }
            if(info.zoom) {
                table_html += "<tr><td class=\"wagInfoRowHead\">' . self::$LBL_INFO_ZOOM . '</td><td>" + info.zoom + "</td></tr>";
            }
            table_html += "</table>";
            element = document.getElementById("wagInfoTableContainer");
            if(element) {
                element.innerHTML = table_html;
            }
            var geo = "";
            if (typeof(info.longitude) !== "undefined" && info.longitude !== null && typeof(info.latitude) !== "undefined" && info.latitude !== null) {
                    geo = "<a href=\"http://www.openstreetmap.org/?mlat=" + info.latitude + "&mlon=" + info.longitude + "\" class=\"wagLink wagSlabLink\"><img alt=\"' . self::$LBL_INFO_LOCATION . '\" title=\"' . self::$LBL_INFO_LOCATION . '\" src=\"http://staticmap.openstreetmap.de/staticmap.php?zoom=8&size=200x200&center=" + info.latitude + "," + info.longitude + "&markers=" + info.latitude + "," + info.longitude + ",red-pushpin\" class=\"wagLinkThumb\"></a>";
            }
            element = document.getElementById("wagInfoGeo");
            if(element) {
                element.innerHTML = geo;
            }
        }

        function wag_toggle_div(div, visible) {
            if(div) {
                if(visible) {
                    div.style.display = "block";
                } else {
                    div.style.display = "none";
                }
            }
        }

        function wag_toggle_item(visible) {
            wag_toggle_div(document.getElementById("wagItemOverlay"), visible);
        }

        function wag_toggle_info_box(visible) {
            wag_toggle_div(document.getElementById("wagInfoBox"), visible);
        }

        function wag_is_zone_close(event) {
            var element = event.target || event.srcElement;
            return element && element.id == "wagItemOverlay";
        }

        function wag_is_zone_next(event) {
            var element = event.target || event.srcElement;
            return element && element.id == "wagPhoto";
        }

        function wag_item_click(event) {
            if(wag_is_zone_next(event)) {
                var element = document.getElementById("wagActNext");
                if(element) {
                    element.onclick();
                }
            } else if(wag_is_zone_close(event)) {
                var element = document.getElementById("wagActClose");
                if(element) {
                    element.onclick();
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
                var img_size = wag_img_size(img);
                var img_props = img_size.width / img_size.height;
                var avail_size = wag_window_size();
                avail_size.width = Math.max(1, avail_size.width - ' . (2 * $this->ITEM_BORDER + 5) . ');
                avail_size.height = Math.max(1, avail_size.height - ' . ($this->ITEM_BORDER + 40) . ');
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
            }' . $map_resize . '   
        }


        function wag_loaded() {
            if(typeof wag_original_onload === "function") {
                wag_original_onload();
            }
            var loading = document.getElementById("wagItemLoading");
            if(loading !== null && typeof(loading) !== "undefined") {
                loading.style.display = "none";
            }
            clearTimeout(wag_loading_anim);
        }

        window.onresize = wag_resize_img;
        window.onload = wag_loaded;

' . $map . '
        // -->
</script>';
    }
    
    // --- Private code ----------------------------------------
    const TARGET_UNKNOWN = 0;

    const TARGET_PHOTO = 1;

    const TARGET_VIDEO = 2;

    const TARGET_ALBUM = 4;

    const TARGET_TRACK = 8;

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

    protected static $TRACK_EXT = array(
        'gpx' => 'text/xml'
    );

    protected static $IMG_MAX_AGE = 3600;

    protected $script = NULL;

    public $is_managed = FALSE;

    protected $action = NULL;

    protected $target = NULL;

    protected $key = NULL;

    protected $has_access = FALSE;

    protected $target_type = self::TARGET_UNKNOWN;

    protected $item = NULL;

    public function __construct()
    {
        $this->script = basename($_SERVER['PHP_SELF']);
        $this->is_managed = basename(__FILE__) != $this->script;
        $this->process_passkey();
        $this->parse_params();
        $this->has_access = $this->is_valid_key();
        $this->item = new WAG_item($this, array());
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
            $passkey = $_GET['passkey'];
            if (get_magic_quotes_gpc()) {
                $passkey = stripslashes($passkey);
            }
            $params = $_GET;
            unset($params['passkey']);
            if (get_magic_quotes_gpc() && isset($params['trg'])) {
                $params['trg'] = stripslashes($params['trg']);
            }
            $params['key'] = self::mangle_key($passkey);
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
            $trg = $_GET['trg'];
            if (get_magic_quotes_gpc()) {
                $trg = stripslashes($trg);
            }
            $this->target = self::decode_target($trg);
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

    public function url($act, $target)
    {
        return $this->script . '?act=' . $act . $this->url_arg_target($target) . $this->url_arg_key();
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
    public static function get_file_name($file, $with_ext = FALSE)
    {
        preg_match('%^(.*?)[\\\\/]*(([^/\\\\]*?)((\.)([^\.\\\\/]*?)|))[\\\\/]*$%im', $file, $matches);
        if ($with_ext) {
            return $matches[2];
        } else {
            return $matches[3];
        }
    }

    public static function is_photo($file)
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

    public static function is_video($file)
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

    public static function is_track($file)
    {
        if (! is_file($file)) {
            return FALSE;
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (array_key_exists($ext, self::$TRACK_EXT)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public static function get_video_type($video)
    {
        $ext = strtolower(pathinfo($video, PATHINFO_EXTENSION));
        return self::$VIDEO_EXT[$ext];
    }

    public static function get_track_type($track)
    {
        $ext = strtolower(pathinfo($track, PATHINFO_EXTENSION));
        return self::$TRACK_EXT[$ext];
    }

    public static function get_name_set($file)
    {
        $file_no_ext = WAG::get_file_name($file);
        $dir = dirname($file);
        $files = array();
        foreach (scandir($dir) as $f) {
            if ($f == '.' || $f == '..') {
                continue;
            }
            $path = $dir . '/' . $f;
            if (is_file($path)) {
                $f_no_ext = WAG::get_file_name($f);
                if ($f_no_ext === $file_no_ext) {
                    array_push($files, $path);
                }
            }
        }
        return $files;
    }

    public static function is_video_set($files)
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

    public function get_album_caption($dir)
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

    protected function load_breadcrumbs()
    {
        $this->output_breadcurmbs = array();
        $path = $this->target;
        while (dirname($path) != $path) {
            $path = dirname($path);
            $crumb = array(
                'caption' => $this->get_album_caption($path),
                'url' => $this->script . '?act=show' . $this->url_arg_target($path) . $this->url_arg_key()
            );
            array_unshift($this->output_breadcurmbs, $crumb);
        }
    }

    protected function load_target_info()
    {
        if (is_dir($this->target)) {
            $this->target_type = self::TARGET_ALBUM;
            $this->item = new WAG_album($this, $this->target);
        } else 
            if (self::is_photo($this->target) || self::is_video($this->target)) {
                $name_set = self::get_name_set($this->target);
                if (self::is_video_set($name_set)) {
                    $this->target_type = self::TARGET_VIDEO;
                    $this->item = new WAG_item($this, $name_set);
                } else {
                    $this->target_type = self::TARGET_PHOTO;
                    $this->item = new WAG_item($this, array(
                        $this->target
                    ));
                }
            } else 
                if (self::is_track($this->target)) {
                    $this->target_type = self::TARGET_TRACK;
                    $this->item = new WAG_item($this, array(
                        $this->target
                    ));
                } else {
                    $this->target_type = self::TARGET_UNKNOWN;
                }
    }

    protected function generate_slab($item, $index)
    {
        $css_class;
        $link;
        $js = "";
        $footer_html = "";
        if ($item->type == 'album') {
            $css_class = "wagSlabAlbum";
            $link = $item->srcs[0];
            $footer_html = "<br>" . $item->caption;
        } else {
            $css_class = "wagSlabPhoto";
            $link = "javascript:void(0)";
            $js = ' onclick="wag_display_item(' . $index . ')" ';
            $footer_html = '<noscript><a href="' . $item->srcs[0] . '">' . self::$LBL_ACT_VIEW . '</a></noscript>';
        }
        return <<<HTML

        <div class="{$css_class}">
            <a href="{$link}" class="wagLink wagSlabLink" {$js}>
                <img src="{$item->icon}" class="wagSlabThumbnail" title="{$item->caption}" alt="{$item->caption}">
            </a>{$footer_html}
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
            $content = $content . '<a href="' . $crumb['url'] . '" class="wagLink">' . $crumb['caption'] . '</a>';
        }
        $content = $content . '
    </div>
';
        return $content;
    }

    protected function generate_album_actions()
    {
        $up = "";
        if (count($this->output_breadcurmbs) > 0) {
            $up = '<a href="' . $this->output_breadcurmbs[count($this->output_breadcurmbs) - 1]['url'] . '" class="wagLink wagAction"><img src="' . $this->script . '?act=iupp" alt="' . self::$LBL_ACT_UP . '" title="' . self::$LBL_ACT_UP . '" class="wagLinkThumb" ></a>';
        }
        $sshw = "";
        if (count($this->item->items()) > 0) {
            $sshw = '<a href="javascript:void(0)" class="wagLink wagAction" onclick="wag_display_item(0, true)"><img src="' . $this->script . '?act=issh" alt="' . self::$LBL_ACT_SSHW . '" title="' . self::$LBL_ACT_SSHW . '" class="wagLinkThumb" ></a>';
        }
        $tracks = "";
        if (count($this->item->tracks()) > 0) {
            $tracks = '<a href="javascript:void(0)" class="wagLink wagAction" onclick="wag_display_map()"><img src="' . $this->script . '?act=itrk" alt="' . self::$LBL_ACT_TRACK . '" title="' . self::$LBL_ACT_TRACK . '" class="wagLinkThumb" ></a>';
        } else 
            if ($this->item->has_geolocation()) {
                $tracks = '<a href="javascript:void(0)" class="wagLink wagAction" onclick="wag_display_map()"><img src="' . $this->script . '?act=iflg" alt="' . self::$LBL_ACT_LOCATION . '" title="' . self::$LBL_ACT_LOCATION . '" class="wagLinkThumb" ></a>';
            }
        return '
        ' . $sshw . $tracks . '<span class="wagSpacer"></span>' . $up;
    }

    protected function show_album()
    {
        $this->output_type = self::TYPE_HTML;
        $this->output_title = $this->item->caption;
        $this->output_content = '
    <div id="wagAlbumHeader">
        <div id="wagAlbumCaption">' . $this->item->caption . '</div>' . $this->generate_album_actions() . '
    </div>
    <div id="wagSlabs">';
        $index = 0;
        foreach ($this->item->albums() as $album) {
            $this->output_content = $this->output_content . $this->generate_slab($album, $index);
            $index ++;
        }
        $index = 0;
        foreach ($this->item->items() as $item) {
            $this->output_content = $this->output_content . $this->generate_slab($item, $index);
            $index ++;
        }
        if (count($this->item->albums()) == 0 && count($this->item->items()) == 0) {
            $this->output_content = $this->output_content . '<div class="wagMsg">' . self::$LBL_EMPTY . '</div>';
        }
        $this->output_content = $this->output_content . '</div>
    <div id="wagItemOverlay" onclick="wag_item_click(event)">
        <div><div id="wagItemLoading"><div id="wagItemLoading0"></div><div id="wagItemLoading1"></div><div id="wagItemLoading2"></div></div></div>
        <div id="wagItemContainer"></div>
    </div>
    <div id="wagInfoBox">
        <div id="wagInfoClose">
            <a href="javascript:void(0)" class="wagLink wagAction" onclick="wag_toggle_info_box(false)"><img src="' . $this->script . '?act=icls" alt="' . self::$LBL_ACT_CLOSE . '" title="' . self::$LBL_ACT_CLOSE . '" class="wagLinkThumb" ></a>
        </div>
        <div id="wagInfoList">
            <span id="wagCopyright"></span><br>
            <div id="wagInfoTableContainer"></div>
        </div>
        <div id="wagInfoGeo"></div>
    </div>            
';
    }

    protected function show_auth_form()
    {
        $this->output_type = self::TYPE_HTML;
        $this->output_title = self::$LBL_AUTH_TITLE;
        $encoded_target = htmlspecialchars(self::encode_target($this->target));
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
            $this->show_auth_form();
            return;
        }
        $this->load_target_info();
        if ($this->target_type == self::TARGET_ALBUM) {
            $this->init_js();
            $this->output_js = $this->JS;
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
        if (count($this->item->albums()) > 0) {
            $tn = $this->build_thumbnail_subalbums($slot_tn_size);
            $tn_dx = $tn_idx % 3;
            $tn_dy = (int) ($tn_idx / 3);
            imagecopyresized($tn_img, $tn, ($tn_dx * $slot_size) + 1, ($tn_dy * $slot_size) + 1, 0, 0, $slot_tn_size, $slot_tn_size, $slot_tn_size, $slot_tn_size);
            imagedestroy($tn);
            $tn_idx ++;
        }
        foreach ($this->item->items() as $item) {
            $tn;
            if ($item->type == 'video') {
                $tn = $this->build_thumbnail_video($item->resource(), $slot_tn_size);
            } else {
                $tn = $this->build_thumbnail_photo($item->resource(), $slot_tn_size);
            }
            $tn_dx = $tn_idx % 3;
            $tn_dy = (int) ($tn_idx / 3);
            imagecopyresized($tn_img, $tn, ($tn_dx * $slot_size) + 1, ($tn_dy * $slot_size) + 1, 0, 0, $slot_tn_size, $slot_tn_size, $slot_tn_size, $slot_tn_size);
            imagedestroy($tn);
            $tn_idx ++;
            if ($tn_idx > 5) {
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
            } else 
                if (self::is_track($this->target)) {
                    $this->output_type = self::TYPE_FILE;
                    $this->output_mime_type = self::get_track_type($this->target);
                    $this->output_content = $this->target;
                } else {
                    $this->output_type = self::TYPE_IMAGE;
                    $this->output_content = $this->build_thumbnail_unknown($this->THUMB_SIZE);
                }
        }
    }

    protected function icon()
    {
        switch ($this->action) {
            case 'inxt':
                $thumb = imagecreatefromstring(base64_decode('R0lGODlhIAAgAJEAAAAAAP///////wAAACH5BAEAAAIALAAAAAAgACAAAAJ3VI6py2chopy0WmSz
ri6CD4biCEodiaZfdKpuyELCSwOxV7v3nAb5rjrUgD3hi4hKHB+4pFKFJC2gTJ5TkYqOGiitiEvy
hhjZahBbbl2NadmZ3W5K4XHrfGju/fK5ep+q9jciJmhTZVdoeLjByNHRCMnUMEmJUQAAOw=='));
                break;
            case 'iprv':
                $thumb = imagecreatefromstring(base64_decode('R0lGODlhIAAgAJEAAAAAAP///////wAAACH5BAEAAAIALAAAAAAgACAAAAJ1VI6py2chopy0WmSz
ri6CD4biCEodiaZfdKpuyELCSwOxV7v3XAfq3vOlgC/E74FTJY6tlIIpUz6HSB6JAU2KGlnrFktt
jsAoImrRdU3L1aIxHHUL2WJpunfPwbX6kbnv1wZIsuM1aFO4ocjRseiI1BApiVEAADs='));
                break;
            case 'icls':
                $thumb = imagecreatefromstring(base64_decode('R0lGODlhIAAgAJEAAAAAAP///////wAAACH5BAEAAAIALAAAAAAgACAAAAKEVI6py2chopy0WmSz
ri6CD4biCEodiaZfdKpuyELCSwOxV7v3nKv7iEAFRT9RAjiEPXAhRfNIXPKMUOeoiGygsFkrids9
+KSqxbhF0m7J6UZgjX4Ovcr4h05fseVvqjhqB5Dkd7XX82V4CCijCMfYWBgIuTMFaUO5kcnRodm5
5AYaKlAAADs='));
                break;
            case 'iinf':
                $thumb = imagecreatefromstring(base64_decode('R0lGODlhIAAgAJEAAAAAAP///////wAAACH5BAEAAAIALAAAAAAgACAAAAJ4VI6py2chopy0WmSz
ri6CD4biCEodiaZfdKpuyELCSwOxV7v37CL6g1P5VLvXMFXMEYE8JSqZSvxawsNUZrQuqVEtkpkN
XINd8Rbb8z7BafOXizqu4SQ5CRqXvtHOO7s/ggdY8jdI2NFkaCKz0XiB6Bjp0EBZiVEAADs='));
                break;
            case 'iupp':
                $thumb = imagecreatefromstring(base64_decode('R0lGODlhIAAgAJEAAAAAAP///////wAAACH5BAEAAAIALAAAAAAgACAAAAJxVI6py2chopy0WmSz
ri6CD4biCEodiaZfdKpuyELCSwOxV7v3nKt773vggKSf6/AyphI6IQ+1CLZIjZRS1EAWndSsVnT9
eJlgbmisKE+Jahl7u36vzPISvW6718Ny/tvPtvPUJ7hhyNFxqCiE1qggUAAAOw=='));
                break;
            case 'issh':
                $thumb = imagecreatefromstring(base64_decode('R0lGODlhIAAgAJEAAAAAAP///////wAAACH5BAEAAAIALAAAAAAgACAAAAKQVI6py2chopy0WmSz
ri6CD4biCEodiaZfdKpuyELCSwOxV7v3DDZ+AHvgPr9GsEUE0g5HWYi5VK6EvKRIesXunlgANNsc
eruI0ddG5WbP43C1ze3Cp8i51axN98io83Zf46d39yJYV8TgFodYVjI4xthI57QXaUiZk/KXibLJ
ObLz9jlFtWF60XGqKmRpKVAAADs='));
                break;
            case 'iflg':
                $thumb = imagecreatefromstring(base64_decode('R0lGODlhIAAgAJEAAAAAAP///////wAAACH5BAEAAAIALAAAAAAgACAAAAJ+VI6py2chopy0WmSz
ri6CD4biCEodiaZfdKpuyELCSwOxRx71+uCjQrvNcgyVENVAkI7EpA7WG/6cyyiymGJOlS+t6Fnz
hsBBK3FnM0/RYhC5q/4G2PHxfNf+vF15wN5Y53YXFogG1WJYhZgoIiTFyBO1MXnRQXnZ46SZJFAA
ADs='));
                break;
            case 'itrk':
                $thumb = imagecreatefromstring(base64_decode('R0lGODlhIAAgAJEAAAAAAP///////wAAACH5BAEAAAIALAAAAAAgACAAAAKDVI6py2chopy0WmSz
ri6CD4biCEodiaZfdKpuyELCGx5o7NFAQuIzvRj5dEHREKgQPnKvImz5QyKULZHNOqXKRjwd4Ahy
vsAfcdcIxZ53WXS1nKwFbml5m0aGX3V5r5bp11NnN+fVtzY2KBfYF1ii6PjUERW54rOBeTGZyenQ
8AmKUQAAOw=='));
                break;
            case 'ipin':
                $thumb = imagecreatefromstring(base64_decode('R0lGODlhEgAcAKIAAAAAAP///9MgJzAHCf///wAAAAAAAAAAACH5BAEAAAQALAAAAAASABwAAANS
CDrc9BACQatw8U1rV95cBwxSaCoAAZocyrLKG7oyhaq1p70jGa0VzIcjzOAsPmMJqfwRm8sgdMec
ApPN6/QotT69VagWXElZQYqtapJWr83bBAA7'));
                break;
            default:
                $thumb = imagecreatefromstring(base64_decode('R0lGODlhIAAgAJEAAAAAAP///////wAAACH5BAEAAAIALAAAAAAgACAAAAJ4VI6py2chopy0WmSz
ri6CD4biCEodiaZfdKpuyELCSwOxV7v37CL6g1P5VLvXMFXMEYE8JSqZSvxawsNUZrQuqVEtkpkN
XINd8Rbb8z7BafOXizqu4SQ5CRqXvtHOO7s/ggdY8jdI2NFkaCKz0XiB6Bjp0EBZiVEAADs='));
                break;
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
{$this->CSS}
{$this->JS}
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
        header('Content-type: ' . $this->output_mime_type);
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

class WAG_item
{

    protected $wag;

    protected $resource = NULL;

    protected $is_loaded = FALSE;

    protected $info = array();

    public function __construct($wag, $resources)
    {
        $this->wag = $wag;
        if (WAG::is_video_set($resources)) {
            $this->info['type'] = 'video';
            $this->info['srcs'] = array();
            $this->info['mimes'] = array();
            foreach ($resources as $resource) {
                if (WAG::is_video($resource)) {
                    array_push($this->info['srcs'], $this->wag->url('rndr', $resource));
                    array_push($this->info['mimes'], WAG::get_video_type($resource));
                    if (! $this->resource) {
                        $this->resource = $resource;
                        $this->info['icon'] = $this->wag->url('thmb', $this->resource);
                    }
                } else 
                    if (WAG::is_photo($resource)) {
                        $this->info['poster'] = $this->wag->url('rndr', $resource);
                        $this->resource = $resource;
                        $this->info['icon'] = $this->wag->url('thmb', $this->resource);
                    }
            }
        } else 
            if (count($resources) > 0) {
                $this->resource = $resources[0];
                $this->info['icon'] = $this->wag->url('thmb', $this->resource);
                if (WAG::is_photo($this->resource)) {
                    $this->info['type'] = 'photo';
                    $this->info['srcs'] = array(
                        $this->wag->url('rndr', $this->resource)
                    );
                } else 
                    if (WAG::is_track($this->resource)) {
                        $this->info['type'] = 'track';
                        $this->info['srcs'] = array(
                            $this->wag->url('rndr', $this->resource)
                        );
                    } else {
                        $this->info['type'] = 'unknown';
                    }
            }
    }

    public function resource()
    {
        return $this->resource;
    }

    protected function load()
    {
        if ($this->is_loaded) {
            return;
        }
        if ($this->info['type'] == 'photo') {
            $this->load_photo_info($this->resource);
        } else 
            if ($this->info['type'] == 'video') {
                if (WAG::is_photo($this->resource)) {
                    $this->load_photo_info($this->resource);
                } else {
                    $this->load_video_info($this->resource);
                }
            } else 
                if ($this->info['type'] == 'track') {
                    $this->load_track_info($this->resource);
                }
        $this->is_loaded = TRUE;
    }

    protected function load_photo_info($photo)
    {
        list ($this->info['width'], $this->info['height']) = getimagesize($photo, $info);
        if (isset($info["APP13"])) {
            $iptc = iptcparse($info["APP13"]);
            $this->info['caption'] = htmlspecialchars($iptc["2#120"][0]);
            $this->info['copyright'] = htmlspecialchars($iptc["2#116"][0]);
        }
        if (! isset($this->info['caption'])) {
            $this->info['caption'] = htmlspecialchars(WAG::get_file_name($photo));
        }
        if (extension_loaded('exif')) {
            $exif = @exif_read_data($photo, 'IFD0');
            if ($exif) {
                $orient = $exif['Orientation'];
                switch ($orient) {
                    case 6:
                    case 8:
                        $width = $this->info['width'];
                        $this->info['width'] = $this->info['height'];
                        $this->info['height'] = $width;
                        break;
                }
                $this->info['date'] = $exif['DateTimeOriginal'];
                $lon = $exif['GPSLongitude'];
                if ($lon) {
                    $this->info['longitude'] = self::calculate_exif_gps_number($lon[0]) + self::calculate_exif_gps_number($lon[1]) / 60 + self::calculate_exif_gps_number($lon[2]) / 3600;
                    if (strtoupper($exif['GPSLongitudeRef']) == 'W') {
                        $this->info['longitude'] = - $this->info['longitude'];
                    }
                }
                $lat = $exif['GPSLatitude'];
                if ($lat) {
                    $this->info['latitude'] = self::calculate_exif_gps_number($lat[0]) + self::calculate_exif_gps_number($lat[1]) / 60 + self::calculate_exif_gps_number($lat[2]) / 3600;
                    if (strtoupper($exif['GPSLatitudeRef']) == 'S') {
                        $this->info['latitude'] = - $this->info['latitude'];
                    }
                }
                if ($exif['ExposureTime']) {
                    $this->info['shutter'] = self::calculate_exif_shutter($exif['ExposureTime']);
                }
                if ($exif['FNumber']) {
                    $this->info['aperture'] = self::calculate_exif_gps_number($exif['FNumber']);
                }
                $this->info['ISO'] = $exif['ISOSpeedRatings'];
                $this->info['zoom'] = $exif['FocalLengthIn35mmFilm'];
            }
        }
        if (! $this->info['date']) {
            $this->info['date'] = date("Y-m-d H:i:s", filemtime($photo));
        }
    }

    protected function load_video_info($video)
    {
        $this->info['caption'] = htmlspecialchars(WAG::get_file_name($video));
        $this->info['date'] = date("Y-m-d H:i:s", filemtime($video));
    }

    protected function load_track_info($track)
    {
        $this->info['caption'] = htmlspecialchars(WAG::get_file_name($track));
        $this->info['date'] = date("Y-m-d H:i:s", filemtime($track));
    }

    public function __get($name)
    {
        if (! array_key_exists($name, $this->info)) {
            $this->load();
        }
        return $this->info[$name];
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
}

class WAG_album extends WAG_item
{

    protected $albums = array();

    protected $items = array();

    protected $tracks = array();

    public function __construct($wag, $resource)
    {
        $this->wag = $wag;
        $this->resource = $resource;
        $this->info['icon'] = $this->wag->url('thmb', $this->resource);
        if (is_dir($this->resource)) {
            $this->info['type'] = 'album';
            $this->info['srcs'] = array(
                $this->wag->url('show', $this->resource)
            );
            $this->info['caption'] = htmlspecialchars($this->wag->get_album_caption($this->resource));
            $this->info['date'] = date("Y-m-d H:i:s", filemtime($this->resource));
        } else {
            $this->info['type'] = 'unknown';
        }
    }

    public function items_json()
    {
        $this->load();
        $infos = array();
        foreach ($this->items as $item) {
            $item->load();
            array_push($infos, $item->info);
        }
        return json_encode($infos);
    }

    public function albums()
    {
        $this->load();
        return $this->albums;
    }

    public function items()
    {
        $this->load();
        return $this->items;
    }

    public function has_geolocation()
    {
        $geo = FALSE;
        foreach ($this->items() as $item) {
            if ($item->longitude && $item->latitude) {
                $geo = TRUE;
                break;
            }
        }
        return $geo;
    }

    public function tracks()
    {
        $this->load();
        return $this->tracks;
    }

    protected function load()
    {
        if ($this->is_loaded) {
            return;
        }
        $this->load_album_info($this->resource);
        $this->is_loaded = TRUE;
    }

    protected function load_album_info($album)
    {
        $this->albums = array();
        $this->items = array();
        $this->tracks = array();
        $file_map = array();
        foreach (scandir($album) as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            $path = $album . '/' . $file;
            if (is_dir($path)) {
                array_push($this->albums, new WAG_album($this->wag, $path));
            } else {
                $file_no_ext = WAG::get_file_name($file);
                if (! array_key_exists($file_no_ext, $file_map)) {
                    $file_map[$file_no_ext] = array();
                }
                array_push($file_map[$file_no_ext], $path);
            }
        }
        foreach (array_keys($file_map) as $file_name) {
            $files = $file_map[$file_name];
            if (! WAG::is_video_set($files)) {
                foreach ($files as $file) {
                    if (WAG::is_photo($file)) {
                        array_push($this->items, new WAG_item($this->wag, array(
                            $file
                        )));
                    }
                }
            } else {
                $video_set = array();
                foreach ($files as $file) {
                    if (WAG::is_video($file) || WAG::is_photo($file)) {
                        array_push($video_set, $file);
                    }
                }
                array_push($this->items, new WAG_item($this->wag, $video_set));
            }
            foreach ($files as $file) {
                if (WAG::is_track($file)) {
                    array_push($this->tracks, new WAG_item($this->wag, array(
                        $file
                    )));
                }
            }
        }
    }
}

$wag = new WAG();
if (! $wag->is_managed) {
    $wag->delegate_output_types = 0;
    $wag->run();
}
?>