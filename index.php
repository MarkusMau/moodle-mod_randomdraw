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
 * Lists all randomdraw activities in a course.
 *
 * @package    mod_randomdraw
 * @copyright  2026 Markus Maurer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/randomdraw/lib.php');

$id = required_param('id', PARAM_INT); // Course ID.

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_login($course);

$PAGE->set_url('/mod/randomdraw/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context(context_course::instance($course->id));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_randomdraw'));

$cms = get_fast_modinfo($course)->get_instances_of('randomdraw');
if (empty($cms)) {
    notice(
        get_string('nothingtodisplay'),
        new moodle_url('/course/view.php', ['id' => $course->id])
    );
}

echo '<table class="generaltable">';
echo '<thead><tr>';
echo '<th>' . get_string('activityname', 'mod_randomdraw') . '</th>';
echo '<th>' . get_string('range', 'mod_randomdraw') . '</th>';
echo '<th>' . get_string('totaldraws', 'mod_randomdraw') . '</th>';
echo '</tr></thead><tbody>';

foreach ($cms as $cm) {
    if (!$cm->uservisible) {
        continue;
    }
    $instance = $DB->get_record('randomdraw', ['id' => $cm->instance]);
    if (!$instance) {
        continue;
    }
    $freq       = randomdraw_get_frequency($instance->id, $instance->range_min, $instance->range_max);
    $totaldraws = array_sum($freq);

    $url = new moodle_url('/mod/randomdraw/view.php', ['id' => $cm->id]);
    echo '<tr>';
    echo '<td><a href="' . $url . '">' . format_string($cm->name) . '</a></td>';
    echo '<td>' . $instance->range_min . ' – ' . $instance->range_max . '</td>';
    echo '<td>' . $totaldraws . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';
echo $OUTPUT->footer();
