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
 * Unit test for the filter_smartmedia
 *
 * @package    filter
 * @subpackage smartmedia
 * @copyright  2019 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/filter/smartmedia/filter.php'); // Include the code to test


class filter_smartmedia_testcase extends advanced_testcase {

    /**
     * There is no valid tags to replace.
     * Output next should be the same as input text.
     */
    function test_filter_smartmedia_filter_no_replace() {
        $this->resetAfterTest(true);
        $filterplugin = new filter_smartmedia(null, array());

        $inputtext = '<div class="no-overflow">'
            .'<a href="#">Some test data</a>'
            .'<a href="#">Some other test data</a>'
            .'</div>';

        $outputtext = $filterplugin->filter($inputtext);
        $this->assertEquals($inputtext, $outputtext);
    }

    /**
     * A link tag was matched in the source text,
     * but the file type isn't one we can process.
     */
    function test_filter_replace_callback_no_match() {
        $this->resetAfterTest(true);
        $filterplugin = new filter_smartmedia(null, array());

        $match = array('<a href="#">Some test data</a>', '#');

        // We're testing a private method, so we need to setup reflector magic.
        $method = new ReflectionMethod('filter_smartmedia', 'replace_callback');
        $method->setAccessible(true); // Allow accessing of private method.
        $proxy = $method->invoke($filterplugin, $match); // Get result of invoked method.
    }

    function test_filter_smartmedia_filter() {
        $this->resetAfterTest(true);
        $filterplugin = new filter_smartmedia(null, array());

        $validlinks = array(
            '<div class="no-overflow">'
                .'<a href="http://moodle.local/pluginfile.php/1461/mod_label/intro/SampleVideo1mb.mp4">SampleVideo1mb.mp4</a>'
            .'</div>'
        );

        //test for valid link
        foreach ($validlinks as $text) {
           // $filter = $filterplugin->filter($text);

        }


    }
}
