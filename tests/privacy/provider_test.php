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

namespace mod_randomdraw\privacy;

use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/randomdraw/lib.php');

/**
 * Privacy provider tests for mod_randomdraw.
 *
 * @package    mod_randomdraw
 * @category   test
 * @copyright  2026 Markus Maurer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_randomdraw\privacy\provider
 */
final class provider_test extends provider_testcase {
    /** @var \stdClass the course */
    private $course;
    /** @var \stdClass the first activity instance */
    private $instance1;
    /** @var \stdClass the second activity instance */
    private $instance2;
    /** @var \stdClass a student with draws in both instances */
    private $user1;
    /** @var \stdClass a student with draws in the first instance only */
    private $user2;
    /** @var \stdClass a student without any draws */
    private $user3;

    /**
     * Set up two activities and three students; user1 draws in both, user2 in one, user3 in none.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->instance1 = $generator->create_module('randomdraw', ['course' => $this->course->id]);
        $this->instance2 = $generator->create_module('randomdraw', ['course' => $this->course->id]);

        $this->user1 = $generator->create_user();
        $this->user2 = $generator->create_user();
        $this->user3 = $generator->create_user();
        foreach ([$this->user1, $this->user2, $this->user3] as $user) {
            $generator->enrol_user($user->id, $this->course->id, 'student');
        }

        randomdraw_draw_number($this->instance1->id, $this->user1->id);
        randomdraw_draw_number($this->instance1->id, $this->user1->id);
        randomdraw_draw_number($this->instance2->id, $this->user1->id);
        randomdraw_draw_number($this->instance1->id, $this->user2->id);
    }

    /**
     * Get the module context of an instance.
     *
     * @param \stdClass $instance the instance
     * @return context_module
     */
    private function get_context(\stdClass $instance): context_module {
        $cm = get_coursemodule_from_instance('randomdraw', $instance->id);
        return context_module::instance($cm->id);
    }

    /**
     * The metadata describes the draws table.
     */
    public function test_get_metadata(): void {
        $collection = new collection('mod_randomdraw');
        $collection = provider::get_metadata($collection);
        $items = $collection->get_collection();

        $this->assertCount(1, $items);
        $this->assertSame('randomdraw_draws', $items[0]->get_name());
        $fields = $items[0]->get_privacy_fields();
        $this->assertArrayHasKey('userid', $fields);
        $this->assertArrayHasKey('number', $fields);
        $this->assertArrayHasKey('timedrawn', $fields);
    }

    /**
     * Contexts are found only for users who have drawn there.
     */
    public function test_get_contexts_for_userid(): void {
        $ctx1 = $this->get_context($this->instance1);
        $ctx2 = $this->get_context($this->instance2);

        $contextids = provider::get_contexts_for_userid($this->user1->id)->get_contextids();
        $this->assertCount(2, $contextids);
        $this->assertContainsEquals($ctx1->id, $contextids);
        $this->assertContainsEquals($ctx2->id, $contextids);

        $contextids = provider::get_contexts_for_userid($this->user2->id)->get_contextids();
        $this->assertEquals([$ctx1->id], array_values($contextids));

        $this->assertCount(0, provider::get_contexts_for_userid($this->user3->id)->get_contextids());
    }

    /**
     * Users in a context are exactly those who have drawn there.
     */
    public function test_get_users_in_context(): void {
        $userlist = new userlist($this->get_context($this->instance1), 'mod_randomdraw');
        provider::get_users_in_context($userlist);
        $userids = $userlist->get_userids();
        sort($userids);
        $expected = [$this->user1->id, $this->user2->id];
        sort($expected);
        $this->assertEquals($expected, $userids);

        $userlist = new userlist($this->get_context($this->instance2), 'mod_randomdraw');
        provider::get_users_in_context($userlist);
        $this->assertEquals([$this->user1->id], $userlist->get_userids());
    }

    /**
     * Exported data contains exactly the user's own draws.
     */
    public function test_export_user_data(): void {
        $ctx1 = $this->get_context($this->instance1);
        $this->export_context_data_for_user($this->user1->id, $ctx1, 'mod_randomdraw');

        $writer = writer::with_context($ctx1);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([get_string('privacy:drawspath', 'mod_randomdraw')]);
        $this->assertCount(2, $data->draws);
        foreach ($data->draws as $draw) {
            $this->assertGreaterThanOrEqual(1, $draw['number']);
            $this->assertLessThanOrEqual(10, $draw['number']);
            $this->assertNotEmpty($draw['timedrawn']);
        }
    }

    /**
     * Deleting all data in a context removes only that context's draws.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        provider::delete_data_for_all_users_in_context($this->get_context($this->instance1));

        $this->assertSame(0, $DB->count_records('randomdraw_draws', ['randomdrawid' => $this->instance1->id]));
        $this->assertSame(1, $DB->count_records('randomdraw_draws', ['randomdrawid' => $this->instance2->id]));
    }

    /**
     * Deleting one user's data leaves other users' data untouched.
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $ctx1 = $this->get_context($this->instance1);
        $ctx2 = $this->get_context($this->instance2);
        $contextlist = new approved_contextlist($this->user1, 'mod_randomdraw', [$ctx1->id, $ctx2->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertSame(0, $DB->count_records('randomdraw_draws', ['userid' => $this->user1->id]));
        $this->assertSame(1, $DB->count_records('randomdraw_draws', ['userid' => $this->user2->id]));
    }

    /**
     * Deleting several users in one context affects only those users in that context.
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $ctx1 = $this->get_context($this->instance1);
        $userlist = new approved_userlist($ctx1, 'mod_randomdraw', [$this->user1->id, $this->user3->id]);
        provider::delete_data_for_users($userlist);

        $this->assertSame(0, $DB->count_records(
            'randomdraw_draws',
            ['randomdrawid' => $this->instance1->id, 'userid' => $this->user1->id]
        ));
        $this->assertSame(1, $DB->count_records(
            'randomdraw_draws',
            ['randomdrawid' => $this->instance1->id, 'userid' => $this->user2->id]
        ));
        $this->assertSame(
            1,
            $DB->count_records(
                'randomdraw_draws',
                ['randomdrawid' => $this->instance2->id, 'userid' => $this->user1->id]
            ),
            'Draws in another context must not be deleted.'
        );
    }
}
