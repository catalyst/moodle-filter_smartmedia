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
 * @copyright   2019 Matt Porritt <mattp@catalyst-au.net>
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

    public function filter($text, array $options = array()) {

        // First do some rapid checks to see if we can process the text
        // we've been given. If not then exit early.

        if (!is_string($text) or empty($text)) {
            // non string data can not be filtered anyway
            return $text;
        }

        if (stripos($text, '</a>') === false && stripos($text, '</video>') === false && stripos($text, '</audio>') === false) {
            // Performance shortcut - if there are no </a>, </video> or </audio> tags, nothing can match.
            return $text;
        }

        // Check SWF permissions.
        $this->trusted = !empty($options['noclean']) or !empty($CFG->allowobjectembed);

        // Looking for tags.
        $matches = preg_split('/(<[^>]*>)/i', $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

        if (!$matches) {
            return $text;
        }

        // Now that we have actually confirmed we have text we can actually act on. Do the processing.
        error_log($text);


        // Return the same string except processed by the above.
        return $text;
    }

}
