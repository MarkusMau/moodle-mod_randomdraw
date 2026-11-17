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
 * Renderable for the activity list of a course.
 *
 * @package    mod_randomdraw
 * @copyright  2026 Markus Maurer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_randomdraw\output;

/**
 * Data for the activity list of a course, rendered with a mustache template.
 *
 * @package    mod_randomdraw
 * @copyright  2026 Markus Maurer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class index_page implements \renderable, \templatable {
    /** @var array the rows: name, url, range and total */
    protected $rows;

    /**
     * Constructor.
     *
     * @param array $rows the rows with name, url, range and totaldraws
     */
    public function __construct(array $rows) {
        $this->rows = $rows;
    }

    /**
     * Export the data for the template.
     *
     * @param \renderer_base $output the renderer
     * @return array the template context
     */
    public function export_for_template(\renderer_base $output): array {
        return ['rows' => $this->rows];
    }
}
