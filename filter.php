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
 * @copyright   2019 Matt Porritt <mattp@catalyst-au.net>
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
    /** @var bool True if currently filtering trusted text */
    private $trusted;

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
     * Get he media container types that are supported by this filter.
     *
     * @return string
     */
    private function get_media_types() {
        // TODO: Make this defined in config with some sensible defaults.
        return '\.mp4|\.webm|\.ogg';
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
        // * If processing has completed use extra infor to render media player.
        // * Update cache with results.



        $replacedlink = $linktoreplace;

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

        if (!is_string($text) or empty($text)) {
            // non string data can not be filtered anyway
            return $text;
        }

        if (stripos($text, '</a>') === false) {
            // Performance shortcut - if there are no </a> tags, nothing can match.
            return $text;
        }

        // Match and attempt to replace link tags, for valid media types.
        // We are only processing files for Moodle activities and resources,
        // not valid media types that are delivered external to Moodle.
        $mediatypes = $this->get_media_types();
        $re = '~\<a\s[^>]*href\=[\"\'](.*pluginfile\.php.*[' . $mediatypes .'])[\"\'][^>]*\>\X*?\<\/a\>~';
        $newtext = preg_replace_callback($re, array($this, 'replace_callback'), $text);

        // Return the string after it has been processed by the above.
        return $newtext;
    }

}
