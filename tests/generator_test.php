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

/**
 * Tests for the mod_randomdraw data generator.
 *
 * @package    mod_randomdraw
 * @category   test
 * @copyright  2026 Markus Maurer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_randomdraw_generator
 */
final class generator_test extends advanced_testcase {
    /**
     * The generator creates an instance with defaults and with explicit values.
     */
    public function test_create_instance(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $this->assertSame(0, $DB->count_records('randomdraw'));

        $instance = $this->getDataGenerator()->create_module('randomdraw', ['course' => $course->id]);
        $this->assertSame(1, $DB->count_records('randomdraw'));
        $record = $DB->get_record('randomdraw', ['id' => $instance->id], '*', MUST_EXIST);
        $this->assertEquals(1, $record->range_min);
        $this->assertEquals(10, $record->range_max);
        $this->assertEquals(1, $record->min_draws);
        $this->assertEquals(0, $record->max_draws);

        $cm = get_coursemodule_from_instance('randomdraw', $instance->id);
        $this->assertEquals($course->id, $cm->course);

        $custom = $this->getDataGenerator()->create_module('randomdraw', [
            'course'    => $course->id,
            'name'      => 'Custom draw',
            'range_min' => 3,
            'range_max' => 30,
            'min_draws' => 2,
            'max_draws' => 5,
        ]);
        $record = $DB->get_record('randomdraw', ['id' => $custom->id], '*', MUST_EXIST);
        $this->assertSame('Custom draw', $record->name);
        $this->assertEquals(3, $record->range_min);
        $this->assertEquals(30, $record->range_max);
        $this->assertEquals(2, $record->min_draws);
        $this->assertEquals(5, $record->max_draws);
    }
}
