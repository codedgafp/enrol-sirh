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
 * Self enrolment external functions.
 *
 * @package   enrol_sirh
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     remi <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

class enrol_sirh_external extends external_api {

    /**
     * Returns description of get_instance_info() parameters.
     *
     * @return external_function_parameters
     */
    public static function get_instance_info_parameters() {
        return new external_function_parameters(
            ['instanceid' => new external_value(PARAM_INT, 'instance id of sirh enrolment plugin.')]
        );
    }

    /**
     * Return sirh-enrolment instance information.
     *
     * @param int $instanceid instance id of sirh enrolment plugin.
     * @return array instance information.
     * @throws moodle_exception
     */
    public static function get_instance_info($instanceid) {
        global $DB, $CFG;

        require_once($CFG->libdir . '/enrollib.php');

        $params = self::validate_parameters(self::get_instance_info_parameters(), ['instanceid' => $instanceid]);

        // Retrieve sirh enrolment plugin.
        $enrolplugin = enrol_get_plugin('sirh');
        if (empty($enrolplugin)) {
            throw new moodle_exception('invaliddata', 'error');
        }

        self::validate_context(context_system::instance());

        $enrolinstance = $DB->get_record('enrol', ['id' => $params['instanceid']], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $enrolinstance->courseid], '*', MUST_EXIST);
        if (!core_course_category::can_view_course_info($course) && !can_access_course($course)) {
            throw new moodle_exception('coursehidden');
        }

        $instanceinfo = (array) $enrolplugin->get_enrol_info($enrolinstance);
        unset($instanceinfo->requiredparam);

        return $instanceinfo;
    }

    /**
     * Returns description of get_instance_info() result value.
     *
     * @return external_description
     */
    public static function get_instance_info_returns() {
        return new external_single_structure(
            [
                'id' => new external_value(PARAM_INT, 'id of course enrolment instance'),
                'courseid' => new external_value(PARAM_INT, 'id of course'),
                'type' => new external_value(PARAM_PLUGIN, 'type of enrolment plugin'),
                'name' => new external_value(PARAM_RAW, 'name of enrolment plugin'),
                'status' => new external_value(PARAM_RAW, 'status of enrolment plugin'),
                'customchar1' => new external_value(PARAM_RAW, 'SIRH id'),
                'customchar2' => new external_value(PARAM_RAW, 'SIRH training id'),
                'customchar3' => new external_value(PARAM_RAW, 'SIRH session id'),
                'customint1' => new external_value(PARAM_INT, 'Group id'),
                'customint2' => new external_value(PARAM_INT, 'Last user id to sync'),
                'customint3' => new external_value(PARAM_INT, 'Last date to sync'),
            ],
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function enrol_user_parameters() {
        return new external_function_parameters(
            [
                'courseid' => new external_value(PARAM_INT, 'Id of the course'),
                'instanceid' => new external_value(PARAM_INT, 'Instance id of self enrolment plugin.', VALUE_DEFAULT, 0),
                'userid' => new external_value(PARAM_INT, 'User id', VALUE_DEFAULT, 0),
            ],
        );
    }

    /**
     * sirh enrol the current user in the given course.
     *
     * @param int $courseid id of course
     * @param int $instanceid instance id of self enrolment plugin
     * @param int $userid User id
     * @return array of warnings and status result
     * @throws moodle_exception
     * @since Moodle 3.0
     */
    public static function enrol_user($courseid, $instanceid = 0, $userid = 0) {
        global $CFG, $DB;

        require_once($CFG->libdir . '/enrollib.php');

        $params = self::validate_parameters(self::enrol_user_parameters(),
            [
                'courseid' => $courseid,
                'instanceid' => $instanceid,
                'userid' => $userid,
            ]);

        $warnings = [];

        $course = get_course($params['courseid']);
        self::validate_context(context_system::instance());

        if (!core_course_category::can_view_course_info($course)) {
            throw new moodle_exception('coursehidden');
        }

        // Retrieve the self enrolment plugin.
        $enrol = enrol_get_plugin('sirh');
        if (empty($enrol)) {
            throw new moodle_exception('canntenrol', 'enrol_self');
        }

        $instance = $DB->get_record('enrol', ['id' => $instanceid]);

        $data = new \stdClass();
        $data->userid = $userid;

        $enrol->enrol_sirh($instance, $data);

        // Try to enrol the user in the instance/s.
        $enrolled = true;

        $result = [];
        $result['status'] = $enrolled;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function enrol_user_returns() {
        return new external_single_structure(
            [
                'status' => new external_value(PARAM_BOOL, 'status: true if the user is enrolled, false otherwise'),
                'warnings' => new external_warnings(),
            ]
        );
    }
}
