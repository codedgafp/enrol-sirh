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
 * SIRH enrolment plugin.
 *
 * @package    enrol_sirh
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     remi <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/enrol/sirh/forms/syncsirh_form.php');
require_once($CFG->dirroot . '/enrol/sirh/classes/api/sirh.php');
require_once($CFG->dirroot . '/enrol/sirh/classes/database_interface.php');

/**
 * SIRH enrolment plugin implementation.
 */
class enrol_sirh_plugin extends enrol_plugin {

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     * @throws coding_exception
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/sirh:config', $context);
    }

    /**
     * Returns localised name of enrol instance.
     *
     * @param stdClass $instance (null is accepted too)
     * @return string
     * @throws coding_exception
     */
    public function get_instance_name($instance) {
        return get_string('instancepluginname', 'enrol_sirh') . ' (' . $instance->customchar1 . ' - ' . $instance->customchar2 .
               ' - ' .
               $instance->customchar3 . ')';
    }

    /**
     * User is not able to enrol or configure SIRH instances.
     *
     * @param int $courseid
     * @return bool
     */
    public function can_add_instance($courseid) {
        return false;
    }

    /**
     * Add new instance of enrol plugin.
     *
     * @param object $course
     * @param array $fields instance fields
     * @return int id of new instance, null if can not be created
     * @throws coding_exception
     */
    public function add_instance($course, array $fields = null) {

        $result = parent::add_instance($course, $fields);

        return $result;
    }

    /**
     * Update instance of enrol plugin.
     *
     * @param stdClass $instance
     * @param stdClass $data modified instance fields
     * @return boolean
     * @throws coding_exception
     */
    public function update_instance($instance, $data) {

        // Get old SIRH instance data.
        $oldinstancedata = \enrol_sirh\sirh_api::get_instance($instance->id);

        // Required properties.
        $properties = [
            'customint1',
            'customint2',
            'customint3',
            'customchar1',
            'customchar2',
            'customchar3',
            'roleid',
        ];

        // Check required properties.
        foreach ($properties as $key) {
            if (!isset($data->$key)) {
                $data->$key = $oldinstancedata->$key;
            }
        }

        $context = context_course::instance($instance->courseid);

        if ($data->roleid != $oldinstancedata->roleid) {
            // The sync script can only add roles, for perf reasons it does not modify them.
            $params = [
                'contextid' => $context->id,
                'roleid' => $instance->roleid,
                'component' => 'enrol_sirh',
                'itemid' => $instance->id,
            ];
            role_unassign_all($params);
        }

        // If there are string.
        $data->customint1 = intval($data->customint1);
        $oldinstancedata->customint1 = intval($oldinstancedata->customint1);

        // Get all SIRH instance users.
        $instanceusers = \enrol_sirh\sirh_api::get_instance_users($instance->id);

        if ($data->customint1 === \syncsirh_form::ADD_TO_NO_GROUP) {
            // Remove SIRH instance group date to database.
            $data->customint1 = null;
            $instance->customint1 = null;
        }

        foreach ($instanceusers as $user) {
            // Not nul or 0.
            if ($oldinstancedata->customint1 && $oldinstancedata->customint1 !== $data->customint1) {
                // Remove user to group.
                groups_remove_member($oldinstancedata->customint1, $user->id);
            }

            // Not null or 0.
            if ($data->customint1) {
                // Add user to group.
                groups_add_member($data->customint1, $user->id);
            }
        }

        // Update SIRH instance.
        $result = parent::update_instance($instance, $data);

        return $result;
    }

    /**
     * SIRH enrol user to course
     *
     * @param stdClass $instance enrolment instance
     * @param stdClass $data data needed for enrolment.
     * @return bool|array enrolment success
     * @throws coding_exception
     */
    public function enrol_sirh(stdClass $instance, $data = null) {

        $timestart = time();
        $timeend = 0;

        $this->enrol_user($instance, $data->userid, $this->get_config('roleid'), $timestart, $timeend);

        return true;
    }

    /**
     * SIRH unenrolment
     *
     * @param stdClass $instance enrolment instance
     * @param stdClass $data data needed for enrolment.
     * @return bool|array enrolment success
     * @throws coding_exception
     */
    public function unenrol_sirh(stdClass $instance, $data = null) {
        $this->unenrol_user($instance, $data->userid);
        return true;
    }

    /**
     * This plugin does not allow manual unenrolment of a specific user.
     *
     * @param stdClass $instance course enrol instance
     * @param stdClass $ue record from user_enrolments table
     *
     * @return bool - true means user with 'enrol/xxx:unenrol' may unenrol this user, false means nobody may touch this user
     *     enrolment
     */
    public function allow_unenrol_user(stdClass $instance, stdClass $ue) {
        return false;
    }

    /**
     * Restore user enrolment.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $instance
     * @param int $userid
     * @param int $oldinstancestatus
     * @throws coding_exception
     * @throws dml_exception
     */
    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
        $dbi = \enrol_sirh\database_interface::get_instance();

        if ($this->get_config('unenrolaction') != ENROL_EXT_REMOVED_SUSPENDNOROLES) {
            // Enrolments were already synchronised in restore_instance(), we do not want any suspended leftovers.
            return;
        }

        // ENROL_EXT_REMOVED_SUSPENDNOROLES means all previous enrolments are restored.
        // but without roles and suspended.

        if (!$dbi->user_enrolment_exist($instance->id, $userid)) {
            $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, ENROL_USER_SUSPENDED);
        }
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     * @throws coding_exception
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/sirh:config', $context);
    }

    /**
     * We are a good plugin and don't invent our own UI/validation code path.
     *
     * @return boolean
     */
    public function use_standard_editing_ui() {
        return true;
    }

    /**
     * Add elements to the edit instance form.
     *
     * @param stdClass $instance
     * @param MoodleQuickForm $mform
     * @param context $coursecontext
     * @return void
     * @throws coding_exception
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $coursecontext) {

        // SIRH name.
        $mform->addElement('text', 'customchar1', get_string('sirhlabel', 'enrol_sirh'),
            ['disabled' => 'disabled', 'class' => 'sirh-field']);
        $mform->setType('customchar1', PARAM_RAW);

        // SIRH training name.
        $mform->addElement('text', 'customchar2', get_string('sirhtraininglabel', 'enrol_sirh'),
            ['disabled' => 'disabled', 'class' => 'sirh-field']);
        $mform->setType('customchar2', PARAM_RAW);

        // SIRH session name.
        $mform->addElement('text', 'customchar3', get_string('sirhsessionlabel', 'enrol_sirh'),
            ['disabled' => 'disabled', 'class' => 'sirh-field']);
        $mform->setType('customchar3', PARAM_RAW);

        // Group id.
        $coursegroupe = groups_get_course_data($instance->courseid)->groups;
        $coursegroupdata = [];
        foreach ($coursegroupe as $group) {
            $coursegroupdata[$group->id] = $group->name;
        }

        // Create select group option.
        $mform->addElement(
            'select',
            'customint1',
            get_string('addtogroup', 'enrol_sirh'),
            [
                // Add to main entity.
                \syncsirh_form::ADD_TO_NO_GROUP => get_string('addtonogroup', 'enrol_sirh'),
            ] + $coursegroupdata
        );
        $mform->setType('addtogroup', PARAM_INT);
        if (!is_null($instance->customint1)) {
            $mform->setDefault('customint1', $instance->customint1);
        }

        // User sync.

        $usersync = \core_user::get_user(
            $instance->customint2);

        $mform->addElement('static', '', 'Auteur de dernière synchronisation',
            $usersync->firstname . ' ' . $usersync->lastname . ' (' . $usersync->email . ')');

        $mform->addElement('hidden', 'customint2', $instance->customint2);
        $mform->setType('customint2', PARAM_INT);

        // Last sync.
        $mform->addElement('static', '', 'Date de dernière synchronisation', date('Y-m-d H:i:s',
            $instance->customint3));

        $mform->addElement('hidden', 'customint3', $instance->customint3);
        $mform->setType('customint3', PARAM_INT);

        // Default role.
        $mform->addElement('hidden', 'roleid', $this->get_config('roleid'));
        $mform->setType('roleid', PARAM_INT);
    }

    /**
     * Perform custom validation of the data used to edit the instance.
     *
     * @param array $data array of ("fieldname" => value) of submitted data
     * @param array $files array of uploaded files "element_name" => tmp_file_path
     * @param object $instance The instance loaded from the DB
     * @param context $context The context of the instance we are editing
     * @return array of "element_name" => "error_description" if there are errors,
     *         or an empty array if everything is OK.
     * @return void
     */
    public function edit_instance_validation($data, $files = null, $instance = null, $context = null) {
        $errors = [];

        $tovalidate = [
            // SIRH.
            'customchar1' => PARAM_RAW,
            // SIRH training.
            'customchar2' => PARAM_RAW,
            // SIRH session.
            'customchar3' => PARAM_RAW,
            // Group id.
            'customint1' => PARAM_INT,
            // Last user to sync.
            'customint2' => PARAM_INT,
            // Last date to sync.
            'customint3' => PARAM_INT,
            'roleid' => PARAM_INT,
        ];

        $typeerrors = $this->validate_param_types($data, $tovalidate);
        $errors = array_merge($errors, $typeerrors);

        return $errors;
    }

    /**
     * Return information for enrolment instance containing list of parameters required
     * for enrolment, name of enrolment plugin etc.
     *
     * @param stdClass $instance enrolment instance
     * @return stdClass instance info.
     */
    public function get_enrol_info(stdClass $instance) {
        return $instance;
    }
}
