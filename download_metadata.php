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
 *  Smart media metadata download portal.
 *
 * @package    filter_smartmedia
 * @copyright  2021 Peter Burnett <peterburnett@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_admin();
confirm_sesskey();

$conv = required_param('conv', PARAM_TEXT);
$titleraw = required_param('title', PARAM_TEXT);
$convurl = new moodle_url(base64_decode($conv));
$title = base64_decode($titleraw);

// Get smartmedia elements.
$api = new \local_smartmedia\aws_api();
$transcoder = new \local_smartmedia\aws_elastic_transcoder($api->create_elastic_transcoder_client());
$conversion = new \local_smartmedia\conversion($transcoder);
// Get files instead of raw urls.
$smartmedia = $conversion->get_smart_media($convurl, false, true);

if (empty($smartmedia['data'])) {
    return;
}

// Create an empty zip to fill in data for.
$zip = new zip_archive();
$path = get_request_storage_directory() . '/' . $title . "_metadata.zip";
$zip->open($path, file_archive::CREATE);

foreach($smartmedia['data'] as $file) {
    $zip->add_file_from_string($file->get_filename(), $file->get_content());
}

$zip->close();
send_temp_file($path, basename($path));
