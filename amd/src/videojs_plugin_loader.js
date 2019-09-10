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
 * Workflow step select javascript.
 *
 * @module     filter_smartmedia/videojs_plugin_loader
 * @package    filter_smartmedia
 * @class      VideoJsPluginLoader
 * @copyright  2019 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      3.8
 */

define(
  ['jquery', 'media_videojs/video-lazy'],
        function ($, videojs) {

            /**
             * Module level variables.
             */
            var VideoJsPluginLoader = {};
            
            function examplePlugin(options) {
                window.console.log('Im loaded');
                this.on('play', function(e) {
                    window.console.log('playback has started!');
                  });
              }


            /**
             * Initialise the class.
             *
             * @public
             */
            VideoJsPluginLoader.init = function(context) {
                window.console.log('HEHEHEEERERERERE');
                videojs.registerPlugin('someshithouseplugin', examplePlugin);
                //var oldPlayer = document.getElementById("id_videojs_2");
               // videojs(oldPlayer).dispose();
              //  oldPlayer.someshithouseplugin();
                window.videojs = videojs;

            };

            return VideoJsPluginLoader;
        });
