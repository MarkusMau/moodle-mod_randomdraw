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
 * Privacy Subsystem implementation for mod_randomdraw.
 *
 * @package    mod_randomdraw
 * @copyright  2026 Markus Maurer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_randomdraw\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context;
use context_module;

/**
 * Privacy provider storing which number(s) each user drew, and when.
 *
 * @package    mod_randomdraw
 * @copyright  2026 Markus Maurer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data stored by this plugin.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'randomdraw_draws',
            [
                'userid'    => 'privacy:metadata:randomdraw_draws:userid',
                'number'    => 'privacy:metadata:randomdraw_draws:number',
                'timedrawn' => 'privacy:metadata:randomdraw_draws:timedrawn',
            ],
            'privacy:metadata:randomdraw_draws'
        );
        return $collection;
    }

    /**
     * Get the list of contexts that contain data for the given user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {randomdraw_draws} d
                  JOIN {randomdraw} r       ON r.id = d.randomdrawid
                  JOIN {course_modules} cm  ON cm.instance = r.id
                  JOIN {modules} m          ON m.id = cm.module AND m.name = :modname
                  JOIN {context} ctx        ON ctx.instanceid = cm.id
                                           AND ctx.contextlevel = :contextlevel
                 WHERE d.userid = :userid";

        $contextlist->add_from_sql($sql, [
            'modname'      => 'randomdraw',
            'contextlevel' => CONTEXT_MODULE,
            'userid'       => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data in a given context.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        $sql = "SELECT d.userid
                  FROM {randomdraw_draws} d
                  JOIN {randomdraw} r       ON r.id = d.randomdrawid
                  JOIN {course_modules} cm  ON cm.instance = r.id
                  JOIN {modules} m          ON m.id = cm.module AND m.name = :modname
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, [
            'modname' => 'randomdraw',
            'cmid'    => $context->instanceid,
        ]);
    }

    /**
     * Export all user data for the approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('randomdraw', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $draws = $DB->get_records('randomdraw_draws', [
                'randomdrawid' => $cm->instance,
                'userid'       => $user->id,
            ], 'timedrawn ASC');

            if (empty($draws)) {
                continue;
            }

            $data = [];
            foreach ($draws as $draw) {
                $data[] = [
                    'number'    => $draw->number,
                    'timedrawn' => \core_privacy\local\request\transform::datetime($draw->timedrawn),
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('privacy:drawspath', 'mod_randomdraw')],
                (object)['draws' => $data]
            );
        }
    }

    /**
     * Delete all data for all users in a context.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('randomdraw', $context->instanceid);
        if (!$cm) {
            return;
        }

        $DB->delete_records('randomdraw_draws', ['randomdrawid' => $cm->instance]);
    }

    /**
     * Delete data for a single user across the approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('randomdraw', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $DB->delete_records('randomdraw_draws', [
                'randomdrawid' => $cm->instance,
                'userid'       => $user->id,
            ]);
        }
    }

    /**
     * Delete data for multiple users in a single context.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('randomdraw', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = array_merge(['randomdrawid' => $cm->instance], $inparams);
        $DB->delete_records_select(
            'randomdraw_draws',
            "randomdrawid = :randomdrawid AND userid $insql",
            $params
        );
    }
}
