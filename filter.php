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
 * @package    filter
 * @subpackage smartmedia
 * @copyright   2019 Matt Porritt <mattp@catalyst-au.net>V
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Automatic smart media embedding filter class.
 *
 * @package    filter
 * @subpackage smartmedia
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
            // if we haven't already determined Videjos plugin enabled status
            // do so now.
            $enabledplayes = \core\plugininfo\media::get_enabled_plugins();
            if (in_array('videojs', $enabledplayes) && class_exists('media_videojs_plugin')){
                $this->videojsenabled = self::VIDEOJS_ENABLED;
            } else {
                $this->videojsenabled = self::VIDEOJS_NOT_ENABLED;
            }

        }

        return $this->videojsenabled;
    }

    /**
     * Get he media container types that are supported by this filter.
     *
     * @return string
     */
    private function get_media_types() {
        // TODO: Make this defined in config with some sensible defaults.
        return '\.mp4|\.webm|\.ogg';
    }

    /**
     * Given a href to a media file get the corresponding
     * smart media elements.
     *
     * @param string $linkhref The href to the source file.
     * @return array $elements The smart media elements to embed.
     */
    private function get_smart_elements($linkhref) {
        $urls = array();
        $options = array();

        // TODO: add smart element processing. For now just use original file.
        $href = new \moodle_url($linkhref);

        $urls[] = $href;
        $options['width'] = core_media_player_native::get_attribute($linkhref, 'width', PARAM_INT);
        $options['height'] = core_media_player_native::get_attribute($linkhref, 'height', PARAM_INT);
        $options['name'] = core_media_player_native::get_attribute($linkhref, 'title');

        $elements = array(
            'urls' => $urls,
            'options' => $options
        );

        return $elements;

    }

    /**
     *
     * @param unknown $urls
     * @param unknown $options
     * @return string
     */
    private function get_embed_markup($urls, $options) {
        $name = $options['name'];
        $width = $options['width'];
        $height = $options['height'];
        $embedoptions = array();

        $videojs = new \media_videojs_plugin();
        $newtext = $videojs->embed($urls, $name, $width, $height, $embedoptions);
        // TODO: Deal with fallback link.

        return $newtext;

    }


    private function replace_callback($matches) {
        $linktoreplace = $matches[0]; // First element is the full matched link markup.
        $linkhref = $matches[1]; // Second element is the href of the link.

        // First check link in filter cache to see if there is a cached replace.
        // TODO: link lookup caching.

        // If there isn't a cached value, process the link to see if we have smart content for it.
        // Rough steps:
        // * Break link into component parts (which should map to moodle file table and see,
        // what the processing status is.
        // * If processing hasn't completed use original link to render media player.
        // * If processing has completed use extra info to render media player.
        // * Update cache with results.
        // * Replace text with the result

        $elements = $this->get_smart_elements($linkhref);
        $replacedlink = $this->get_embed_markup($elements['urls'], $elements['options']);

        return $replacedlink;
    }

    /**
     *
     * {@inheritDoc}
     * @see moodle_text_filter::filter()
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
            // non string data can not be filtered anyway
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
