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

use \local_smartmedia\conversion;
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
        list($context, $proxy) = $method->invoke($filterplugin, $linkhref); // Get result of invoked method.

        $this->assertEmpty($context);
        $this->assertEmpty($proxy);
    }

    public function test_get_embed_markup_simple() {
        $this->resetAfterTest(true);
        $filterplugin = new filter_smartmedia(null, array());

        $linkhref = 'http://moodle.local/pluginfile.php/1461/mod_label/intro/OriginalVideo.mp4';
        $urls = array(new \moodle_url('http://moodle.local/pluginfile.php/1461/mod_label/intro/SampleVideo1mb.m3u8'));
        $options = array(
            'width' => '',
            'height' => '',
            'name' => ''
        );
        $download = array(
            new \moodle_url('http://moodle.local/pluginfile.php/1461/mod_label/intro/SampleVideo1mb.mp4'),
            new \moodle_url('http://moodle.local/pluginfile.php/1461/mod_label/intro/SampleVideo1mb.mp3'),
        );

        // We're testing a private method, so we need to setup reflector magic.
        $method = new ReflectionMethod('filter_smartmedia', 'get_embed_markup');
        $method->setAccessible(true); // Allow accessing of private method.
        $proxy = $method->invoke($filterplugin, $linkhref, $urls, $options, $download, false); // Get result of invoked method.

        $this->assertRegExp('~mediaplugin_videojs~', $proxy);
        $this->assertRegExp('~</video>~', $proxy);
        $this->assertRegExp('~ data-download-video~', $proxy);
        $this->assertRegExp('~ data-download-audio~', $proxy);
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

        $linkhref = 'http://moodle.local/pluginfile.php/1461/mod_label/intro/SampleVideo1mb.avi';
        $fulltext = '<div class="no-overflow">'
            .'<a href="' . $linkhref . '">' . $linkhref . '</a>'
            .'</div>';

        // File with uppercase extension. IOS records video to .MOV for example.
        $linkhref2 = 'http://moodle.local/pluginfile.php/1461/mod_label/intro/SampleVideo1mb.MOV';
        $fulltext2 = '<div class="no-overflow">'
            .'<a href="' . $linkhref2 . '">' . $linkhref2 . '</a>'
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
            'filename' => 'myfile1.avi');
        $initialfile = $fs->create_file_from_string($initialfilerecord, 'the first test file');
        $contenthash = $initialfile->get_contenthash();

        $initialfilerecord2 = array (
            'contextid' => 31,
            'component' => 'mod_forum',
            'filearea' => 'attachment',
            'itemid' => 3,
            'filepath' => '/',
            'filename' => 'myfile2.MOV');
        $initialfile2 = $fs->create_file_from_string($initialfilerecord2, 'the second test file');
        $contenthash2 = $initialfile2->get_contenthash();

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

        $conversionrecord2 = new \stdClass();
        $conversionrecord2->pathnamehash = $contenthash2;
        $conversionrecord2->contenthash = $contenthash2;
        $conversionrecord2->status = 201;
        $conversionrecord2->transcribe_status = 201;
        $conversionrecord2->rekog_label_status = 201;
        $conversionrecord2->rekog_moderation_status = 201;
        $conversionrecord2->rekog_face_status = 201;
        $conversionrecord2->rekog_person_status = 201;
        $conversionrecord2->detect_sentiment_status = 201;
        $conversionrecord2->detect_phrases_status = 201;
        $conversionrecord2->detect_entities_status = 201;
        $conversionrecord2->timecreated = time();
        $conversionrecord2->timemodified = time();

        $href = moodle_url::make_pluginfile_url(
            $initialfilerecord['contextid'], $initialfilerecord['component'], $initialfilerecord['filearea'],
            $initialfilerecord['itemid'], $initialfilerecord['filepath'], $initialfilerecord['filename']);

        $href2 = moodle_url::make_pluginfile_url(
            $initialfilerecord2['contextid'], $initialfilerecord2['component'], $initialfilerecord2['filearea'],
            $initialfilerecord2['itemid'], $initialfilerecord2['filepath'], $initialfilerecord2['filename']);

        // We're testing a private method, so we need to setup reflector magic.
        $method = new ReflectionMethod('filter_smartmedia', 'get_placeholder_markup');
        $method->setAccessible(true); // Allow accessing of private method.

        $proxy = $method->invoke($filterplugin, $linkhref, $fulltext); // Get result of invoked method.
        if (method_exists($this, 'assertStringNotContainsString')) {
            $this->assertStringNotContainsString('local-smartmedia-placeholder-container', $proxy);
        } else {
            $this->assertNotContains('local-smartmedia-placeholder-container', $proxy);
        }

        $proxy = $method->invoke($filterplugin, $href, $fulltext); // Get result of invoked method.
        if (method_exists($this, 'assertStringContainsString')) {
            $this->assertStringContainsString('local-smartmedia-placeholder-container', $proxy);
        } else {
            $this->assertContains('local-smartmedia-placeholder-container', $proxy);
        }

        $DB->insert_record('local_smartmedia_conv', $conversionrecord);
        $proxy = $method->invoke($filterplugin, $href, $fulltext); // Get result of invoked method.
        if (method_exists($this, 'assertStringContainsString')) {
            $this->assertStringContainsString('local-smartmedia-placeholder-container', $proxy);
        } else {
            $this->assertContains('local-smartmedia-placeholder-container', $proxy);
        }

        $proxy = $method->invoke($filterplugin, $linkhref2, $fulltext2); // Get result of invoked method.
        if (method_exists($this, 'assertStringNotContainsString')) {
            $this->assertStringNotContainsString('local-smartmedia-placeholder-container', $proxy);
        } else {
            $this->assertNotContains('local-smartmedia-placeholder-container', $proxy);
        }
        $proxy = $method->invoke($filterplugin, $href2, $fulltext2); // Get result of invoked method.
        if (method_exists($this, 'assertStringContainsString')) {
            $this->assertStringContainsString('local-smartmedia-placeholder-container', $proxy);
        } else {
            $this->assertContains('local-smartmedia-placeholder-container', $proxy);
        }
        $DB->insert_record('local_smartmedia_conv', $conversionrecord2);
        $proxy = $method->invoke($filterplugin, $href2, $fulltext2); // Get result of invoked method.
        if (method_exists($this, 'assertStringContainsString')) {
            $this->assertStringContainsString('local-smartmedia-placeholder-container', $proxy);
        } else {
            $this->assertContains('local-smartmedia-placeholder-container', $proxy);
        }
    }


    public function test_filter_replace_dataprovider() {
        // Return [text, regex to match in output, match count, mediaplugincount, url, contextkey].
        // All <video> must have 2 surrounding divs, which matches the structure of video elements from other plugins.
        // This is then targeted in the node replacement for the filter.
        // The mediaplugin count is how many divs with that class should be remaining after replacement of matching tags.
        return [
            // Test <a>, Legit video link via dashboard url.
            [
                html_writer::link('url.com/pluginfile.php/fake.mp4', 'My Fake Video'),
                '~<video~',
                1,
                1,
                '/my/',
                'system'
            ],
            // Test <a>, Legit video link via webservice url in system context.
            [
                html_writer::link('url.com/pluginfile.php/fake.mp4', 'My Fake Video'),
                '~<video~',
                1,
                1,
                '/lib/ajax/service.php',
                'system'
            ],
            // Test <a>, Legit video link via webservice url in course context.
            [
                html_writer::link('url.com/pluginfile.php/fake.mp4', 'My Fake Video'),
                '~<video~',
                1,
                1,
                '/lib/ajax/service.php',
                'course'
            ],
            // Test <a>, Legit video link via webservice url in module context.
            [
                html_writer::link('url.com/pluginfile.php/fake.mp4', 'My Fake Video'),
                '~<video~',
                1,
                1,
                '/lib/ajax/service.php',
                'module'
            ],
            // Test <a>, Legit video link via webservice url in course context.
            [
                html_writer::link('url.com/pluginfile.php/fake.mp4', 'My Fake Video'),
                '~<video~',
                1,
                1,
                '/lib/ajax/service.php',
                'course'
            ],
            // Test <a>, Legit video link via course url.
            [
                html_writer::link('url.com/pluginfile.php/fake.mp4', 'My Fake Video'),
                '~<video~',
                1,
                1,
                '/course/view.php?id=:courseid',
                'course'
            ],
            // Test <a>, Legit video link via module url.
            [
                html_writer::link('url.com/pluginfile.php/fake.mp4', 'My Fake Video'),
                '~<video~',
                1,
                1,
                '/mod/forum/view.php?id=:cmid',
                'module'
            ],
            // Test <a>, Not supported extension.
            [
                html_writer::link('url.com/pluginfile.php/fake.wtf', 'My Fake Video'),
                '~pluginfile\.php/fake\.wtf~',
                1,
                0,
                '/my/',
                'system'
            ],
            // Test <a>, Not a pluginfile.
            [
                html_writer::link('url.com/dodgypage.php/fake.mp4', 'My Fake Video'),
                '~dodgypage\.php/fake\.mp4~',
                1,
                0,
                '/my/',
                'system'
            ],
            // Test <a>, 2 legit links.
            [
                '<div>' . html_writer::link('url.com/pluginfile.php/fake.mp4', 'My Fake Video') .
                html_writer::link('url.com/pluginfile.php/fake.mp4', 'The Other Fake Video') . '</div>',
                '~<video~',
                2,
                2,
                '/my/',
                'system'
            ],
            // Test <a>, 1 legit, 1 not.
            [
                html_writer::link('url.com/pluginfile.php/fake.mp4', 'My Fake Video') .
                html_writer::link('url.com/dodgypage.php/fake.mp4', 'The Other Fake Video'),
                '~<video~',
                1,
                1,
                '/my/',
                'system'

            ],
            // Test <video>, legit element.
            [
                '<div class="mediaplugin"><div><video><source src="url.com/pluginfile.php/fake.mp4"/></video></div></div>',
                '~pluginfile\.php.*fakename\.mp4~',
                1,
                1,
                '/my/',
                'system'
            ],
            // Test <video>, bad extension.
            [
                '<div class="mediaplugin"><div><video><source src="url.com/pluginfile.php/fake.wtf"/></video></div></div>',
                '~pluginfile\.php/fake\.wtf~',
                1,
                1,
                '/my/',
                'system'
            ],
            // Test <video>, not a pluginfile.
            [
                '<div class="mediaplugin"><div><video><source src="url.com/dodgypage.php/fake.mp4"/></video></div></div>',
                '~dodgypage\.php/fake\.mp4~',
                1,
                1,
                '/my/',
                'system'
            ],
            // Test <video>, 2 legit elements.
            [
                '<div class="mediaplugin"><div><video><source src="url.com/pluginfile.php/fake.mp4"/></video></div></div>' .
                '<div><div><video><source src="url.com/pluginfile.php/fake.mp4"/></video></div></div>',
                '~pluginfile\.php.*?fakename\.mp4?~',
                2,
                2,
                '/my/',
                'system'
            ],
            // Test <video> then <a>, 2 legit elements.
            [
                '<div class="mediaplugin"><div><video><source src="url.com/pluginfile.php/fake.mp4"/></video></div></div>' .
                html_writer::link('url.com/pluginfile.php/fake.mp4', 'My Fake Video'),
                '~pluginfile\.php.*?fakename\.mp4?~',
                2,
                2,
                '/my/',
                'system'
            ],
            // Test <a> then <video>, 2 legit elements.
            [
                html_writer::link('url.com/pluginfile.php/fake.mp4', 'My Fake Video') .
                '<div><div><video><source src="url.com/pluginfile.php/fake.mp4"/></video></div></div>',
                '~pluginfile\.php.*?fakename\.mp4~',
                2,
                2,
                '/my/',
                'system'
            ],
            // Test <a> then <video>, 2 legit elements and one naughty.
            [
                html_writer::link('url.com/pluginfile.php/fake.mp4', 'My Fake Video') .
                '<div class="mediaplugin"><div><video><source src="url.com/pluginfile.php/fake.mp4"/></video></div></div>' .
                '<div class="mediaplugin"><div><video><source src="url.com/dodgypage.php/fake.mp4"/></video></div></div>',
                '~pluginfile\.php.*?fakename\.mp4~',
                2,
                3,
                '/my/',
                'system'
            ],
        ];
    }

    /**
     * @dataProvider test_filter_replace_dataprovider
     */
    public function test_filter_replace($text, $regex, $matchcount, $mediaplugincount, $pageurl, $contextkey) {
        global $PAGE;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['hiddensections' => 0, 'coursedisplay' => COURSE_DISPLAY_SINGLEPAGE]);
        $module = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);

        // Replace courseid placeholder in pageurl.
        if (strpos($pageurl, ':courseid') !== false) {
            $pageurl = str_replace(':courseid', $course->id, $pageurl);
        }

        if (strpos($pageurl, ':cmid') !== false) {
            $pageurl = str_replace(':cmid', $module->cmid, $pageurl);
        }

        switch($contextkey) {
            case 'system':
                $PAGE->set_context(\context_system::instance());
                break;
            case 'course':
                $PAGE->set_context(\context_course::instance($course->id));
                break;
            case 'module':
                $PAGE->set_context(\context_module::instance($module->cmid));
                break;
        }

        $conversion = $this->createMock(conversion::class);
        $conversion->method('get_smart_media')->willReturn([
            'media' => [
                \moodle_url::make_pluginfile_url('1', 'local_smartmedia', 'test', '1', 'fake/path', 'fakename.mp4')
            ],
            'data' => [],
            'download' => [],
            'context' => $PAGE->context
        ]);
        $PAGE->set_url(new moodle_url($pageurl));

        $filterplugin = new filter_smartmedia(null, array(), $conversion);
        $result = $filterplugin->filter($text);
        $this->assertEquals($matchcount, preg_match_all($regex, $result));

        // Count each instance of an ID, and confirm there are no collisions.
        $matches = [];
        preg_match_all('/id=".*"?/', $result, $matches);
        $filtered = array_unique($matches);
        $this->assertEquals(count($matches), count($filtered));

        // Now check that the number of 'mediaplugin' divs from multimedia filters is matching.
        $matches = [];
        preg_match_all('/class="mediaplugin/', $result, $matches);
        $this->assertEquals(count($matches[0]), $mediaplugincount);
    }

    public function test_view_source() {
        global $PAGE;
        $this->setAdminUser();

        $conversion = $this->createMock(conversion::class);
        $conversion->method('get_smart_media')->willReturn([
            'media' => [
                \moodle_url::make_pluginfile_url('1', 'local_smartmedia', 'test', '1', 'fake/path', 'fakename.mp4')
            ],
            'data' => [],
            'download' => [],
            'context' => \context::instance_by_id(1)
        ]);
        $PAGE->set_url(new moodle_url("/my/"));

        $filterplugin = new filter_smartmedia(null, array(), $conversion);
        $text = '<div><div><video><source src="url.com/pluginfile.php/fake.mp4"/></video></div></div>';
        $result = $filterplugin->filter($text);
        $this->assertRegExp('/<button.*View source media.*<\/button>/', $result);
    }

    public function test_view_optimised() {
        global $PAGE, $SESSION;
        $this->setAdminUser();

        $conversion = $this->createMock(conversion::class);
        $conversion->method('get_smart_media')->willReturn([
            'media' => [
                \moodle_url::make_pluginfile_url('1', 'local_smartmedia', 'test', '1', 'fake/path', 'fakename.mp4')
            ],
            'data' => [],
            'download' => [],
            'context' => \context::instance_by_id(1)
        ]);
        $PAGE->set_url(new moodle_url("/my/"));

        // Setup condition to view source.
        $text = '<div><div><video><source src="url.com/pluginfile.php/fake.mp4"/></video></div></div>';
        $SESSION->local_smartmedia_viewsource = [sha1('url.com/pluginfile.php/fake.mp4') => true];

        $filterplugin = new filter_smartmedia(null, array(), $conversion);
        $result = $filterplugin->filter($text);
        $this->assertRegExp('/<button.*View optimised media.*<\/button>/', $result);
    }
}
