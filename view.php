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
 * Main view page for a randomdraw activity.
 *
 * @package    mod_randomdraw
 * @copyright  2026 Markus Maurer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/randomdraw/lib.php');

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'randomdraw');
$instance = $DB->get_record('randomdraw', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/randomdraw:view', $context);

$ismanager = has_capability('mod/randomdraw:manage', $context);
$hasdrawcap = has_capability('mod/randomdraw:draw', $context);

// Mark as viewed for completion tracking, but only when completion
// is enabled for this activity.
$completion = new completion_info($course);
if ($completion->is_enabled($cm)) {
    $completion->set_module_viewed($cm);
}

$PAGE->set_url('/mod/randomdraw/view.php', ['id' => $id]);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Since Moodle 4.0 the activity name and description are rendered by the
// core activity header, so they must not be output again here.
echo $OUTPUT->header();
$page = new \mod_randomdraw\output\view_page(
    $instance,
    $id,
    $ismanager,
    $hasdrawcap,
    (int)$USER->id,
    optional_param('drawn', 0, PARAM_INT)
);
echo $OUTPUT->render_from_template('mod_randomdraw/view', $page->export_for_template($OUTPUT));
echo $OUTPUT->footer();
