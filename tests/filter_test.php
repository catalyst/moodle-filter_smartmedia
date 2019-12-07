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
 * @package    filter_smartmedia
 * @copyright  2019 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/filter/smartmedia/filter.php'); // Include the code to test.

/**
 * Unit test for the filter_smartmedia
 *
 * @package    filter_smartmedia
 * @copyright  2019 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_smartmedia_testcase extends advanced_testcase {

    /*
     * Set up method for this test suite.
     */
    public function setUp() {
        $this->resetAfterTest(true);
        global $CFG;
        set_config('api_region', 'ap-southeast-2', 'local_smartmedia');
        set_config('api_key', 'somefakekey', 'local_smartmedia');
        set_config('api_secret', 'somefakesecret', 'local_smartmedia');
        set_config('s3_input_bucket', 'inputbucket', 'local_smartmedia');
        set_config('s3_output_bucket', 'outputbucket', 'local_smartmedia');
        set_config('detectlabels', 1, 'local_smartmedia');
        set_config('detectmoderation', 1, 'local_smartmedia');
        set_config('detectfaces', 1, 'local_smartmedia');
        set_config('detectpeople', 1, 'local_smartmedia');
        set_config('detectsentiment', 1, 'local_smartmedia');
        set_config('detectphrases', 1, 'local_smartmedia');
        set_config('detectentities', 1, 'local_smartmedia');
        set_config('transcribe', 1, 'local_smartmedia');
    }

    /**
     * Test the video.js enabled method returns true.
     */
    public function test_videojs_enabled_true() {
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
    public function test_videojs_enabled_false() {
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
     * Test getting media types string.
     */
    public function test_get_media_types() {
        $filterplugin = new filter_smartmedia(null, array());

        // We're testing a private method, so we need to setup reflector magic.
        $method = new ReflectionMethod('filter_smartmedia', 'get_media_types');
        $method->setAccessible(true); // Allow accessing of private method.
        $proxy = $method->invoke($filterplugin); // Get result of invoked method.

        $this->assertStringContainsString('|\.m4a|\.oga|\.ogg|\.wav|\.aif|', $proxy);
    }

    /**
     * Test method that gets smart media elements.
     * The href in htis test has no smart media elements available.
     */
    public function test_get_smart_elements_no_smart() {
        $this->resetAfterTest(true);
        $filterplugin = new filter_smartmedia(null, array());

        $linkhref = 'http://moodle.local/pluginfile.php/1461/mod_label/intro/SampleVideo1mb.mp4';

        // We're testing a private method, so we need to setup reflector magic.
        $method = new ReflectionMethod('filter_smartmedia', 'get_smart_elements');
        $method->setAccessible(true); // Allow accessing of private method.
        $proxy = $method->invoke($filterplugin, $linkhref); // Get result of invoked method.

        $this->assertEmpty($proxy);
    }

    public function test_get_embed_markup_simple() {
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
    public function test_filter_smartmedia_filter_no_replace() {
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
     * Test method that gets smart media placeholder markup.
     */
    public function test_get_placeholder_markkup() {
        $this->resetAfterTest(true);

        global $DB;
        $filterplugin = new filter_smartmedia(null, array());

        $linkhref = 'http://moodle.local/pluginfile.php/1461/mod_label/intro/SampleVideo1mb.mp4';
        $fulltext = '<div class="no-overflow">'
            .'<a href="' . $linkhref . '">' . $linkhref . '</a>'
            .'</div>';

        // Setup for testing.
        $fs = new file_storage();

        // Mock the initial file record from which conversions were made.
        $initialfilerecord = array (
            'contextid' => 31,
            'component' => 'mod_forum',
            'filearea' => 'attachment',
            'itemid' => 2,
            'filepath' => '/',
            'filename' => 'myfile1.mp4');
        $initialfile = $fs->create_file_from_string($initialfilerecord, 'the first test file');
        $contenthash = $initialfile->get_contenthash();

        // Add a successful conversion status for this file.
        $conversionrecord = new \stdClass();
        $conversionrecord->pathnamehash = $contenthash;
        $conversionrecord->contenthash = $contenthash;
        $conversionrecord->status = 201;
        $conversionrecord->transcribe_status = 201;
        $conversionrecord->rekog_label_status = 201;
        $conversionrecord->rekog_moderation_status = 201;
        $conversionrecord->rekog_face_status = 201;
        $conversionrecord->rekog_person_status = 201;
        $conversionrecord->detect_sentiment_status = 201;
        $conversionrecord->detect_phrases_status = 201;
        $conversionrecord->detect_entities_status = 201;
        $conversionrecord->timecreated = time();
        $conversionrecord->timemodified = time();

        $href = moodle_url::make_pluginfile_url(
            $initialfilerecord['contextid'], $initialfilerecord['component'], $initialfilerecord['filearea'],
            $initialfilerecord['itemid'], $initialfilerecord['filepath'], $initialfilerecord['filename']);

        // We're testing a private method, so we need to setup reflector magic.
        $method = new ReflectionMethod('filter_smartmedia', 'get_placeholder_markkup');
        $method->setAccessible(true); // Allow accessing of private method.

        $proxy = $method->invoke($filterplugin, $linkhref, $fulltext); // Get result of invoked method.
        $this->assertStringNotContainsString('local-smartmedia-placeholder-container', $proxy);

        $proxy = $method->invoke($filterplugin, $href, $fulltext); // Get result of invoked method.
        $this->assertStringContainsString('local-smartmedia-placeholder-container', $proxy);

        $DB->insert_record('local_smartmedia_conv', $conversionrecord);
        $proxy = $method->invoke($filterplugin, $href, $fulltext); // Get result of invoked method.
        $this->assertStringContainsString('local-smartmedia-placeholder-container', $proxy);
    }

}
