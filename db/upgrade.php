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
 * Database upgrade steps for mod_randomdraw.
 *
 * @package    mod_randomdraw
 * @copyright  2026 Markus Maurer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Upgrade script for mod_randomdraw.
//
// Designed to run safely from ANY previous state:
// - v1.0 (rounds-based schema, with randomdraw_pool + round columns).
// - v2.0 (free-draw, pool table still present).
// - partially-upgraded / inconsistent states.
//
// Every operation is guarded with existence checks so it can never.
// Fatal on a production system, and it is fully idempotent.

/**
 * Apply the database upgrade steps for mod_randomdraw.
 *
 * @param int $oldversion the currently installed plugin version
 * @return bool true on success
 */
function xmldb_randomdraw_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // Step to free-draw schema (add min/max_draws, drop round cols).
    if ($oldversion < 2024060200) {
        $table = new xmldb_table('randomdraw');

        // Add min_draws if missing.
        $field = new xmldb_field(
            'min_draws',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'range_max'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add max_draws if missing.
        $field = new xmldb_field(
            'max_draws',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'min_draws'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Drop legacy current_round if present.
        $field = new xmldb_field('current_round');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Drop legacy allow_redraw if present.
        $field = new xmldb_field('allow_redraw');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2024060200, 'randomdraw');
    }

    // Clean up randomdraw_draws: remove legacy 'round' column safely.
    if ($oldversion < 2024060250) {
        $draws = new xmldb_table('randomdraw_draws');

        // Only act if the table actually exists.
        if ($dbman->table_exists($draws)) {
            $roundfield = new xmldb_field('round');
            if ($dbman->field_exists($draws, $roundfield)) {
                // Remove any index that includes the round column first,
                // otherwise dropping the field can fail on some engines.
                $oldidx = new xmldb_index(
                    'user_instance_round',
                    XMLDB_INDEX_UNIQUE,
                    ['userid', 'randomdrawid', 'round']
                );
                if ($dbman->index_exists($draws, $oldidx)) {
                    $dbman->drop_index($draws, $oldidx);
                }

                $dbman->drop_field($draws, $roundfield);
            }

            // Ensure the current non-unique lookup index exists.
            $newidx = new xmldb_index(
                'user_instance',
                XMLDB_INDEX_NOTUNIQUE,
                ['userid', 'randomdrawid']
            );
            if (!$dbman->index_exists($draws, $newidx)) {
                $dbman->add_index($draws, $newidx);
            }
        }

        upgrade_mod_savepoint(true, 2024060250, 'randomdraw');
    }

    // Drop the obsolete randomdraw_pool table entirely.
    if ($oldversion < 2024060300) {
        $pool = new xmldb_table('randomdraw_pool');
        if ($dbman->table_exists($pool)) {
            // The drop_table call removes the table and all its keys and indexes.
            // There is no need to drop them individually first.
            $dbman->drop_table($pool);
        }

        upgrade_mod_savepoint(true, 2024060300, 'randomdraw');
    }

    // Final consolidation, independent of the starting version: make sure
    // every legacy artefact is gone and every current field exists. Each
    // operation is guarded, so this is safe on an already clean schema.
    if ($oldversion < 2024060301) {
        $table = new xmldb_table('randomdraw');
        $draws = new xmldb_table('randomdraw_draws');

        // Current fields that must exist.
        $field = new xmldb_field(
            'min_draws',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'range_max'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field(
            'max_draws',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'min_draws'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Legacy fields on the instance table.
        foreach (['current_round', 'allow_redraw'] as $legacy) {
            $field = new xmldb_field($legacy);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }

        // Legacy round column and its index on the draws table.
        if ($dbman->table_exists($draws)) {
            $oldidx = new xmldb_index(
                'user_instance_round',
                XMLDB_INDEX_UNIQUE,
                ['userid', 'randomdrawid', 'round']
            );
            if ($dbman->index_exists($draws, $oldidx)) {
                $dbman->drop_index($draws, $oldidx);
            }
            $field = new xmldb_field('round');
            if ($dbman->field_exists($draws, $field)) {
                $dbman->drop_field($draws, $field);
            }
            $newidx = new xmldb_index(
                'user_instance',
                XMLDB_INDEX_NOTUNIQUE,
                ['userid', 'randomdrawid']
            );
            if (!$dbman->index_exists($draws, $newidx)) {
                $dbman->add_index($draws, $newidx);
            }
        }

        // Obsolete pool table.
        $pool = new xmldb_table('randomdraw_pool');
        if ($dbman->table_exists($pool)) {
            $dbman->drop_table($pool);
        }

        upgrade_mod_savepoint(true, 2024060301, 'randomdraw');
    }

    // First public release; no schema changes.
    if ($oldversion < 2026102000) {
        upgrade_mod_savepoint(true, 2026102000, 'randomdraw');
    }

    return true;
}
