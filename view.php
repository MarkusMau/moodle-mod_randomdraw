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
 * Main view page for a randomdraw activity.
 *
 * @package    mod_randomdraw
 * @copyright  2026 Markus Maurer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/randomdraw/lib.php');

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'randomdraw');
$instance = $DB->get_record('randomdraw', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/randomdraw:view', $context);

$ismanager = has_capability('mod/randomdraw:manage', $context);
$hasdrawcap = has_capability('mod/randomdraw:draw', $context);

// Mark as viewed for completion tracking, but only when completion
// is enabled for this activity.
$completion = new completion_info($course);
if ($completion->is_enabled($cm)) {
    $completion->set_module_viewed($cm);
}

$PAGE->set_url('/mod/randomdraw/view.php', ['id' => $id]);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->css('/mod/randomdraw/styles.css');

$min  = (int)$instance->range_min;
$max  = (int)$instance->range_max;
$mind = (int)$instance->min_draws;
$maxd = (int)$instance->max_draws;

// Student state: $hasdrawcap says whether the user may draw at all,
// $candraw whether they may draw right now (limit not yet reached).
$mydraws = [];
$mycount = 0;
$candraw = false;
if ($hasdrawcap) {
    $mydraws = randomdraw_my_draws($instance->id, $USER->id);
    $mycount = count($mydraws);
    $candraw = ($maxd === 0 || $mycount < $maxd);
}

// Frequency data.
$freq      = randomdraw_get_frequency($instance->id, $min, $max);
$deviation = randomdraw_distribution_deviation($freq);
[$remaining, $total] = randomdraw_get_pool_status($instance->id, $min, $max);

// Since Moodle 4.0 the activity name and description are rendered by the
// core activity header, so they must not be echoed again here.
echo $OUTPUT->header();

$drawnnum = optional_param('drawn', 0, PARAM_INT);
if ($drawnnum) {
    echo '<div class="rd-flash">' . get_string('justdrawn', 'mod_randomdraw', $drawnnum) . '</div>';
}

echo '<div class="rd-wrap">';

// Local helper for rendering an info-bar item.
$infoitem = function (string $label, $value, string $valclass = ''): string {
    return '<div class="rd-info-item">'
         . '<span class="rd-info-label">' . $label . '</span>'
         . '<span class="rd-info-val ' . $valclass . '">' . $value . '</span>'
         . '</div>';
};

// MANAGER VIEW.
if ($ismanager) {
    // Info bar.
    $totaldraws = array_sum($freq);
    echo '<div class="rd-info-bar">';
    echo $infoitem(get_string('range', 'mod_randomdraw'), $min . ' – ' . $max);
    echo $infoitem(get_string('totaldraws', 'mod_randomdraw'), $totaldraws);
    echo $infoitem(get_string('min_draws', 'mod_randomdraw'), $mind);
    echo $infoitem(get_string('max_draws', 'mod_randomdraw'), $maxd > 0 ? $maxd : '∞');
    echo $infoitem(
        get_string('deviation', 'mod_randomdraw'),
        $deviation,
        $deviation === 0 ? 'rd-val-perfect' : ($deviation === 1 ? 'rd-val-good' : 'rd-val-warn')
    );
    echo '</div>';

    // Actions.
    echo '<div class="rd-actions">';
    $reseturl = new moodle_url(
        '/mod/randomdraw/action.php',
        ['cmid' => $id, 'action' => 'resetall', 'sesskey' => sesskey()]
    );
    echo $OUTPUT->single_button(
        $reseturl,
        get_string('resetall', 'mod_randomdraw'),
        'post',
        ['class' => 'rd-btn-reset']
    );
    echo '</div>';

    // Distribution chart.
    if ($totaldraws > 0) {
        echo '<h3 class="rd-section-title">' . get_string('distribution', 'mod_randomdraw') . '</h3>';
        echo '<div class="rd-distribution">';

        $maxcount = max($freq) ?: 1;
        $mincount = min($freq);

        foreach ($freq as $number => $count) {
            $pct    = round(($count / $maxcount) * 100);
            $ismin  = ($count === $mincount); // Still in the "next tier".
            $cls    = $ismin ? 'rd-bar-next' : 'rd-bar-done';
            $title  = $number . ': ' . $count . 'x';
            echo '<div class="rd-bar-col" title="' . $title . '">';
            echo '  <div class="rd-bar-count">' . ($count > 0 ? $count : '') . '</div>';
            echo '  <div class="rd-bar-track">';
            echo '    <div class="rd-bar-fill ' . $cls . '" style="height:' . $pct . '%"></div>';
            echo '  </div>';
            echo '  <div class="rd-bar-label">' . $number . '</div>';
            echo '</div>';
        }
        echo '</div>';

        $nexttier = array_keys(array_filter($freq, fn($f) => $f === $mincount));
        echo '<p class="rd-chart-legend">';
        echo '  <span class="rd-legend-dot rd-bar-next"></span> '
            . get_string('nexttier', 'mod_randomdraw', count($nexttier));
        if ($deviation === 0) {
            echo ' &nbsp;·&nbsp; <span class="rd-perfect">' . get_string('perfectdist', 'mod_randomdraw') . '</span>';
        }
        echo '</p>';
    }

    // Student cards.
    $alldraws = randomdraw_get_all_draws($instance->id);
    echo '<h3 class="rd-section-title" style="margin-top:28px">'
        . get_string('allstudents', 'mod_randomdraw') . '</h3>';

    if (empty($alldraws)) {
        echo '<div class="rd-empty">' . get_string('nodraws', 'mod_randomdraw') . '</div>';
    } else {
        echo '<div class="rd-students-grid">';
        foreach ($alldraws as $data) {
            $count   = count($data['draws']);
            $done    = $count >= $mind;
            $scls    = $done ? 'rd-status-done' : 'rd-status-progress';
            $stxt    = $done
                ? get_string('status_done', 'mod_randomdraw')
                : get_string('status_progress', 'mod_randomdraw', ['n' => $count, 'min' => $mind]);
            $progpct = $mind > 0 ? min(100, round(($count / $mind) * 100)) : 100;

            echo '<div class="rd-student-card">';
            echo '<div class="rd-student-header">';
            echo '  <span class="rd-student-name">' . format_string($data['fullname']) . '</span>';
            echo '  <span class="rd-status ' . $scls . '">' . $stxt . '</span>';
            echo '</div>';
            echo '<div class="rd-student-numbers">';
            foreach ($data['draws'] as $num) {
                echo '<span class="rd-num-chip">' . $num . '</span>';
            }
            echo '</div>';
            echo '<div class="rd-mini-bar-bg"><div class="rd-mini-bar-fill '
                . ($done ? 'done' : '') . '" style="width:' . $progpct . '%"></div></div>';
            echo '</div>';
        }
        echo '</div>';
    }

    // STUDENT VIEW.
} else {
    $done = $mycount >= $mind;

    echo '<div class="rd-student-view">';

    // Status chip.
    if ($done) {
        echo '<div class="rd-round-chip rd-chip-done">✓ ' . get_string('task_complete', 'mod_randomdraw') . '</div>';
    } else {
        $left = $mind - $mycount;
        echo '<div class="rd-round-chip">' . get_string('draws_needed', 'mod_randomdraw', $left) . '</div>';
    }

    // Draw button or disabled state.
    echo '<div class="rd-predraw">';
    if ($candraw) {
        $drawurl = new moodle_url(
            '/mod/randomdraw/action.php',
            ['cmid' => $id, 'action' => 'draw', 'sesskey' => sesskey()]
        );
        echo '<p class="rd-invite">' . get_string('taptoget', 'mod_randomdraw') . '</p>';
        echo '<form method="post" action="' . $drawurl->out(false) . '">';
        echo '  <input type="hidden" name="sesskey" value="' . sesskey() . '" />';
        echo '  <button type="submit" class="rd-draw-btn">'
            . get_string('drawbtn', 'mod_randomdraw') . '</button>';
        echo '</form>';
        if ($maxd > 0) {
            echo '<p class="rd-counter">'
                . get_string('draws_sofar', 'mod_randomdraw', ['n' => $mycount, 'max' => $maxd])
                . '</p>';
        }
    } else {
        echo '<div class="rd-draw-btn rd-draw-btn-disabled">'
            . get_string('maxreached_btn', 'mod_randomdraw') . '</div>';
    }
    echo '</div>';

    // My drawn numbers.
    if (!empty($mydraws)) {
        echo '<div class="rd-my-draws">';
        echo '<h3 class="rd-my-draws-title">' . get_string('mynumbers', 'mod_randomdraw') . '</h3>';

        $progpct = $mind > 0 ? min(100, round(($mycount / $mind) * 100)) : 100;
        echo '<div class="rd-pool-bar-bg" style="margin-bottom:8px">';
        echo '  <div class="rd-pool-bar-fill ' . ($done ? 'done' : '') . '" style="width:' . $progpct . '%"></div>';
        echo '</div>';
        echo '<p class="rd-pool-label" style="margin-bottom:16px">';
        echo $done
            ? get_string('task_complete', 'mod_randomdraw')
            : get_string('progress_label', 'mod_randomdraw', ['n' => $mycount, 'min' => $mind]);
        echo '</p>';

        echo '<div class="rd-my-numbers">';
        foreach ($mydraws as $i => $draw) {
            echo '<div class="rd-my-num-card">';
            echo '  <div class="rd-my-num-index">#' . ($i + 1) . '</div>';
            echo '  <div class="rd-my-num-val">' . $draw->number . '</div>';
            echo '  <div class="rd-my-num-time">'
                . userdate($draw->timedrawn, get_string('strftimetime', 'langconfig'))
                . '</div>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    }

    echo '</div>'; // End of student view.
}

echo '</div>'; // End of wrapper.
echo $OUTPUT->footer();
