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
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/randomdraw/lib.php');

/**
 * Unit tests for the mod_randomdraw library functions.
 *
 * @package    mod_randomdraw
 * @category   test
 * @copyright  2026 Markus Maurer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \randomdraw_draw_number
 * @covers     \randomdraw_get_frequency
 * @covers     \randomdraw_distribution_deviation
 * @covers     \randomdraw_get_pool_status
 * @covers     \randomdraw_update_instance
 * @covers     \randomdraw_delete_instance
 */
final class lib_test extends advanced_testcase {
    /**
     * Create a course, an activity instance and a number of enrolled students.
     *
     * @param array $instancedata overrides for the instance record
     * @param int $students number of students to enrol
     * @return array [instance, course, students]
     */
    private function setup_activity(array $instancedata = [], int $students = 1): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->create_module('randomdraw', ['course' => $course->id] + $instancedata);

        $users = [];
        for ($i = 0; $i < $students; $i++) {
            $user = $generator->create_user();
            $generator->enrol_user($user->id, $course->id, 'student');
            $users[] = $user;
        }
        return [$instance, $course, $users];
    }

    /**
     * A draw returns a number inside the configured range and stores it.
     */
    public function test_draw_returns_number_in_range(): void {
        global $DB;
        $this->resetAfterTest();
        [$instance, , $users] = $this->setup_activity(['range_min' => 5, 'range_max' => 9]);

        $number = randomdraw_draw_number($instance->id, $users[0]->id);

        $this->assertGreaterThanOrEqual(5, $number);
        $this->assertLessThanOrEqual(9, $number);
        $record = $DB->get_record('randomdraw_draws', ['randomdrawid' => $instance->id, 'userid' => $users[0]->id]);
        $this->assertEquals($number, $record->number);
        $this->assertSame(1, randomdraw_draw_count($instance->id, $users[0]->id));
    }

    /**
     * The maximum number of draws per student is enforced and a rejected draw stores nothing.
     */
    public function test_max_draws_is_enforced(): void {
        $this->resetAfterTest();
        [$instance, , $users] = $this->setup_activity(['max_draws' => 2]);
        $userid = $users[0]->id;

        randomdraw_draw_number($instance->id, $userid);
        randomdraw_draw_number($instance->id, $userid);
        $this->assertSame(2, randomdraw_draw_count($instance->id, $userid));

        try {
            randomdraw_draw_number($instance->id, $userid);
            $this->fail('Expected maxdrawsreached exception was not thrown.');
        } catch (moodle_exception $e) {
            $this->assertSame('maxdrawsreached', $e->errorcode);
        }
        $this->assertSame(2, randomdraw_draw_count($instance->id, $userid), 'A rejected draw must not be stored.');
    }

    /**
     * A maximum of 0 means unlimited draws.
     */
    public function test_zero_max_draws_means_unlimited(): void {
        $this->resetAfterTest();
        [$instance, , $users] = $this->setup_activity(['max_draws' => 0, 'range_min' => 1, 'range_max' => 3]);

        for ($i = 0; $i < 20; $i++) {
            randomdraw_draw_number($instance->id, $users[0]->id);
        }
        $this->assertSame(20, randomdraw_draw_count($instance->id, $users[0]->id));
    }

    /**
     * Draws are balanced: no number is ever drawn more than once more often than any other.
     *
     * @dataProvider balance_provider
     * @param int $min lower bound of the range
     * @param int $max upper bound of the range
     * @param int $students number of students
     * @param int $drawseach draws per student
     */
    public function test_distribution_stays_balanced(int $min, int $max, int $students, int $drawseach): void {
        $this->resetAfterTest();
        [$instance, , $users] = $this->setup_activity(['range_min' => $min, 'range_max' => $max], $students);

        foreach ($users as $user) {
            for ($i = 0; $i < $drawseach; $i++) {
                randomdraw_draw_number($instance->id, $user->id);
            }
        }

        $freq = randomdraw_get_frequency($instance->id, $min, $max);
        $this->assertCount($max - $min + 1, $freq, 'Frequency map must cover the whole range.');
        $this->assertSame($students * $drawseach, array_sum($freq));
        $this->assertLessThanOrEqual(1, randomdraw_distribution_deviation($freq));
    }

    /**
     * Data provider for test_distribution_stays_balanced.
     *
     * @return array[]
     */
    public static function balance_provider(): array {
        return [
            'fewer draws than numbers' => [1, 20, 3, 2],
            'exactly one round'        => [1, 10, 5, 2],
            'several rounds'           => [1, 6, 4, 5],
            'single number range'      => [7, 7, 2, 3],
            'offset range'             => [100, 104, 3, 4],
        ];
    }

    /**
     * Every number is drawn once before any number is drawn twice.
     */
    public function test_each_number_drawn_once_before_repeat(): void {
        $this->resetAfterTest();
        [$instance, , $users] = $this->setup_activity(['range_min' => 1, 'range_max' => 8]);

        $drawn = [];
        for ($i = 0; $i < 8; $i++) {
            $drawn[] = randomdraw_draw_number($instance->id, $users[0]->id);
        }
        sort($drawn);
        $this->assertSame(range(1, 8), $drawn);
    }

    /**
     * Frequency map and overview reflect all users' draws.
     */
    public function test_get_all_draws_groups_by_user(): void {
        $this->resetAfterTest();
        [$instance, , $users] = $this->setup_activity([], 2);

        randomdraw_draw_number($instance->id, $users[0]->id);
        randomdraw_draw_number($instance->id, $users[0]->id);
        randomdraw_draw_number($instance->id, $users[1]->id);

        $all = randomdraw_get_all_draws($instance->id);
        $this->assertCount(2, $all);
        $this->assertCount(2, $all[$users[0]->id]['draws']);
        $this->assertCount(1, $all[$users[1]->id]['draws']);
        $this->assertSame(fullname($users[0]), $all[$users[0]->id]['fullname']);

        $mine = randomdraw_my_draws($instance->id, $users[0]->id);
        $this->assertCount(2, $mine);
    }

    /**
     * Deleting the instance removes all of its draws and nothing else.
     */
    public function test_delete_instance_removes_draws(): void {
        global $DB;
        $this->resetAfterTest();
        [$instance, $course, $users] = $this->setup_activity();
        $other = $this->getDataGenerator()->create_module('randomdraw', ['course' => $course->id]);

        randomdraw_draw_number($instance->id, $users[0]->id);
        randomdraw_draw_number($other->id, $users[0]->id);

        $this->assertTrue(randomdraw_delete_instance($instance->id));

        $this->assertFalse($DB->record_exists('randomdraw', ['id' => $instance->id]));
        $this->assertSame(0, $DB->count_records('randomdraw_draws', ['randomdrawid' => $instance->id]));
        $this->assertSame(1, $DB->count_records('randomdraw_draws', ['randomdrawid' => $other->id]));
        $this->assertFalse(randomdraw_delete_instance($instance->id), 'Deleting twice must return false.');
    }

    /**
     * The pool status reports how many numbers are in the least-drawn tier.
     */
    public function test_get_pool_status(): void {
        $this->resetAfterTest();
        [$instance, , $users] = $this->setup_activity(['range_min' => 1, 'range_max' => 4]);

        [$remaining, $total] = randomdraw_get_pool_status($instance->id, 1, 4);
        $this->assertSame(4, $total);
        $this->assertSame(4, $remaining, 'Before any draw every number is in the next tier.');

        randomdraw_draw_number($instance->id, $users[0]->id);
        [$remaining, $total] = randomdraw_get_pool_status($instance->id, 1, 4);
        $this->assertSame(3, $remaining);

        for ($i = 0; $i < 3; $i++) {
            randomdraw_draw_number($instance->id, $users[0]->id);
        }
        [$remaining] = randomdraw_get_pool_status($instance->id, 1, 4);
        $this->assertSame(4, $remaining, 'After a full round all numbers are in the next tier again.');
    }

    /**
     * Updating an instance stores the new settings and clamps invalid limits.
     */
    public function test_update_instance(): void {
        global $DB;
        $this->resetAfterTest();
        [$instance] = $this->setup_activity();

        $data = (object)[
            'instance'  => $instance->id,
            'name'      => 'Renamed',
            'range_min' => 10,
            'range_max' => 20,
            'min_draws' => 0,
            'max_draws' => -5,
        ];
        $this->assertTrue(randomdraw_update_instance($data));

        $record = $DB->get_record('randomdraw', ['id' => $instance->id], '*', MUST_EXIST);
        $this->assertSame('Renamed', $record->name);
        $this->assertEquals(10, $record->range_min);
        $this->assertEquals(20, $record->range_max);
        $this->assertEquals(1, $record->min_draws, 'min_draws is clamped to at least 1.');
        $this->assertEquals(0, $record->max_draws, 'max_draws is clamped to at least 0.');
        $this->assertGreaterThan(0, $record->timemodified);
    }

    /**
     * Supported features are reported correctly.
     */
    public function test_supports(): void {
        $this->assertTrue(randomdraw_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertTrue(randomdraw_supports(FEATURE_MOD_INTRO));
        $this->assertFalse(randomdraw_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertNull(randomdraw_supports('mod_randomdraw_no_such_feature'));
    }
}
