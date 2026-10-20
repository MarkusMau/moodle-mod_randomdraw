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
 * Settings form for the mod_randomdraw activity module.
 *
 * @package    mod_randomdraw
 * @copyright  2026 Markus Maurer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Settings form for the mod_randomdraw activity module.
 *
 * @package    mod_randomdraw
 * @copyright  2026 Markus Maurer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_randomdraw_mod_form extends moodleform_mod {
    /**
     * Define the elements of the settings form.
     */
    public function definition(): void {
        $mform = $this->_form;

        // General.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement(
            'text',
            'name',
            get_string('activityname', 'mod_randomdraw'),
            ['size' => 64, 'maxlength' => 255]
        );
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        // Number Range.
        $mform->addElement('header', 'rangeheader', get_string('rangeheader', 'mod_randomdraw'));
        $mform->setExpanded('rangeheader');

        $mform->addElement('text', 'range_min', get_string('range_min', 'mod_randomdraw'), ['size' => 6]);
        $mform->setType('range_min', PARAM_INT);
        $mform->setDefault('range_min', 1);
        $mform->addRule('range_min', null, 'required', null, 'client');
        $mform->addHelpButton('range_min', 'range_min', 'mod_randomdraw');

        $mform->addElement('text', 'range_max', get_string('range_max', 'mod_randomdraw'), ['size' => 6]);
        $mform->setType('range_max', PARAM_INT);
        $mform->setDefault('range_max', 20);
        $mform->addRule('range_max', null, 'required', null, 'client');
        $mform->addHelpButton('range_max', 'range_max', 'mod_randomdraw');

        // Draw limits.
        $mform->addElement('header', 'limitsheader', get_string('limitsheader', 'mod_randomdraw'));
        $mform->setExpanded('limitsheader');

        $mform->addElement('text', 'min_draws', get_string('min_draws', 'mod_randomdraw'), ['size' => 6]);
        $mform->setType('min_draws', PARAM_INT);
        $mform->setDefault('min_draws', 1);
        $mform->addHelpButton('min_draws', 'min_draws', 'mod_randomdraw');

        $mform->addElement('text', 'max_draws', get_string('max_draws', 'mod_randomdraw'), ['size' => 6]);
        $mform->setType('max_draws', PARAM_INT);
        $mform->setDefault('max_draws', 0);
        $mform->addHelpButton('max_draws', 'max_draws', 'mod_randomdraw');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Validate the submitted form data.
     *
     * @param array $data the submitted data
     * @param array $files any submitted files
     * @return array an array of validation errors
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $min    = (int)($data['range_min'] ?? 1);
        $max    = (int)($data['range_max'] ?? 20);
        $mind   = (int)($data['min_draws'] ?? 1);
        $maxd   = (int)($data['max_draws'] ?? 0);

        if ($min < 1) {
            $errors['range_min'] = get_string('err_minpositive', 'mod_randomdraw');
        }
        if ($max <= $min) {
            $errors['range_max'] = get_string('err_maxgtmin', 'mod_randomdraw');
        }
        if (($max - $min + 1) > 1000) {
            $errors['range_max'] = get_string('err_rangetoolarge', 'mod_randomdraw');
        }
        if ($mind < 1) {
            $errors['min_draws'] = get_string('err_mindrawspositive', 'mod_randomdraw');
        }
        if ($maxd > 0 && $maxd < $mind) {
            $errors['max_draws'] = get_string('err_maxdrawsltmin', 'mod_randomdraw');
        }

        return $errors;
    }
}
