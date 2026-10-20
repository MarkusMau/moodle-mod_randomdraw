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

namespace mod_randomdraw;

use advanced_testcase;
use backup;
use backup_controller;
use backup_setting;
use restore_controller;
use restore_dbops;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/randomdraw/lib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Backup and restore tests for mod_randomdraw.
 *
 * @package    mod_randomdraw
 * @category   test
 * @copyright  2026 Markus Maurer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_randomdraw_activity_structure_step
 * @covers     \restore_randomdraw_activity_structure_step
 */
final class backup_restore_test extends advanced_testcase {
    /**
     * Back up a course and restore it into a new course.
     *
     * @param \stdClass $course the course to back up
     * @param bool $userinfo whether to include user data
     * @return int the id of the new course
     */
    private function backup_and_restore(\stdClass $course, bool $userinfo): int {
        global $USER, $CFG;
        $CFG->backup_file_logger_level = backup::LOG_NONE;

        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value($userinfo);
        $bc->execute_plan();

        // Extract the backup file into the backup temp directory, as a real restore does.
        $results = $bc->get_results();
        $backupid = 'mod_randomdraw_test_' . $course->id;
        $packer = get_file_packer('application/vnd.moodle.backup');
        $results['backup_destination']->extract_to_pathname($packer, make_backup_temp_directory($backupid));
        $bc->destroy();

        $newcourseid = restore_dbops::create_new_course('Restored', 'restored' . $course->id, $course->category);
        $rc = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }

    /**
     * Settings, intro and all draws survive a backup and restore with user data.
     */
    public function test_backup_restore_with_user_data(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();

        $course = $generator->create_course();
        $instance = $generator->create_module('randomdraw', [
            'course'    => $course->id,
            'name'      => 'Backup me',
            'intro'     => '<p>Intro text</p>',
            'range_min' => 2,
            'range_max' => 7,
            'min_draws' => 2,
            'max_draws' => 4,
        ]);
        $users = [];
        for ($i = 0; $i < 3; $i++) {
            $user = $generator->create_user();
            $generator->enrol_user($user->id, $course->id, 'student');
            randomdraw_draw_number($instance->id, $user->id);
            randomdraw_draw_number($instance->id, $user->id);
            $users[] = $user;
        }
        $expected = array_map(
            fn($d) => $d->userid . ':' . $d->number,
            $DB->get_records('randomdraw_draws', ['randomdrawid' => $instance->id])
        );
        sort($expected);

        $newcourseid = $this->backup_and_restore($course, true);

        $restored = $DB->get_record('randomdraw', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertNotEquals($instance->id, $restored->id);
        $this->assertSame('Backup me', $restored->name);
        $this->assertStringContainsString('Intro text', $restored->intro);
        $this->assertEquals(2, $restored->range_min);
        $this->assertEquals(7, $restored->range_max);
        $this->assertEquals(2, $restored->min_draws);
        $this->assertEquals(4, $restored->max_draws);

        $actual = array_map(
            fn($d) => $d->userid . ':' . $d->number,
            $DB->get_records('randomdraw_draws', ['randomdrawid' => $restored->id])
        );
        sort($actual);
        $this->assertSame($expected, $actual, 'Every draw must be restored with the same user and number.');
        $this->assertSame(
            6,
            $DB->count_records('randomdraw_draws', ['randomdrawid' => $instance->id]),
            'The original instance must be untouched.'
        );
    }

    /**
     * Without user data the settings are restored but no draws.
     */
    public function test_backup_restore_without_user_data(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();

        $course = $generator->create_course();
        $instance = $generator->create_module('randomdraw', ['course' => $course->id, 'range_max' => 15]);
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');
        randomdraw_draw_number($instance->id, $user->id);

        $newcourseid = $this->backup_and_restore($course, false);

        $restored = $DB->get_record('randomdraw', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertEquals(15, $restored->range_max);
        $this->assertSame(0, $DB->count_records('randomdraw_draws', ['randomdrawid' => $restored->id]));
    }

    /**
     * Duplicating the module inside a course (which uses backup and restore) works.
     */
    public function test_duplicate_module(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();

        $course = $generator->create_course();
        $instance = $generator->create_module('randomdraw', ['course' => $course->id, 'range_max' => 4]);
        $cm = get_coursemodule_from_instance('randomdraw', $instance->id);

        if (method_exists(\core_courseformat\local\cmactions::class, 'duplicate')) {
            // Moodle 5.2 and later.
            $cmactions = new \core_courseformat\local\cmactions($course);
            $newcm = $cmactions->duplicate($cm->id);
        } else {
            // Moodle 4.1 to 5.1 (cmactions::duplicate() does not exist yet).
            $newcm = duplicate_module($course, $cm);
        }
        $this->assertNotNull($newcm);

        $this->assertNotEquals($cm->id, $newcm->id);
        $copy = $DB->get_record('randomdraw', ['id' => $newcm->instance], '*', MUST_EXIST);
        $this->assertEquals(4, $copy->range_max);
        $this->assertSame(2, $DB->count_records('randomdraw', ['course' => $course->id]));
    }
}
