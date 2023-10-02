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
        'aac', // Type: audio/aac.
        'au', // Type: audio/au.
        'mp3', // Type: audio/mp3.
        'm4a', // Type: audio/mp4.
        'oga', // Type: audio/ogg.
        'ogg', // Type: audio/ogg.
        'wav', // Type: audio/wav.
        'aif', // Type: audio/x-aiff.
        'aiff', // Type: audio/x-aiff.
        'aifc', // Type: audio/x-aiff.
        'm3u', // Type: audio/x-mpegurl.
        'wma', // Type: audio/x-ms-wma.
        'ram', // Type: audio/x-pn-realaudio-plugin.
        'rm', // Type: audio/x-pn-realaudio-plugin.
        'rv', // Type: audio/x-pn-realaudio-plugin.
        'mp4', // Type: video/mp4.
        'm4v', // Type: video/mp4.
        'f4v', // Type: video/mp4.
        'mpeg', // Type: video/mpeg.
        'mpe', // Type: video/mpeg.
        'mpg', // Type: video/mpeg.
        'ogv', // Type: video/ogg.
        'qt', // Type: video/quicktime.
        '3gp', // Type: video/quicktime.
        'mov', // Type: video/quicktime.
        'webm', // Type: video/webm.
        'dv', // Type: video/x-dv.
        'dif', // Type: video/x-dv.
        'flv', // Type: video/x-flv.
        'asf', // Type: video/x-ms-asf.
        'avi', // Type: video/x-ms-wm.
        'wmv', // Type: video/x-ms-wmv.
    );

    /**
     * Types of media that most browsers will play natively.
     *
     * @var array
     */
    private $browsernative = array(
        '.mp3', // Type: audio/mp3.
        '.ogg', // Type: audio/ogg.
        '.mp4', // Type: video/mp4.
        '.webm', // Type: video/webm.
    );

    /**
     * Conversion controller used for filtering.
     *
     * @var \local_smartmedia\conversion
     */
    private $conversion;

    /**
     * Set any context-specific configuration for this filter.
     *
     * @param context $context The current context.
     * @param array $localconfig Any context-specific configuration for this filter.
     */
    public function __construct($context, array $localconfig, \local_smartmedia\conversion $conversion = null) {
        parent::__construct($context, $localconfig);

        if (!empty($conversion)) {
            $this->conversion = $conversion;
        } else {
            $api = new aws_api();
            $transcoder = new aws_elastic_transcoder($api->create_elastic_transcoder_client());
            $this->conversion = new \local_smartmedia\conversion($transcoder);
        }
    }

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
     * Get the media container types that generally supported by browsers.
     *
     * @return string $typestring String of supported types.
     */
    private function get_browser_native_types() : string {
        $typestring = '\\'. implode('|\\', $this->browsernative);

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

        $smartmedia = $this->conversion->get_smart_media($moodleurl);

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
                    'download' => $smartmedia['download'],
                    'metadata' => $smartmedia['data']
            );
        }

        return [$smartmedia['context'], $elements];
    }

    /**
     * Check if string ends with.
     *
     * @param string $haystack The string to search in.
     * @param string $needle The string to search for.
     * @return bool Result of string check.
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
     * @param string $linkhref Original media href.
     * @param array $urls Source file Moodle URLs.
     * @param array $options Options for the player.
     * @param array $download Download Moodle URLs.
     * @param bool $hasdata Whether there is metadata associated with this smartmedia.
     * @return string $newtext Rendered VideoJS markup.
     */
    private function get_embed_markup(string $linkhref, array $urls, array $options, array $download, bool $hasdata) : string {
        global $OUTPUT;

        $name = $options['name'];
        $width = $options['width'];
        $height = $options['height'];
        $embedoptions = array();
        $downloaddata = '<video ';

        $videojs = new \media_videojs_plugin();
        $newtext = $videojs->embed($urls, $name, $width, $height, $embedoptions);
        // TODO: Deal with fallback link.

        // We need to tweak the data-setup-lazy value so that it has html5->hls->enableLowInitialPlaylist enabled.
        // This passes the enableLowInitialPlaylist value to the videojs JS, which fixes audio-only
        // streams being played when the site bandwidth is too low.
        // Regex and str_replace are used so that we don't have to touch the Moodle core videojs code.
        $pattern = '/data-setup-lazy=["\']([^"\']+)["\']/i';
        $matches = [];

        if (preg_match($pattern, $newtext, $matches)) {
            // Note $matches[1] here is just what was matched.
            // since it is the first parenthesized subpattern matched.
            // see https://www.php.net/manual/en/function.preg-match.php.
            $originalvalue = $matches[1];

            $decoded = json_decode(htmlspecialchars_decode($originalvalue));
            $decoded->html5 = [
                'hls' => [
                    'enableLowInitialPlaylist' => true
                ]
            ];

            $newvalue = htmlspecialchars(json_encode($decoded));
            $newtext = str_replace($originalvalue, $newvalue, $newtext);
        }

        // Add download URLs as data to the video tag.
        if (!empty($download)) {
            foreach ($download as $url) {
                if ($this->string_ends_with($url->out(), '.mp4')) {
                    $downloaddata .= 'data-download-video="' . $url->out(true, array('forcedownload' => true)). '" ';
                } else if ($this->string_ends_with($url->out(), '.mp3')) {
                    $downloaddata .= 'data-download-audio="' . $url->out(true, array('forcedownload' => true)). '" ';
                }
            }
            $newtext = preg_replace('/\<video /', $downloaddata, $newtext);
        }

        // Display download link only if there is data, and the user is a siteadmin.
        if ($hasdata && is_siteadmin()) {
            // Explode the url to get the filename component for naming.
            $components = explode('/', $linkhref);
            $newtext .= $OUTPUT->single_button(
                new moodle_url('/filter/smartmedia/download_metadata.php', [
                    'sesskey' => sesskey(),
                    'conv' => base64_encode($linkhref),
                    'title' => base64_encode(end($components))
                ]),
                get_string('downloadmetadata', 'filter_smartmedia')
            ) . '';
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
    private function get_placeholder_markup(string $linkhref, string $fulltext) : string {
        global $OUTPUT;
        $moodleurl = new \moodle_url($linkhref);
        $path = $moodleurl->get_path();
        $args = explode('/', $path);
        $filename = array_pop($args);

        // Cleanup the filename a bit.
        $filename = urldecode($filename);
        if (strlen($filename) > 30) {
            $filename = substr($filename, 0, 30) . '...';
        }

        $markup = $fulltext;
        $context = new \stdClass();

        // If file is of type that is browser native,
        // don't show placeholder.
        $nativetypes = $this->get_browser_native_types();
        $re = '~\<a\s[^>]*href\=[\"\'](.*pluginfile\.php.*[' . $nativetypes .'])[\"\'][^>]*\>\X*?\<\/a\>~';
        $isnative = preg_match($re, $markup);
        if ($isnative) {
            return $markup;
        }

        // Get status of conversion.
        $conversionstatus = $this->conversion->will_convert($moodleurl);

        if ($conversionstatus != $this->conversion::CONVERSION_ERROR) {
            $context->linkhref = $linkhref;
            $context->filename = $filename;
            $markup = $OUTPUT->render_from_template('filter_smartmedia/placeholder', $context);
        }

        return $markup;
    }

    /**
     * Given a matched link check if there is smartmedia available,
     * and return updated link if there is.
     *
     * @param array $matches An array of link matches.
     * @return array Array of newtext and whether the text was replaced
     */
    private function replace($target, $fulltext) : array {
        global $OUTPUT, $SESSION;

        list($context, $elements) = $this->get_smart_elements($target); // Get the smartmedia elements if they exist.
        $placeholder = get_config('filter_smartmedia', 'enableplaceholder');
        $lookback = get_config('local_smartmedia', 'convertfrom');

        if (empty($elements) && $placeholder) {
            // The placeholder should only be displayed if this file will actually be converted.
            // We need to verify that the file will be queued for conversion.
            // Timecreated check.
            $file = $this->conversion->get_file_from_url(new \moodle_url($target));
            if (!empty($file) && $file->get_timecreated() < time() - $lookback) {
                $placeholder = false;
            }
        }

        if (!empty($elements)) {
            $url = $this->url_from_context($context);

            $current = sha1($target);
            // If we are going to replace, first we need to check if we are viewing the source for this video.
            if (!isset($SESSION->local_smartmedia_viewsource)) {
                $SESSION->local_smartmedia_viewsource = [];
            } else {
                // Get all the current state data.
                $viewsource = $SESSION->local_smartmedia_viewsource;
                $sourceparam = optional_param('source', '', PARAM_TEXT);
                $smparam = optional_param('sm', '', PARAM_TEXT);

                // If we have the SM param here, we need to embed and remove from the list.
                if (array_key_exists($current, $viewsource)) {
                    if ($smparam === $current) {
                        unset($viewsource[$current]);
                    }
                }

                // Now if the item is still present in the array, or we have a param to view source, use source.
                $usesource = array_key_exists($current, $viewsource) || $sourceparam === $current;
                if ($usesource && has_capability('filter/smartmedia:viewsource', $context)) {
                    // Return the original markup, along with a button to swap back to smartmedia.
                    $url->param('sm', $current);
                    $button = new \single_button(
                        $url,
                        get_string('viewoptimised', 'filter_smartmedia'),
                        'get'
                    );
                    $button = \html_writer::div($OUTPUT->render($button), 'local-smartmedia-view-optimised');

                    // Output the original source media and return.
                    if (!array_key_exists($current, $viewsource)) {
                        $viewsource[$current] = true;
                        $SESSION->local_smartmedia_viewsource = $viewsource;
                    }

                    // Filter out any spacing that doesn't need to be there from atto editor.
                    $fulltext = str_replace('<br>', '', $fulltext);
                    $fulltext = str_replace('&nbsp;', '', $fulltext);

                    // Put in the smartmedia wrapper to keep styling consistent.
                    $html = \html_writer::div($fulltext . $button, 'local-smartmedia-wrapper');
                    return [$html, false];
                }
                // Now store the state back into the session.
                $SESSION->local_smartmedia_viewsource = $viewsource;
            }

            // Get the complete smartmedia markup.
            $hasdata = !empty($elements['metadata']);
            $replacedlink = $this->get_embed_markup(
                $target,
                $elements['urls'],
                $elements['options'],
                $elements['download'],
                $hasdata
            );

            if (has_capability('filter/smartmedia:viewsource', $context)) {
                // Add a button to view source.
                $url->param('source', $current);
                $button = new \single_button(
                    $url,
                    get_string('viewsource', 'filter_smartmedia'),
                    'get'
                );
                // Wrap just smartmedia content inside a wrapper div for styling targeting.
                $replacedlink = \html_writer::div($replacedlink . $OUTPUT->render($button), 'local-smartmedia-wrapper');
            } else {
                $replacedlink = \html_writer::div($replacedlink, 'local-smartmedia-wrapper');
            }
            $replaced = true;
        } else if ($placeholder) {
            // If no smartmedia found add the correct placeholder markup.
            $replacedlink = \html_writer::div($this->get_placeholder_markup($target, $fulltext), 'local-smartmedia-wrapper');
            $replaced = true;
        } else {
            // Do nothing, no replacement candidate
            $replacedlink = $fulltext;
            $replaced = false;
        }

        return [$replacedlink, $replaced];
    }

    /**
     * Gets the course/page url from the context.
     *
     * @param context $context
     * @return moodle_url $url
     */
    private function url_from_context($context) {
        global $PAGE;

        // Defaults to page url.
        $url = $PAGE->url;

        // Find course/cm based on context.
        $course = null;
        $cm = null;

        if ($context instanceof \context_module) {
            list($course, $cm) = get_course_and_cm_from_cmid($context->instanceid);
        }

        if ($context instanceof \context_course) {
            $course = get_course($context->instanceid);
        }

        // If loaded via Ajax, guess the URL from the context.
        $isajax = strpos($url, 'lib/ajax/service.php') !== false;

        // Start with course, as its the most likely to exist.
        if ($isajax && !empty($course)) {
            $url = new moodle_url('/course/view.php', ['id' => $course->id]);
        }

        // Then check the course module has a URL, if so then use that instead.
        if ($isajax && !empty($cm) && !empty($cm->get_url())) {
            $url = $cm->get_url();
        }

        // Setup a page anchor if on the course page and viewing a section.
        if ($context instanceof \context_module && strpos($url, '/course/view.php') !== false && !empty($cm)) {
            $url->set_anchor('section-' . $cm->sectionnum);
        }

        return $url;
    }

    /**
     * Apply the smart media filter to the text.
     *
     * @param string $text The text to filter.
     * @param array $options Extra options.
     * @return string $newtext The filtered Text.
     */
    public function filter($text, array $options = array()) {
        global $SESSION;

        // First check the page URL's for flags. Prevents Ajax load missing them.
        if (!isset($SESSION->local_smartmedia_viewsource)) {
            $SESSION->local_smartmedia_viewsource = [];
        }

        // Get all the current state data.
        $viewsource = $SESSION->local_smartmedia_viewsource;
        $sourceparam = optional_param('source', '', PARAM_TEXT);
        $smparam = optional_param('sm', '', PARAM_TEXT);

        // If we have the SM param here, we need to embed and remove from the list.
        if (array_key_exists($smparam , $viewsource)) {
            unset($viewsource[$smparam]);
        } else if (!array_key_exists($sourceparam, $viewsource)) {
            $viewsource[$sourceparam] = true;
        }
        $SESSION->local_smartmedia_viewsource = $viewsource;

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

        if (stripos($text, '</a>') === false && stripos($text, '<video') === false) {
            // Performance shortcut - if there are no video tags, nothing can match.
            return $text;
        }

        $originaldom = new DOMDocument('1.0', 'UTF-8');

        // Add a wrapping div so DOMDocument doesnt mangle the structure.
        $loadtext = '<div>' . $text . '</div>';
        // Ensure the encoding can be loaded by the domdoc.
        $loadtext = mb_convert_encoding($loadtext, 'HTML-ENTITIES', 'UTF-8');

        // Supress warnings. HTML5 nodes currently throw warnings.
        // Use flags to prevent html and body tags from being included.
        @$originaldom->loadHTML($loadtext, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $videos = $originaldom->getElementsByTagName('video');
        // Non-manipulatble objects.
        $links = iterator_to_array($originaldom->getElementsByTagName('a'));
        $videos = iterator_to_array($originaldom->getElementsByTagName('video'));
        foreach ($videos as $video) {
            // Get the source and use the target to get the smartmedia for the file.
            $source = $video->getElementsByTagName('source');
            // If there are no sources, can we replace? Not currently.
            if (count($source) === 0) {
                continue;
            }
            $target = $source[0]->getAttribute('src');

            // Check if the target media type is compatible.
            $components = explode('/', $target);
            $ext = strtolower(pathinfo(end($components), PATHINFO_EXTENSION));
            if (stripos($target, 'pluginfile.php') === false ||
                !in_array($ext, $this->mediatypes)) {
                continue;
            }

            // Get the raw HTML for the replace target.
            $videotext = $originaldom->saveHTML($video);
            list($newtext, $replaced) = $this->replace($target, $videotext);
            // Encase in another div to prevent mangling when loading into the new domdoc.
            $newtext = '<div>' . $newtext . '</div>';
            // Encode to the domdocument usable format.
            $newtext = mb_convert_encoding($newtext, 'HTML-ENTITIES', 'UTF-8');

            // Open that as a new doc to pull the video node out.
            $tempdom = new DOMDocument('1.0', 'UTF-8');
            @$tempdom->loadHTML($newtext, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $xpath = new DomXPath($tempdom);
            $query = $xpath->query("//*[contains(@class, 'local-smartmedia-wrapper')]");

            if (count($query) === 0) {
                // Something went real wrong.
                continue;
            }
            $newvideo = $query->item(0);

            // Import that video node into the original DOM, and replace the original node.
            $imported = $originaldom->importNode($newvideo, true);
            if ($replaced) {
                // Replace target is the mediaplugin div 2 levels above the video if this is a proper videoJS embed. This swaps the whole block.
                // Otherwise we need to replace only the video element itself, so we don't accidentally eat wrapping divs in HTML content.
                $xpath = new DomXPath($originaldom);
                $query = $xpath->query('ancestor::div[contains(@class, "mediaplugin")]', $video);
                if (count($query) === 0) {
                    $replacetarget = $video;
                } else {
                    $replacetarget = $query->item(0);
                }
            } else {
                $replacetarget = $video;
            }
            $replacetarget->parentNode->replaceChild($imported, $replacetarget);
        }

        // We now need to check every <a> in the Dom.
        // If it still exists after the video replacement,
        // It will need to be smartmedia'd aswell.
        $newlinks = $originaldom->getElementsByTagName('a');
        foreach ($links as $link) {
            // Check if this link node still exists. That's the ones we want.
            $exists = false;
            foreach ($newlinks as $newlink) {
                if ($link->isSameNode($newlink)) {
                    $exists = true;
                }
            }
            if (!$exists) {
                continue;
            }

            // Perform the same data manipulations as above, using the href of the <a> as the target.
            $target = $link->getAttribute('href');

            // Check if the target media type is compatible.
            $components = explode('/', $target);
            $ext = pathinfo(end($components), PATHINFO_EXTENSION);
            if (stripos($target, 'pluginfile.php') === false ||
                !in_array($ext, $this->mediatypes)) {
                continue;
            }

            // Get the raw HTML for the replace target.
            $linktext = $originaldom->saveHTML($link);
            list($newtext, $unused) = $this->replace($target, $linktext);
            // Encase in another div to prevent mangling when loading into the new domdoc.
            $newtext = '<div>' . $newtext . '</div>';
            // Encode to the domdocument usable format.
            $newtext = mb_convert_encoding($newtext, 'HTML-ENTITIES', 'UTF-8');

            // Open that as a new doc to pull the video node out.
            $tempdom = new DOMDocument('1.0', 'UTF-8');
            @$tempdom->loadHTML($newtext, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $xpath = new DomXPath($tempdom);
            $query = $xpath->query("//*[contains(@class, 'local-smartmedia-wrapper')]");

            if (count($query) === 0) {
                // If we haven't had a code replacement, just continue.
                continue;
            }
            $newvideo = $query->item(0);

            // Import that video node into the original DOM, and replace the original node.
            $imported = $originaldom->importNode($newvideo, true);
            $link->parentNode->replaceChild($imported, $link);
        }

        $html = trim($originaldom->saveHTML());
        if (strpos($html, '<div>') === 0) {
            // The raw html minus the wrapping div.
            $html = substr($html, 5, -6);
        }

        return $html;
    }

}
