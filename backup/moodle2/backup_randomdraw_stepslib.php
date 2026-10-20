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
 * Backup structure steps for mod_randomdraw.
 *
 * @package    mod_randomdraw
 * @copyright  2026 Markus Maurer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step for backing up a randomdraw activity.
 */
class backup_randomdraw_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the backup/restore structure.
     *
     * @return mixed the prepared structure
     */
    protected function define_structure(): backup_nested_element {
        $userinfo = $this->get_setting_value('userinfo');

        $randomdraw = new backup_nested_element('randomdraw', ['id'], [
            'name', 'intro', 'introformat',
            'range_min', 'range_max', 'min_draws', 'max_draws',
            'timecreated', 'timemodified',
        ]);

        $draws = new backup_nested_element('draws');
        $draw  = new backup_nested_element('draw', ['id'], [
            'userid', 'number', 'timedrawn',
        ]);

        $randomdraw->add_child($draws);
        $draws->add_child($draw);

        $randomdraw->set_source_table('randomdraw', ['id' => backup::VAR_ACTIVITYID]);

        if ($userinfo) {
            $draw->set_source_table('randomdraw_draws', ['randomdrawid' => backup::VAR_PARENTID]);
            $draw->annotate_ids('user', 'userid');
        }

        $randomdraw->annotate_files('mod_randomdraw', 'intro', null);

        return $this->prepare_activity_structure($randomdraw);
    }
}
