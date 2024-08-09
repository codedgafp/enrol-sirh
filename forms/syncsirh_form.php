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
 * Sync SIRH form
 *
 * @package    enrol_sirh
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     Remi Colet <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

class syncsirh_form extends moodleform {

    /**
     * Add to no group.
     */
    public const ADD_TO_NO_GROUP = -1;

    /*
     * Create group and add to this.
     */
    public const ADD_TO_NEW_GROUP = 0;

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

        $firstselectaddtogroupoptions = [
            // Add to main entity.
            self::ADD_TO_NO_GROUP => get_string('addtonogroup', 'enrol_sirh'),
        ];

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

            // Add to secondary entity list.
            $firstselectaddtogroupoptions[self::ADD_TO_NEW_GROUP] = get_string('addtonewgroup', 'enrol_sirh');
        } else {
            $mform->addElement('hidden', 'instanceid', 'instanceid');
            $mform->setType('instanceid', PARAM_INT);
            $mform->setDefault('instanceid', $this->data['instanceid']);

            if ($instancegroupe = \enrol_sirh\sirh_api::default_sirh_group_exist($this->data['instanceid'])) {
                // Add existing group with SIRH instance name.
                $firstselectaddtogroupoptions[$instancegroupe->id] = groups_get_group($instancegroupe->id)->name;
            } else {
                // Add to secondary entity list.
                $firstselectaddtogroupoptions[self::ADD_TO_NEW_GROUP] = get_string('addtonewgroup', 'enrol_sirh');
            }
        }

        $mform->addElement('hidden', 'users', 'users');
        $mform->setType('users', PARAM_RAW);
        $mform->setDefault('users', json_encode($this->data['users']));

        // Create select group option.
        $mform->addElement(
            'select',
            'addtogroup',
            get_string('addtogroup', 'enrol_sirh'), $firstselectaddtogroupoptions + $this->data['sessiongroup']
        );
        $mform->setType('addtogroup', PARAM_INT);
        if (isset($this->data['groupid'])) {
            $mform->setDefault('addtogroup', $this->data['groupid']);
        }

        // Submit button.
        $mform->addElement('submit', 'submitbutton', get_string('continuesync', 'enrol_sirh'));
        $mform->closeHeaderBefore('submitbutton');
    }

    /**
     * Return submitted data if properly submitted or returns NULL if validation fails or
     * if there is no submitted data.
     *
     * @return object|null submitted data; NULL if not valid or not submitted or cancelled
     */
    public function get_data() {
        $data = parent::get_data();

        if (is_null($data)) {
            return null;
        }

        $data->addtogroup = intval($data->addtogroup);
        return $data;
    }
}
