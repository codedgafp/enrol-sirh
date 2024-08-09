<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Sync user SIRH form
 *
 * @package    enrol_sirh
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     Remi Colet <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

class syncusers_form extends moodleform {

    public $data;

    /**
     * Sync enrol constructor.
     *
     * @param array $data
     * @param string $action
     */
    public function __construct($data, $action) {
        $this->data = $data;

        parent::__construct($action);
    }

    /**
     * Define form fields
     */
    public function definition() {
        $mform = $this->_form;

        // Set hidden data.
        if (is_null($this->data['instanceid'])) {

            $mform->addElement('hidden', 'sirh', 'sirh');
            $mform->setType('sirh', PARAM_RAW);
            $mform->setDefault('sirh', $this->data['sirh']);

            $mform->addElement('hidden', 'sirhtraining', 'sirhtraining');
            $mform->setType('sirhtraining', PARAM_RAW);
            $mform->setDefault('sirhtraining', $this->data['sirhtraining']);

            $mform->addElement('hidden', 'sirhsession', 'sirhsession');
            $mform->setType('sirhsession', PARAM_RAW);
            $mform->setDefault('sirhsession', $this->data['sirhsession']);
        } else {
            $mform->addElement('hidden', 'instanceid', 'instanceid');
            $mform->setType('instanceid', PARAM_INT);
            $mform->setDefault('instanceid', $this->data['instanceid']);
        }

        $mform->addElement('hidden', 'userssync', 'userssync');
        $mform->setType('userssync', PARAM_RAW);
        $mform->setDefault('userssync', json_encode($this->data['userssync']));

        $mform->addElement('hidden', 'addtogroup', 'addtogroup');
        $mform->setType('addtogroup', PARAM_INT);
        $mform->setDefault('addtogroup', $this->data['addtogroup']);

        // Submit button.
        $mform->addElement('submit', 'submitbutton', get_string('savesync', 'enrol_sirh'));
        $mform->closeHeaderBefore('submitbutton');
    }
}
