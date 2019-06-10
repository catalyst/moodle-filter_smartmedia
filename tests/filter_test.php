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
     * Test the video.js enabled method returns true.
     */
    function test_videojs_enabled_true() {
        $this->resetAfterTest(true);

        $filterplugin = new filter_smartmedia(null, array());

        // We're testing a private method, so we need to setup reflector magic.
        $method = new ReflectionMethod('filter_smartmedia', 'videojs_enabled');
        $method->setAccessible(true); // Allow accessing of private method.
        $proxy = $method->invoke($filterplugin); // Get result of invoked method.

        $this->assertEquals(1, $proxy);
    }

    /**
     * Test the video.js enabled method returns false.
     */
    function test_videojs_enabled_false() {
        $this->resetAfterTest(true);

        // Only enable the HTML5 video player not video.js.
        \core\plugininfo\media::set_enabled_plugins('html5video');

        $filterplugin = new filter_smartmedia(null, array());

        // We're testing a private method, so we need to setup reflector magic.
        $method = new ReflectionMethod('filter_smartmedia', 'videojs_enabled');
        $method->setAccessible(true); // Allow accessing of private method.
        $proxy = $method->invoke($filterplugin); // Get result of invoked method.

        $this->assertEquals(0, $proxy);
    }


    /**
     * Test method that gets smart media elements.
     * The href in htis test has no smart media elements available.
     */
    function test_get_smart_elements_no_smart() {
        $this->resetAfterTest(true);
        $filterplugin = new filter_smartmedia(null, array());

        $linkhref = 'http://moodle.local/pluginfile.php/1461/mod_label/intro/SampleVideo1mb.mp4';

        // We're testing a private method, so we need to setup reflector magic.
        $method = new ReflectionMethod('filter_smartmedia', 'get_smart_elements');
        $method->setAccessible(true); // Allow accessing of private method.
        $proxy = $method->invoke($filterplugin, $linkhref); // Get result of invoked method.

        $this->assertEquals('/pluginfile.php/1461/mod_label/intro/SampleVideo1mb.mp4', $proxy['urls'][0]->get_path());
        $this->assertEmpty($proxy['options']['width']);
        $this->assertEmpty($proxy['options']['height']);
    }

    function test_get_embed_markup_simple() {
        $this->resetAfterTest(true);
        $filterplugin = new filter_smartmedia(null, array());

        $urls = array(new \moodle_url('http://moodle.local/pluginfile.php/1461/mod_label/intro/SampleVideo1mb.mp4'));
        $options = array(
            'width' => '',
            'height' => '',
            'name' => ''
        );

        // We're testing a private method, so we need to setup reflector magic.
        $method = new ReflectionMethod('filter_smartmedia', 'get_embed_markup');
        $method->setAccessible(true); // Allow accessing of private method.
        $proxy = $method->invoke($filterplugin, $urls, $options); // Get result of invoked method.

        $this->assertRegExp('~mediaplugin_videojs~', $proxy);
        $this->assertRegExp('~</video>~', $proxy);
    }

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
    function test_filter_replace_callback() {
        $this->resetAfterTest(true);
        $filterplugin = new filter_smartmedia(null, array());

        $match = array(
            '<a href="http://moodle.local/pluginfile.php/1461/mod_label/intro/SampleVideo1mb.mp4">SampleVideo1mb.mp4</a>',
            'http://moodle.local/pluginfile.php/1461/mod_label/intro/SampleVideo1mb.mp4');

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
