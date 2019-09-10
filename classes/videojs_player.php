<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Main class for plugin 'filter_smartmedia'
 *
 * @package   filter_smartmedia
 * @copyright 2016 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_smartmedia;

defined('MOODLE_INTERNAL') || die();

/**
 * Player that creates HTML5 <video> tag.
 *
 * @package   filter_smartmedia
 * @copyright 2016 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class videojs_player extends \media_videojs_plugin {
    /** @var array caches last moodle_page used to include AMD modules */
    protected $loadedonpage = [];
    /** @var string language file to use */
    protected $language = 'en';
    /** @var array caches supported extensions */
    protected $extensions = null;
    /** @var bool is this a youtube link */
    protected $youtube = false;

    /**
     * Generates code required to embed the player.
     *
     * @param \moodle_url[] $urls
     * @param string $name
     * @param int $width
     * @param int $height
     * @param array $options
     * @return string
     */
    public function embed($urls, $name, $width, $height, $options) {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $sources = array();
        $mediamanager = \core_media_manager::instance();
        $datasetup = [];

        $text = null;
        $isaudio = null;
        $hastracks = false;
        $hasposter = false;
        if (array_key_exists(\core_media_manager::OPTION_ORIGINAL_TEXT, $options) &&
                preg_match('/^<(video|audio)\b/i', $options[\core_media_manager::OPTION_ORIGINAL_TEXT], $matches)) {
            // Original text already had media tag - get some data from it.
            $text = $options[\core_media_manager::OPTION_ORIGINAL_TEXT];
            $isaudio = strtolower($matches[1]) === 'audio';
            $hastracks = preg_match('/<track\b/i', $text);
            $hasposter = self::get_attribute($text, 'poster') !== null;
        }

        // Currently Flash in VideoJS does not support responsive layout. If Flash is enabled try to guess
        // if HTML5 player will be engaged for the user and then set it to responsive.
        $responsive = (get_config('filter_smartmedia', 'useflash') && !$this->youtube) ? null : true;
        $flashtech = false;

        // Build list of source tags.
        foreach ($urls as $url) {
            $extension = $mediamanager->get_extension($url);
            $mimetype = $mediamanager->get_mimetype($url);
            if ($mimetype === 'video/quicktime' && (\core_useragent::is_chrome() || \core_useragent::is_edge())) {
                // Fix for VideoJS/Chrome bug https://github.com/videojs/video.js/issues/423 .
                $mimetype = 'video/mp4';
            }
            // If this is RTMP stream, adjust mimetype to those VideoJS suggests to use (either flash or mp4).
            if ($url->get_scheme() === 'rtmp') {
                if ($mimetype === 'video/x-flv') {
                    $mimetype = 'rtmp/flv';
                } else {
                    $mimetype = 'rtmp/mp4';
                }
            }
            $source = \html_writer::empty_tag('source', array('src' => $url, 'type' => $mimetype));
            $sources[] = $source;
            if ($isaudio === null) {
                $isaudio = in_array('.' . $extension, file_get_typegroup('extension', 'audio'));
            }
            if ($responsive === null) {
                $responsive = \core_useragent::supports_html5($extension);
            }
            if (($url->get_scheme() === 'rtmp' || !\core_useragent::supports_html5($extension))
                    && get_config('filter_smartmedia', 'useflash')) {
                $flashtech = true;
            }
        }
        $sources = implode("\n", $sources);

        // Find the title, prevent double escaping.
        $title = $this->get_name($name, $urls);
        $title = preg_replace(['/&amp;/', '/&gt;/', '/&lt;/'], ['&', '>', '<'], $title);

        if ($this->youtube) {
            $datasetup[] = '"techOrder": ["youtube"]';
            $datasetup[] = '"sources": [{"type": "video/youtube", "src":"' . $urls[0] . '"}]';
            $sources = ''; // Do not specify <source> tags - it may confuse browser.
            $isaudio = false; // Just in case.
        } else if ($flashtech) {
            $datasetup[] = '"techOrder": ["flash", "html5"]';
        }

        // Add a language.
        if ($this->language) {
            $datasetup[] = '"language": "' . $this->language . '"';
        }

        // Set responsive option.
        if ($responsive) {
            $datasetup[] = '"fluid": true';
        }

        if ($isaudio && !$hastracks) {
            // We don't need a full screen toggle for the audios (except when tracks are present).
            $datasetup[] = '"controlBar": {"fullscreenToggle": false}';
        }

        if ($isaudio && !$height && !$hastracks && !$hasposter) {
            // Hide poster area for audios without tracks or poster.
            // See discussion on https://github.com/videojs/video.js/issues/2777 .
            // Maybe TODO: if there are only chapter tracks we still don't need poster area.
            $datasetup[] = '"aspectRatio": "1:0"';
        }

        // Attributes for the video/audio tag.
        // We use data-setup-lazy as the attribute name for the config instead of
        // data-setup because data-setup will cause video.js to load the player as soon as the library is loaded,
        // which is BEFORE we have a chance to load any additional libraries (youtube).
        // The data-setup-lazy is just a tag name that video.js does not recognise so we can manually initialise
        // it when we are sure the dependencies are loaded.
        static $playercounter = 1;
        $attributes = [
            'data-setup-lazy' => '{' . join(', ', $datasetup) . '}',
            'id' => 'id_videojs_' . uniqid() . '_' . $playercounter++,
            'class' => get_config('filter_smartmedia', $isaudio ? 'audiocssclass' : 'videocssclass')
        ];

        if (!$responsive) {
            // Note we ignore limitsize setting if not responsive.
            parent::pick_video_size($width, $height);
            $attributes += ['width' => $width] + ($height ? ['height' => $height] : []);
        }

        if (\core_useragent::is_ios(10)) {
            // Hides native controls and plays videos inline instead of fullscreen,
            // see https://github.com/videojs/video.js/issues/3761 and
            // https://github.com/videojs/video.js/issues/3762 .
            // iPhone with iOS 9 still displays double controls and plays fullscreen.
            // iPhone with iOS before 9 display only native controls.
            $attributes += ['playsinline' => 'true'];
        }

        if ($text !== null) {
            // Original text already had media tag - add necessary attributes and replace sources
            // with the supported URLs only.
            if (($class = self::get_attribute($text, 'class')) !== null) {
                $attributes['class'] .= ' ' . $class;
            }
            $text = self::remove_attributes($text, ['id', 'width', 'height', 'class']);
            if (self::get_attribute($text, 'title') === null) {
                $attributes['title'] = $title;
            }
            $text = self::add_attributes($text, $attributes);
            $text = self::replace_sources($text, $sources);
        } else {
            // Create <video> or <audio> tag with necessary attributes and all sources.
            // We don't want fallback to another player because list_supported_urls() is already smart.
            // Otherwise we could end up with nested <audio> or <video> tags. Fallback to link only.
            $attributes += ['preload' => 'auto', 'controls' => 'true', 'title' => $title];
            $text = \html_writer::tag($isaudio ? 'audio' : 'video', $sources . self::LINKPLACEHOLDER, $attributes);
        }

        // Limit the width of the video if width is specified.
        // We do not do it in the width attributes of the video because it does not work well
        // together with responsive behavior.
        if ($responsive) {
            self::pick_video_size($width, $height);
            if ($width) {
                $text = \html_writer::div($text, null, ['style' => 'max-width:' . $width . 'px;']);
            }
        }

        return \html_writer::div($text, 'mediaplugin mediaplugin_videojs');
    }

    /**
     * Setup page requirements.
     *
     * @param \moodle_page $page The page we are going to add requirements to.
     */
    public function setup($page) {
        // Load dynamic loader. It will scan page for videojs media and load necessary modules.
        // Loader will be loaded on absolutely every page, however the videojs will only be loaded
        // when video is present on the page or added later to it in AJAX.
        $path = new \moodle_url('/media/player/videojs/videojs/video-js.swf');
        $contents = 'videojs.options.flash.swf = "' . $path . '";' . "\n";
        $contents .= $this->find_language(current_language());
        $page->requires->js_amd_inline(<<<EOT
require(["filter_smartmedia/loader"], function(loader) {
    loader.setUp(function(videojs) {
        $contents
    });
});
EOT
        );
    }
}
