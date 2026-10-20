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
 * Restore structure steps for mod_randomdraw.
 *
 * @package    mod_randomdraw
 * @copyright  2026 Markus Maurer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step for restoring a randomdraw activity.
 */
class restore_randomdraw_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define the backup/restore structure.
     *
     * @return mixed the prepared structure
     */
    protected function define_structure(): array {
        $paths   = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('randomdraw', '/activity/randomdraw');

        if ($userinfo) {
            $paths[] = new restore_path_element('randomdraw_draw', '/activity/randomdraw/draws/draw');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process a restored randomdraw element.
     *
     * @param array $data the element data
     */
    protected function process_randomdraw(array $data): void {
        global $DB;
        $data = (object)$data;
        $data->course       = $this->get_courseid();
        $data->timemodified = time();

        // Defensively strip any legacy fields that no longer exist in the
        // current schema, so restoring old backups does not fail.
        unset($data->current_round, $data->allow_redraw, $data->round);

        // Ensure new required fields have sane defaults for old backups.
        if (!isset($data->min_draws)) {
            $data->min_draws = 1;
        }
        if (!isset($data->max_draws)) {
            $data->max_draws = 0;
        }

        $newid = $DB->insert_record('randomdraw', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Process a restored draw element.
     *
     * @param array $data the element data
     */
    protected function process_randomdraw_draw(array $data): void {
        global $DB;
        $data = (object)$data;
        $data->randomdrawid = $this->get_new_parentid('randomdraw');
        $data->userid       = $this->get_mappingid('user', $data->userid);

        // Defensive: drop legacy 'round' column from old backups.
        unset($data->round);

        $DB->insert_record('randomdraw_draws', $data);
    }

    /**
     * Actions to run after the restore has executed.
     */
    protected function after_execute(): void {
        $this->add_related_files('mod_randomdraw', 'intro', null);
    }
}
