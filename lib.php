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
 * Library functions for the mod_randomdraw activity module.
 *
 * @package    mod_randomdraw
 * @copyright  2026 Markus Maurer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Required Moodle hooks.

/**
 * Add a new randomdraw instance.
 *
 * @param stdClass $data form data from the module settings form
 * @param mod_randomdraw_mod_form|null $mform the module form instance
 * @return int the id of the newly created instance
 */
function randomdraw_add_instance(stdClass $data, ?mod_randomdraw_mod_form $mform = null): int {
    global $DB;
    $data->timecreated  = time();
    $data->timemodified = time();
    $data->min_draws    = max(1, (int)($data->min_draws ?? 1));
    $data->max_draws    = max(0, (int)($data->max_draws ?? 0));
    return $DB->insert_record('randomdraw', $data);
}

/**
 * Update an existing randomdraw instance.
 *
 * @param stdClass $data form data from the module settings form
 * @param mod_randomdraw_mod_form|null $mform the module form instance
 * @return bool true on success
 */
function randomdraw_update_instance(stdClass $data, ?mod_randomdraw_mod_form $mform = null): bool {
    global $DB;
    $data->id           = $data->instance;
    $data->timemodified = time();
    $data->min_draws    = max(1, (int)($data->min_draws ?? 1));
    $data->max_draws    = max(0, (int)($data->max_draws ?? 0));
    $DB->update_record('randomdraw', $data);
    return true;
}

/**
 * Delete a randomdraw instance and all its data.
 *
 * @param int $id the instance id to delete
 * @return bool true on success
 */
function randomdraw_delete_instance(int $id): bool {
    global $DB;
    if (!$DB->record_exists('randomdraw', ['id' => $id])) {
        return false;
    }
    $DB->delete_records('randomdraw_draws', ['randomdrawid' => $id]);
    // The pool table only exists on sites upgraded from very old versions.
    // Guard against it being absent on fresh installs.
    if ($DB->get_manager()->table_exists('randomdraw_pool')) {
        $DB->delete_records('randomdraw_pool', ['randomdrawid' => $id]);
    }
    $DB->delete_records('randomdraw', ['id' => $id]);
    return true;
}

/**
 * Declare which features this module supports.
 *
 * @param string $feature the feature constant being queried
 * @return bool|null true/false for known features, null otherwise
 */
function randomdraw_supports(string $feature): ?bool {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

// Core draw algorithm – frequency-balanced.

/**
 * Atomically draw the next number, always picking from the
 * least-drawn numbers so the global distribution stays as
 * even as possible.
 *
 * Algorithm:
 *   1. Count how many times every number in [min,max] has been
 *      drawn globally for this instance.
 *   2. Find the minimum count (= "current tier").
 *   3. Collect all numbers still in that tier.
 *   4. Pick one at random from that subset.
 *
 * Result: after N total draws the maximum deviation between any
 * two numbers is at most 1 – the theoretically best possible
 * distribution.
 */
function randomdraw_draw_number(int $randomdrawid, int $userid): int {
    global $DB;

    // Concurrency control: use Moodle's portable lock factory, which works on
    // all supported databases instead of database-specific row locking. It
    // serialises concurrent draws for the same activity, so the frequency
    // snapshot is always correct and no two simultaneous draws can pick the
    // same least-drawn number.
    $locktype = 'mod_randomdraw_draw';
    $resource = 'instance_' . $randomdrawid;
    $lockfactory = \core\lock\lock_config::get_lock_factory($locktype);

    // Wait up to 10 seconds for the lock; fail gracefully if busy.
    $lock = $lockfactory->get_lock($resource, 10);
    if (!$lock) {
        throw new moodle_exception('drawbusy', 'mod_randomdraw');
    }

    try {
        $instance = $DB->get_record('randomdraw', ['id' => $randomdrawid], '*', MUST_EXIST);

        // Enforce the per-student maximum before opening a transaction. The
        // lock is already held, so this check is still race-free, and a
        // rejected draw never leaves a database transaction open.
        if ($instance->max_draws > 0) {
            $mycount = randomdraw_draw_count($randomdrawid, $userid);
            if ($mycount >= $instance->max_draws) {
                throw new moodle_exception('maxdrawsreached', 'mod_randomdraw');
            }
        }

        $min = (int)$instance->range_min;
        $max = (int)$instance->range_max;

        $transaction = $DB->start_delegated_transaction();
        try {
            // Step 1: global draw counts per number.
            $rows = $DB->get_records_sql(
                'SELECT number, COUNT(*) AS cnt
                   FROM {randomdraw_draws}
                  WHERE randomdrawid = :rid
               GROUP BY number',
                ['rid' => $randomdrawid]
            );

            // Step 2: build frequency map.
            $freq = [];
            for ($n = $min; $n <= $max; $n++) {
                $freq[$n] = 0; // Default: never drawn.
            }
            foreach ($rows as $row) {
                $n = (int)$row->number;
                if (isset($freq[$n])) {
                    $freq[$n] = (int)$row->cnt;
                }
            }

            // Step 3: find least-drawn tier.
            $minfreq = min($freq);
            $candidates = array_keys(array_filter($freq, fn($f) => $f === $minfreq));

            // Step 4: pick randomly within the tier.
            shuffle($candidates);
            $drawn = (int)$candidates[0];

            // Record the draw.
            $DB->insert_record('randomdraw_draws', (object)[
                'randomdrawid' => $randomdrawid,
                'userid'       => $userid,
                'number'       => $drawn,
                'timedrawn'    => time(),
            ]);

            $transaction->allow_commit();
            return $drawn;
        } catch (\Throwable $e) {
            // Any failure inside the transaction must roll it back, so no
            // open transaction can ever leak into the rest of the request.
            $transaction->rollback($e);
            throw $e;
        }
    } finally {
        // Always release the lock, even on exception, so a single failed
        // draw can never block the whole activity.
        $lock->release();
    }
}

// Helper functions.

/**
 * Count how many draws a given user has made in an instance.
 *
 * @param int $randomdrawid the instance id
 * @param int $userid the user id
 * @return int the number of draws made
 */
function randomdraw_draw_count(int $randomdrawid, int $userid): int {
    global $DB;
    return (int)$DB->count_records('randomdraw_draws', [
        'randomdrawid' => $randomdrawid,
        'userid'       => $userid,
    ]);
}

/**
 * Get all draws for a user in an instance, newest first.
 *
 * @param int $randomdrawid the instance id
 * @param int $userid the user id
 * @return array the draw records
 */
function randomdraw_my_draws(int $randomdrawid, int $userid): array {
    global $DB;
    return array_values($DB->get_records('randomdraw_draws', [
        'randomdrawid' => $randomdrawid,
        'userid'       => $userid,
    ], 'timedrawn DESC'));
}

/**
 * Get all draws for all users, grouped by user id with full name.
 *
 * @param int $randomdrawid the instance id
 * @return array draws grouped by user id
 */
function randomdraw_get_all_draws(int $randomdrawid): array {
    global $DB;
    $sql = 'SELECT d.id, d.userid, d.number, d.timedrawn,
                   ' . $DB->sql_fullname('u.firstname', 'u.lastname') . ' AS fullname
              FROM {randomdraw_draws} d
              JOIN {user} u ON u.id = d.userid
             WHERE d.randomdrawid = :rid
          ORDER BY d.userid, d.timedrawn ASC';
    $rows = $DB->get_records_sql($sql, ['rid' => $randomdrawid]);

    $grouped = [];
    foreach ($rows as $row) {
        $uid = $row->userid;
        $grouped[$uid]['fullname']  = $row->fullname;
        $grouped[$uid]['draws'][]   = (int)$row->number;
    }
    return $grouped;
}

/**
 * Frequency map: number => total draws across all users.
 * Returns array indexed by number for the full [min,max] range.
 */
function randomdraw_get_frequency(int $randomdrawid, int $min, int $max): array {
    global $DB;
    $rows = $DB->get_records_sql(
        'SELECT number, COUNT(*) AS cnt
           FROM {randomdraw_draws}
          WHERE randomdrawid = :rid
       GROUP BY number',
        ['rid' => $randomdrawid]
    );

    $freq = [];
    for ($n = $min; $n <= $max; $n++) {
        $freq[$n] = 0;
    }
    foreach ($rows as $row) {
        $n = (int)$row->number;
        if (isset($freq[$n])) {
            $freq[$n] = (int)$row->cnt;
        }
    }
    return $freq;
}

/**
 * Distribution quality: returns max deviation between any two numbers.
 * 0 = perfectly even, 1 = one number drawn once more than another, etc.
 */
function randomdraw_distribution_deviation(array $freq): int {
    if (empty($freq)) {
        return 0;
    }
    return max($freq) - min($freq);
}

/**
 * Pool status: how many numbers are still in the "lowest tier"
 * (i.e., have the minimum draw count). This is what will be
 * drawn from next.
 */
function randomdraw_get_pool_status(int $randomdrawid, int $min, int $max): array {
    $freq  = randomdraw_get_frequency($randomdrawid, $min, $max);
    $total = $max - $min + 1;
    if (empty($freq)) {
        return [$total, $total];
    }
    $minfreq    = min($freq);
    $remaining  = count(array_filter($freq, fn($f) => $f === $minfreq));
    return [$remaining, $total];
}
