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
 * Training Pathways Block v1.0.0 - Version information
 *
 * v1.0.0: INITIAL RELEASE — Student-facing dashboard block for Training Pathways.
 *         Shows each assigned pathway with colour-coded progress bar, stage
 *         completion breakdown, target-date countdown, and a direct link to the
 *         full view page. Gracefully hidden when local_trainingpathways is not
 *         installed. Requires local/trainingpathways:view capability (granted to
 *         students by default). No new DB tables — reads from local_tp_* tables
 *         created by local_trainingpathways. Savepoint 2026060300001.
 *
 * @package    block_trainingpathways
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'block_trainingpathways';
$plugin->version   = 2026060300001;
$plugin->requires  = 2022041900;
$plugin->supported  = [400, 500];
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0';
$plugin->dependencies = [
    'local_trainingpathways' => 2026060100,
];
