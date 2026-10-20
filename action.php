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
 * Handles draw and reset actions for mod_randomdraw.
 *
 * @package    mod_randomdraw
 * @copyright  2026 Markus Maurer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/randomdraw/lib.php');

$cmid   = required_param('cmid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'randomdraw');
$instance = $DB->get_record('randomdraw', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
require_sesskey();
$context = context_module::instance($cm->id);

$returnurl = new moodle_url('/mod/randomdraw/view.php', ['id' => $cmid]);

switch ($action) {
    case 'draw':
        require_capability('mod/randomdraw:draw', $context);
        try {
            $number = randomdraw_draw_number($instance->id, $USER->id);
            $returnurl->param('drawn', $number);
        } catch (moodle_exception $e) {
            // Only the two expected control-flow signals are silently.
            // Ignored (user hit their max, or the activity was briefly.
            // Locked by another draw). Any other error is a real.
            // Problem and must NOT be hidden - re-throw it so Moodle.
            // Logs and reports it properly.
            if (
                $e->errorcode !== 'maxdrawsreached'
                    && $e->errorcode !== 'drawbusy'
            ) {
                throw $e;
            }
        }
        redirect($returnurl);
        break;

    case 'resetall':
        require_capability('mod/randomdraw:manage', $context);
        // Deleting all draw records is enough to fully reset, because the
        // frequency-balanced algorithm derives everything from them.
        $DB->delete_records('randomdraw_draws', ['randomdrawid' => $instance->id]);
        $DB->set_field('randomdraw', 'timemodified', time(), ['id' => $instance->id]);
        redirect($returnurl);
        break;

    default:
        redirect($returnurl);
}
