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
 * Backup task definition for mod_randomdraw.
 *
 * @package    mod_randomdraw
 * @copyright  2026 Markus Maurer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/randomdraw/backup/moodle2/backup_randomdraw_stepslib.php');

/**
 * Backup task for mod_randomdraw.
 */
class backup_randomdraw_activity_task extends backup_activity_task {
    /**
     * Define particular settings for this backup/restore task.
     */
    protected function define_my_settings(): void {
    }

    /**
     * Define the steps for this backup/restore task.
     */
    protected function define_my_steps(): void {
        $this->add_step(new backup_randomdraw_activity_structure_step(
            'randomdraw_structure',
            'randomdraw.xml'
        ));
    }

    /**
     * Encode content links for backup.
     *
     * @param string $content the content to process
     * @return string the processed content
     */
    public static function encode_content_links($content): string {
        global $CFG;
        $base = preg_quote($CFG->wwwroot, '/');
        $content = preg_replace(
            '/(' . $base . '\/mod\/randomdraw\/view\.php\?id=)([0-9]+)/',
            '$@RANDOMDRAWVIEWBYID*$2@$',
            $content
        );
        return $content;
    }
}
