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
 * Restore task definition for mod_randomdraw.
 *
 * @package    mod_randomdraw
 * @copyright  2026 Markus Maurer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/randomdraw/backup/moodle2/restore_randomdraw_stepslib.php');

/**
 * Restore task for mod_randomdraw.
 */
class restore_randomdraw_activity_task extends restore_activity_task {
    /**
     * Define particular settings for this backup/restore task.
     */
    protected function define_my_settings(): void {
    }

    /**
     * Define the steps for this backup/restore task.
     */
    protected function define_my_steps(): void {
        $this->add_step(new restore_randomdraw_activity_structure_step(
            'randomdraw_structure',
            'randomdraw.xml'
        ));
    }

    /**
     * Define the contents to be decoded on restore.
     *
     * @return array the decode content rules
     */
    public static function define_decode_contents(): array {
        return [
            new restore_decode_content('randomdraw', ['intro'], 'randomdraw'),
        ];
    }

    /**
     * Define the decoding rules for links on restore.
     *
     * @return array the decode rules
     */
    public static function define_decode_rules(): array {
        return [
            new restore_decode_rule(
                'RANDOMDRAWVIEWBYID',
                '/mod/randomdraw/view.php?id=$1',
                'course_module'
            ),
        ];
    }

    /**
     * Define the restore log rules.
     *
     * @return array the restore log rules
     */
    public static function define_restore_log_rules(): array {
        return [];
    }

    /**
     * Define the restore log rules for the whole course.
     *
     * @return array the restore log rules
     */
    public static function define_restore_log_rules_for_course(): array {
        return [];
    }
}
