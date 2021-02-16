[![Build Status](https://travis-ci.org/catalyst/moodle-filter_smartmedia.svg?branch=master)](https://travis-ci.org/catalyst/moodle-filter_smartmedia)

# Smart Media Filter #

Smart media aims to enhance Moodle's processing and delivery of multimedia while simplifying the process of managing multimedia for teachers and students.

Smart media leverages cloud services provided through Amazon Web Services (AWS) in order to conduct video transcoding into required formats and provide additional analytics functionality for multimedia.

The Smart Media Filter (this plugin) works to help display smart media content in Moodle courses and resources.

## Supported Moodle Versions
This plugin currently supports Moodle:

* 3.9

## Plugin Installation ##

1. Clone the plugin git repo into your Moodle codebase root `git clone git@github.com:catalyst/moodle-filter_smartmedia.git filter/smartmedia`
2. This plugin also has a dependancy on the *loca/smartmedia* plugin, to install this plugin: clone the plugin git repo into your Moodle codebase root `git clone git@github.com:catalyst/moodle-local_smartmedia.git local/smartmedia`
3. Run the upgrade: `sudo -u www-data php admin/cli/upgrade` **Note:** the user may be different to www-data on your system.
4. Enable and setup plugin. See [Plugin Settings](#plugin-settings)

## Plugin Settings ##

Once the smartmedia filter plugin is installed the filter will need to be enabled via the Moodle user interface.
To do this:

1. Log into the Moodle user interface as an administrator
2. Navigate to: *Site administration > Plugins > Filters > Manage filters*
3. Change the *Active* status to *on* for the *Smart media* filter
4. Move the *Smart media* filter above the *Multimedia plugins* filter (if enabled) but below the *Activity names auto-linking* filter

## License ##

2019 Catalyst IT Australia

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <http://www.gnu.org/licenses/>.


This plugin was developed by Catalyst IT Australia:

https://www.catalyst-au.net/

<img alt="Catalyst IT" src="https://raw.githubusercontent.com/catalyst/moodle-local_smartmedia/master/pix/catalyst-logo.svg?sanitize=true" width="400">
