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
 *  Smart media filtering
 *
 *  This filter will replace any links to a compatible media file with
 *  a smart media plugin that plays that media inline and uses AI/ML
 *  techniques to improve user experience.
 *
 * @package    filter_smartmedia
 * @copyright   2019 Matt Porritt <mattp@catalyst-au.net>V
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_smartmedia\aws_api;
use local_smartmedia\aws_elastic_transcoder;

/**
 * Automatic smart media embedding filter class.
 *
 * @package    filter_smartmedia
 * @copyright  2019 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_smartmedia extends moodle_text_filter {

    /**
     * Video.js plugin enabled status not set.
     *
     * @var integer
     */
    const VIDEOJS_ENABLED_NOT_SET = -1;

    /**
     * Video.js plugin enabled.
     *
     * @var integer
     */
    const VIDEOJS_ENABLED = 1;

    /**
     * Video.js plugin not enabled.
     *
     * @var integer
     */
    const VIDEOJS_NOT_ENABLED = 0;

    /**
     * Enabled status of the video JS player.
     *
     * @var integer
     */
    private $videojsenabled = self::VIDEOJS_ENABLED_NOT_SET;

    /**
     * Array of file container types accepted by the filter.
     *
     * @var array
     */
    private $mediatypes = array(
        '.aac', // Type: audio/aac.
        '.au', // Type: audio/au.
        '.mp3', // Type: audio/mp3.
        '.m4a', // Type: audio/mp4.
        '.oga', // Type: audio/ogg.
        '.ogg', // Type: audio/ogg.
        '.wav', // Type: audio/wav.
        '.aif', // Type: audio/x-aiff.
        '.aiff', // Type: audio/x-aiff.
        '.aifc', // Type: audio/x-aiff.
        '.m3u', // Type: audio/x-mpegurl.
        '.wma', // Type: audio/x-ms-wma.
        '.ram', // Type: audio/x-pn-realaudio-plugin.
        '.rm', // Type: audio/x-pn-realaudio-plugin.
        '.rv', // Type: audio/x-pn-realaudio-plugin.
        '.mp4', // Type: video/mp4.
        '.m4v', // Type: video/mp4.
        '.f4v', // Type: video/mp4.
        '.mpeg', // Type: video/mpeg.
        '.mpe', // Type: video/mpeg.
        '.mpg', // Type: video/mpeg.
        '.ogv', // Type: video/ogg.
        '.qt', // Type: video/quicktime.
        '.3gp', // Type: video/quicktime.
        '.mov', // Type: video/quicktime.
        '.webm', // Type: video/webm.
        '.dv', // Type: video/x-dv.
        '.dif', // Type: video/x-dv.
        '.flv', // Type: video/x-flv.
        '.asf', // Type: video/x-ms-asf.
        '.avi', // Type: video/x-ms-wm.
        '.wmv', // Type: video/x-ms-wmv.

    );

    /**
     * Setup page with filter requirements and other prepare stuff.
     *
     * @param moodle_page $page The page we are going to add requirements to.
     * @param context $context The context which contents are going to be filtered.
     */
    public function setup($page, $context) {
        // This only requires execution once per request.
        static $jsinitialised = false;
        if ($jsinitialised) {
            return;
        }
        $jsinitialised = true;

        // Set up the media manager so that media plugins requiring JS are initialised.
        $mediamanager = core_media_manager::instance($page);
    }


    /**
     * The smart media filter uses the Video.js player exclusively,
     * this method check if it is enabled before continuing.
     * Because status is called mutliple times per request,
     * we determine the enabled status and then cache it,
     * for the life of the request to improve performance,
     *
     * @return integer
     */
    private function videojs_enabled() {

        if ($this->videojsenabled == self::VIDEOJS_ENABLED_NOT_SET) {
            // If we haven't already determined Videjos plugin enabled status
            // do so now.
            $enabledplayes = \core\plugininfo\media::get_enabled_plugins();
            if (in_array('videojs', $enabledplayes) && class_exists('media_videojs_plugin')) {
                $this->videojsenabled = self::VIDEOJS_ENABLED;
            } else {
                $this->videojsenabled = self::VIDEOJS_NOT_ENABLED;
            }

        }

        return $this->videojsenabled;
    }

    /**
     * Get the media container types that are supported by this filter.
     *
     * @return string $typestring String of supported types.
     */
    private function get_media_types() : string {
        $typestring = '\\'. implode('|\\', $this->mediatypes);

        return $typestring;
    }

    /**
     * Given a href to a media file get the corresponding
     * smart media elements.
     *
     * @param string $linkhref The href to the source file.
     * @return array $elements The smart media elements to embed.
     */
    private function get_smart_elements(string $linkhref) : array {
        $urls = array();
        $options = array();
        $elements = array();
        $moodleurl = new \moodle_url($linkhref);

        // Get smartmedia elements.
        $api = new aws_api();
        $transcoder = new aws_elastic_transcoder($api->create_elastic_transcoder_client());
        $conversion = new \local_smartmedia\conversion($transcoder);
        $smartmedia = $conversion->get_smart_media($moodleurl);

        if (!empty($smartmedia['media'])) {
            foreach ($smartmedia['media'] as $url) {
                $urls[] = $url;
            }

            $options['width'] = core_media_player_native::get_attribute($linkhref, 'width', PARAM_INT);
            $options['height'] = core_media_player_native::get_attribute($linkhref, 'height', PARAM_INT);
            $options['name'] = core_media_player_native::get_attribute($linkhref, 'title');

            $elements = array(
                    'urls' => $urls,
                    'options' => $options,
                    'download' => $smartmedia['download']
            );
        }

        return $elements;
    }

    /**
     *
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    private function string_ends_with(string $haystack, string $needle) : bool {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }

        return (substr($haystack, -$length) === $needle);
    }

    /**
     * Given an array of Moodle URLs and an array of options,
     * return the VideoJS markup.
     *
     * @param array $urls Source file Moodle URLS.
     * @param array $options Options for the player.
     * @return string $newtext Rendered VideoJS markup.
     */
    private function get_embed_markup(array $urls, array $options, array $download) : string {
        $name = $options['name'];
        $width = $options['width'];
        $height = $options['height'];
        $embedoptions = array();
        $downloaddata = '<video ';

        $videojs = new \media_videojs_plugin();
        $newtext = $videojs->embed($urls, $name, $width, $height, $embedoptions);
        // TODO: Deal with fallback link.

        // Add download URLs as data to the video tag.
        if (!empty($download)) {
            foreach ($download as $url) {
                if ($this->string_ends_with($url->out(), '.mp4')) {
                    $downloaddata .= 'data-download-video="' . $url->out(). '" ';
                } else if ($this->string_ends_with($url->out(), '.mp3')) {
                    $downloaddata .= 'data-download-audio="' . $url->out(). '" ';
                }
            }
            $newtext = preg_replace('/\<video /', $downloaddata, $newtext);
        }

        return $newtext;

    }

    /**
     * Get placeholder markup.
     *
     * @param string $linkhref The link to the file for downloading.
     * @param string $fulltext The full text of the element.
     * @return string $markup The placeholder markup.
     */
    private function get_placeholder_markkup(string $linkhref, string $fulltext) : string {
        global $OUTPUT;
        $moodleurl = new \moodle_url($linkhref);
        $markup = $fulltext;
        $context = new \stdClass();

        // Get status of conversion.
        $api = new aws_api();
        $transcoder = new aws_elastic_transcoder($api->create_elastic_transcoder_client());
        $conversion = new \local_smartmedia\conversion($transcoder);
        $conversionstatus = $conversion->will_convert($moodleurl);

        if ($conversionstatus != $conversion::CONVERSION_ERROR) {
            $context->linkhref = $linkhref;
            $markup = $OUTPUT->render_from_template('filter_smartmedia/placeholder', $context);
        }

        return $markup;
    }

    /**
     * Given a matched link check if there is smartmedia available,
     * and return updated link if there is.
     *
     * @param array $matches An array of link matches.
     * @return string
     */
    private function replace_callback(array $matches) : string {

        $linkhref = $matches[1]; // Second element is the href of the link.
        $fulltext = $matches[0]; // First element is the full matched link markup.
        $elements = $this->get_smart_elements($linkhref); // Get the smartmedia elements if they exist.
        $placeholder = get_config('filter_smartmedia', 'enableplaceholder');

        if (!empty($elements)) {
            $replacedlink = $this->get_embed_markup($elements['urls'], $elements['options'], $elements['download']);
        } else if ($placeholder) {
            // If no smartmedia found add the correct placeholder markup..
            $replacedlink = $this->get_placeholder_markkup($linkhref, $fulltext);
        } else {
            $replacedlink = $fulltext;
        }

        return $replacedlink;
    }

    /**
     * Apply the smart media filter to the text.
     *
     * @param string $text The text to filter.
     * @param array $options Extra options.
     * @return string $newtext The filtered Text.
     */
    public function filter($text, array $options = array()) {

        // First do some rapid checks to see if we can process the text
        // we've been given. If not then exit early.

        // The smart media filter uses the Video.js player exclusively,
        // so check if it is enabled before continuing.
        if (!$this->videojs_enabled()) {
            return $text;
        }

        if (!is_string($text) or empty($text)) {
            // Non string data can not be filtered anyway.
            return $text;
        }

        if (stripos($text, '</a>') === false) {
            // Performance shortcut - if there are no </a> tags, nothing can match.
            return $text;
        }

        // Next match and attempt to replace link tags, for valid media types.
        // We are only processing files for Moodle activities and resources,
        // not valid media types that are delivered externally to Moodle.
        $mediatypes = $this->get_media_types();
        $re = '~\<a\s[^>]*href\=[\"\'](.*pluginfile\.php.*[' . $mediatypes .'])[\"\'][^>]*\>\X*?\<\/a\>~';
        $newtext = preg_replace_callback($re, array($this, 'replace_callback'), $text);

        // Return the string after it has been processed by the above.
        return $newtext;
    }

}
