<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Language strings for mod_randomdraw (English).
 *
 * @package    mod_randomdraw
 * @copyright  2026 Markus Maurer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activityname'] = 'Activity name';
$string['allstudents'] = 'All students';
$string['deviation'] = 'Max. deviation';
$string['distribution'] = 'Number distribution';
$string['drawbtn'] = "Draw\nnumber";
$string['drawbusy'] = 'The system is busy right now. Please try again in a moment.';
$string['draws_needed'] = '{$a} more draw(s) required';
$string['draws_sofar'] = '{$a->n} of {$a->max} drawn';
$string['err_maxdrawsltmin'] = 'Maximum draws must be at least as large as the minimum.';
$string['err_maxgtmin'] = 'Maximum must be greater than minimum.';
$string['err_mindrawspositive'] = 'Minimum draws must be at least 1.';
$string['err_minpositive'] = 'Minimum must be at least 1.';
$string['err_rangetoolarge'] = 'Range cannot exceed 1000 numbers.';
$string['justdrawn'] = 'You drew number {$a}!';
$string['limitsheader'] = 'Draw limits';
$string['max_draws'] = 'Maximum draws allowed';
$string['max_draws_help'] = 'How many draws may a student make at most? (0 = unlimited)';
$string['maxdrawsreached'] = 'You have already reached the maximum number of draws.';
$string['maxreached_btn'] = "Maximum\nreached";
$string['min_draws'] = 'Minimum draws required';
$string['min_draws_help'] = 'How many draws must a student complete for the activity to be marked done?';
$string['modulename'] = 'Random draw';
$string['modulename_help'] = 'Students draw random numbers as many times as they like within configured limits. The teacher sets how many draws are required (minimum) and allowed (maximum).';
$string['modulenameplural'] = 'Random draws';
$string['mynumbers'] = 'My drawn numbers';
$string['nexttier'] = '{$a} numbers in next tier';
$string['nodraws'] = 'No one has drawn yet.';
$string['perfectdist'] = '✓ Perfectly even';
$string['pluginadministration'] = 'Random draw administration';
$string['pluginname'] = 'Random draw';
$string['poolpct'] = '{$a}% remaining';
$string['poolremaining'] = 'Pool remaining';
$string['privacy:drawspath'] = 'Drawn numbers';
$string['privacy:metadata:randomdraw_draws'] = 'Information about the numbers drawn by users.';
$string['privacy:metadata:randomdraw_draws:number'] = 'The number that was drawn.';
$string['privacy:metadata:randomdraw_draws:timedrawn'] = 'The time the number was drawn.';
$string['privacy:metadata:randomdraw_draws:userid'] = 'The ID of the user who drew the number.';
$string['progress_label'] = '{$a->n} of {$a->min} required draws';
$string['randomdraw:addinstance'] = 'Add a new random draw activity';
$string['randomdraw:draw'] = 'Draw a number';
$string['randomdraw:manage'] = 'Manage activity';
$string['randomdraw:view'] = 'View random draw activity';
$string['range'] = 'Range';
$string['range_max'] = 'Maximum';
$string['range_max_help'] = 'The largest number in the draw pool (e.g. 20).';
$string['range_min'] = 'Minimum';
$string['range_min_help'] = 'The smallest number in the draw pool (e.g. 1).';
$string['rangeheader'] = 'Number range';
$string['resetall'] = 'Reset all data';
$string['resetallconfirm'] = 'This will delete ALL draw records. Are you sure?';
$string['status_done'] = '✓ Done';
$string['status_progress'] = '{$a->n} / {$a->min} drawn';
$string['taptoget'] = 'Tap the button to draw your next game number.';
$string['task_complete'] = 'Task complete!';
$string['totaldraws'] = 'Total draws';
