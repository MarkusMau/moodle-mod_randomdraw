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
 * Renderable for the view page of a randomdraw activity.
 *
 * @package    mod_randomdraw
 * @copyright  2026 Markus Maurer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_randomdraw\output;

/**
 * Data for the view page of a randomdraw activity, rendered with a mustache template.
 *
 * @package    mod_randomdraw
 * @copyright  2026 Markus Maurer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_page implements \renderable, \templatable {
    /** @var \stdClass the activity record */
    protected $instance;
    /** @var int the course module id */
    protected $cmid;
    /** @var bool whether the user may manage the activity */
    protected $ismanager;
    /** @var int the user id */
    protected $userid;
    /** @var bool whether the user may draw at all */
    protected $hasdrawcap;
    /** @var int number just drawn, 0 for none */
    protected $justdrawn;

    /**
     * Constructor.
     *
     * @param \stdClass $instance the activity record
     * @param int $cmid the course module id
     * @param bool $ismanager whether the user may manage the activity
     * @param bool $hasdrawcap whether the user may draw
     * @param int $userid the user id
     * @param int $justdrawn the number drawn just now, 0 for none
     */
    public function __construct(\stdClass $instance, int $cmid, bool $ismanager, bool $hasdrawcap, int $userid, int $justdrawn) {
        $this->instance = $instance;
        $this->cmid = $cmid;
        $this->ismanager = $ismanager;
        $this->hasdrawcap = $hasdrawcap;
        $this->userid = $userid;
        $this->justdrawn = $justdrawn;
    }

    /**
     * Export the data for the template.
     *
     * @param \renderer_base $output the renderer
     * @return array the template context
     */
    public function export_for_template(\renderer_base $output): array {
        $str = fn(string $key, $a = null) => get_string($key, 'mod_randomdraw', $a);
        $min = (int)$this->instance->range_min;
        $max = (int)$this->instance->range_max;
        $mind = (int)$this->instance->min_draws;
        $maxd = (int)$this->instance->max_draws;
        $data = [
            'ismanager' => $this->ismanager,
            'hasflash' => $this->justdrawn > 0,
            'flash' => $this->justdrawn > 0 ? $str('justdrawn', $this->justdrawn) : '',
        ];

        if ($this->ismanager) {
            $freq = randomdraw_get_frequency($this->instance->id, $min, $max);
            $deviation = randomdraw_distribution_deviation($freq);
            $totaldraws = array_sum($freq);
            $devclass = $deviation === 0 ? 'rd-val-perfect' : ($deviation === 1 ? 'rd-val-good' : 'rd-val-warn');
            $data['infoitems'] = [
                ['label' => $str('range'), 'value' => $min . ' – ' . $max, 'valclass' => ''],
                ['label' => $str('totaldraws'), 'value' => $totaldraws, 'valclass' => ''],
                ['label' => $str('min_draws'), 'value' => $mind, 'valclass' => ''],
                ['label' => $str('max_draws'), 'value' => $maxd > 0 ? $maxd : '∞', 'valclass' => ''],
                ['label' => $str('deviation'), 'value' => $deviation, 'valclass' => $devclass],
            ];
            $data['resetbutton'] = $output->single_button(
                new \moodle_url('/mod/randomdraw/action.php', [
                    'cmid' => $this->cmid, 'action' => 'resetall', 'sesskey' => sesskey(),
                ]),
                $str('resetall'),
                'post',
                ['class' => 'rd-btn-reset']
            );
            $data['hasdistribution'] = $totaldraws > 0;
            if ($totaldraws > 0) {
                $data += $this->export_distribution($freq, $deviation);
            }
            $data['allstudentstitle'] = $str('allstudents');
            $data['nodrawstext'] = $str('nodraws');
            $data['students'] = [];
            foreach (randomdraw_get_all_draws($this->instance->id) as $student) {
                $count = count($student['draws']);
                $done = $count >= $mind;
                $data['students'][] = [
                    'name' => format_string($student['fullname']),
                    'done' => $done,
                    'statustext' => $done ? $str('status_done') : $str('status_progress', ['n' => $count, 'min' => $mind]),
                    'draws' => array_map(fn($number) => ['number' => $number], array_values($student['draws'])),
                    'percent' => $mind > 0 ? min(100, round($count / $mind * 100)) : 100,
                ];
            }
            $data['hasstudents'] = !empty($data['students']);
        } else {
            $data += $this->export_student($mind, $maxd);
        }
        return $data;
    }

    /**
     * Data of the distribution chart.
     *
     * @param array $freq number => times drawn
     * @param int $deviation the deviation of the distribution
     * @return array template data
     */
    protected function export_distribution(array $freq, int $deviation): array {
        $maxcount = max($freq) ?: 1;
        $mincount = min($freq);
        $bars = [];
        foreach ($freq as $number => $count) {
            $bars[] = [
                'number' => $number,
                'count' => $count,
                'countlabel' => $count > 0 ? $count : '',
                'title' => $number . ': ' . $count . 'x',
                'percent' => round($count / $maxcount * 100),
                'barclass' => $count === $mincount ? 'rd-bar-next' : 'rd-bar-done',
            ];
        }
        $nexttier = count(array_filter($freq, fn($f) => $f === $mincount));
        return [
            'distributiontitle' => get_string('distribution', 'mod_randomdraw'),
            'bars' => $bars,
            'nexttiertext' => get_string('nexttier', 'mod_randomdraw', $nexttier),
            'isperfect' => $deviation === 0,
            'perfecttext' => get_string('perfectdist', 'mod_randomdraw'),
        ];
    }

    /**
     * Data of the student view.
     *
     * @param int $mind minimum number of draws
     * @param int $maxd maximum number of draws, 0 for unlimited
     * @return array template data
     */
    protected function export_student(int $mind, int $maxd): array {
        $str = fn(string $key, $a = null) => get_string($key, 'mod_randomdraw', $a);
        $mydraws = [];
        $candraw = false;
        if ($this->hasdrawcap) {
            $mydraws = randomdraw_my_draws($this->instance->id, $this->userid);
            $candraw = ($maxd === 0 || count($mydraws) < $maxd);
        }
        $mycount = count($mydraws);
        $done = $mycount >= $mind;
        $data = [
            'done' => $done,
            'donetext' => $str('task_complete'),
            'neededtext' => $done ? '' : $str('draws_needed', $mind - $mycount),
            'candraw' => $candraw,
            'maxreachedtext' => $str('maxreached_btn'),
            'hasdraws' => !empty($mydraws),
        ];
        if ($candraw) {
            $data['drawurl'] = (new \moodle_url('/mod/randomdraw/action.php', [
                'cmid' => $this->cmid, 'action' => 'draw', 'sesskey' => sesskey(),
            ]))->out(false);
            $data['sesskey'] = sesskey();
            $data['invitetext'] = $str('taptoget');
            $data['drawlabel'] = $str('drawbtn');
            $data['hascounter'] = $maxd > 0;
            $data['countertext'] = $maxd > 0 ? $str('draws_sofar', ['n' => $mycount, 'max' => $maxd]) : '';
        }
        if ($mydraws) {
            $data['mynumberstitle'] = $str('mynumbers');
            $data['percent'] = $mind > 0 ? min(100, round($mycount / $mind * 100)) : 100;
            $data['progresstext'] = $done ? $str('task_complete') : $str('progress_label', ['n' => $mycount, 'min' => $mind]);
            $data['draws'] = [];
            foreach (array_values($mydraws) as $i => $draw) {
                $data['draws'][] = [
                    'index' => $i + 1,
                    'number' => $draw->number,
                    'time' => userdate($draw->timedrawn, get_string('strftimetime', 'langconfig')),
                ];
            }
        }
        return $data;
    }
}
