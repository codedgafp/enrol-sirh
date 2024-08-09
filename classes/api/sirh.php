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

namespace enrol_sirh;

defined('MOODLE_INTERNAL') || die();

use core_group\output\user_groups_editable;
use local_mentor_core\profile_api;

require_once($CFG->dirroot . '/enrol/sirh/externallib.php');
require_once($CFG->dirroot . '/enrol/sirh/classes/database_interface.php');
require_once($CFG->dirroot . '/group/lib.php');

/**
 * SIRH API class.
 *
 * @package    enrol_sirh
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     Remi Colet <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sirh_api {

    /**
     * Get all SIRH returns to the REST API request with different filter.
     *  key filter :
     *      - "sirh" => Originals SIRH link with session Entity
     *      - "sirhtraining" => Text conditional to training SIRH id
     *      - "sirhsession" => Text conditional to session SIRH id
     *      - "datestart" => Conditional when to session SIRH start
     *      - "dateend" => Conditional when to session SIRH finish
     *      - "order" => Defines on which element the return list will be ordered
     *      - "start" => Select page list
     *      - "length" => Define number of element by page
     *
     * @param int $sessionid
     * @param array $filter
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_sirh_sessions($sessionid, $filter) {

        // Get session and this entity sirh list.
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $entitysirhlist = $session->get_entity()->get_main_entity()->get_sirh_list();

        $sirhinstances = $session->get_sirh_instances();

        // Check user capability.
        self::check_enrol_sirh_capability($session->get_context()->id);

        // Check if "sirh" key filter exist.
        if (!isset($filter['listeIdentifiantSirhOrigine'])) {
            throw new \Exception('Missing filter : listeIdentifiantSirhOrigine');
        }

        $sirhfilter = [];

        $sirhlist = explode(',', $filter['listeIdentifiantSirhOrigine']);

        // If exist, check if sirh is link to entity.
        foreach ($sirhlist as $sirhdatafilter) {
            if (in_array($sirhdatafilter, $entitysirhlist)) {
                $sirhfilter[] = $sirhdatafilter;
            }
        }

        // Not access if sirh filter is empty.
        if (empty($sirhfilter)) {
            throw new \Exception('Permission denied');
        }

        // Re-set sirh data filter.
        $filter['listeIdentifiantSirhOrigine'] = implode(',', $sirhfilter);

        // Get SIRH REST API.
        $sirhrest = static::get_sirh_rest_api();

        $nbelemperpage = $filter['nombreElementPage'];
        $filter['nombreElementPage'] = 1;

        // Count all sessions.
        $countsessions = $sirhrest->count_sirh_sessions($filter);
        $filter['nombreElementPage'] = $countsessions;

        $pagenumber = $filter['numeroPage'];
        unset($filter['numeroPage']);

        // Get all sessions from the API.
        $sirhsessions = $sirhrest->get_sirh_sessions($filter);

        $finalsessions = [];

        // Check if SIRH enrolment instance already exists.
        foreach ($sirhsessions as $sirhsession) {
            $sirhsession->instanceexists = false;

            foreach ($sirhinstances as $sirhinstance) {
                if (
                    ($sirhsession->sirh == $sirhinstance->customchar1) &&
                    ($sirhsession->sirhtraining == $sirhinstance->customchar2) &&
                    ($sirhsession->sirhsession == $sirhinstance->customchar3)
                ) {

                    $sirhsession->instanceexists = true;
                    $sirhsession->instanceid = $sirhinstance->id;
                }
            }

            $finalsessions[] = $sirhsession;
        }

        // Filter by actions.
        if (isset($filter['filterbyactions']) && $filter['filterbyactions']) {

            $dir = $filter['filterbyactionsdir'];

            // Sort by instanceexists.
            usort($finalsessions,
                function($a, $b) use ($dir) {
                    $left = $a->instanceexists ? 1 : 0;
                    $right = $b->instanceexists ? 1 : 0;

                    if ($dir == 'ASC') {
                        return $right - $left;
                    } else {
                        return $left - $right;
                    }
                }
            );
        }

        // Splice results by page number and nb results per page.
        $offset = ($pagenumber - 1) * $nbelemperpage;
        $finalsessions = array_splice($finalsessions, $offset, $nbelemperpage);

        return $finalsessions;
    }

    /**
     * Count all SIRH returns to the REST API request with different filter.
     *  key filter :
     *      - "sirh" => Originals SIRH link with session Entity
     *      - "sirhtraining" => Text conditional to training SIRH id
     *      - "sirhsession" => Text conditional to session SIRH id
     *      - "datestart" => Conditional when to session SIRH start
     *      - "dateend" => Conditional when to session SIRH finish
     *      - "order" => Defines on which element the return list will be ordered
     *      - "start" => Select page list
     *      - "length" => Define number of element by page
     *
     * @param array $filter
     * @return int
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function count_sirh_sessions($sessionid, $filter) {

        // Get session and this entity sirh list.
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $entitysirhlist = $session->get_entity()->get_main_entity()->get_sirh_list();

        $sirhinstances = $session->get_sirh_instances();

        // Check user capacbility.
        self::check_enrol_sirh_capability($session->get_context()->id);

        // Check if "sirh" key filter exist.
        if (!isset($filter['listeIdentifiantSirhOrigine'])) {
            throw new \Exception('Missing filter : listeIdentifiantSirhOrigine');
        }

        $sirhfilter = [];

        $sirhlist = explode(',', $filter['listeIdentifiantSirhOrigine']);

        // If exist, check if sirh is link to entity.
        foreach ($sirhlist as $sirhdatafilter) {
            if (in_array($sirhdatafilter, $entitysirhlist)) {
                $sirhfilter[] = $sirhdatafilter;
            }
        }

        // Not access if sirh filter is empty.
        if (empty($sirhfilter)) {
            throw new \Exception('Permission denied');
        }

        // Re-set sirh data filter.
        $filter['listeIdentifiantSirhOrigine'] = implode(',', $sirhfilter);

        // Get SIRH REST API.
        $sirhrest = static::get_sirh_rest_api();

        return $sirhrest->count_sirh_sessions($filter);
    }

    /**
     * Return result of users list of SIRH session select
     * to the REST API request
     *
     * @param int $sessionid
     * @param string $sirh
     * @param string $sirhtraining
     * @param string $sirhsession
     * @param int $nbuser
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_session_users($sessionid, $sirh, $sirhtraining, $sirhsession, $nbuser = null) {
        // Get session and this entity sirh list.
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $entitysirhlist = $session->get_entity()->get_main_entity()->get_sirh_list();

        // Check capability.
        self::check_enrol_sirh_capability($session->get_context()->id);

        // Check if sirh is link to entity.
        if (!in_array($sirh, $entitysirhlist)) {
            throw new \Exception('Not access');
        }

        // Get SIRH REST API.
        $sirhrest = static::get_sirh_rest_api();

        // Call request.
        return $sirhrest->get_session_users($sirh, $sirhtraining, $sirhsession, $nbuser);
    }

    /**
     * Enrol all users to link with SIRH session to the session.
     *
     * @param int $sessionid
     * @param string $sirh
     * @param string $sirhtraining
     * @param string $sirhsession
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function enrol_users_sirh($sessionid, $sirh, $sirhtraining, $sirhsession) {
        // Get session and this entity sirh list.
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $courseid = $session->get_course()->id;

        // Check capability.
        self::check_enrol_sirh_capability($session->get_context()->id);

        $instance = self::get_or_create_enrol_sirh_instance($courseid, $sirh, $sirhtraining, $sirhsession);

        // Get SIRH REST API.
        $sirhrest = static::get_sirh_rest_api();

        // Get all users link with SIRH session.
        $users = $sirhrest->get_session_users($instance->customchar1, $instance->customchar2, $instance->customchar3);

        // Get validate users.
        enrol_sirh_validate_users($users['users'], $instance, null, $preview);

        return self::synchronize_users($instance, $preview['list']);
    }

    /**
     * Synchronize sessions users from an existing instance
     *
     * @param \stdClass $instance
     * @param array $users
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function synchronize_users($instance, $users) {
        $db = \enrol_sirh\database_interface::get_instance();

        $enrolledusers = [];

        // Enrol user one by one.
        foreach ($users as $user) {
            self::create_and_enrol_user($instance->courseid, $instance->id, $user);
            $enrolledusers[$user->email] = $user;
            if (intval($instance->customint1)) {
                if (!isset($user->id)) {
                    $user = $db->get_user_by_email($user->email);
                }

                // Add to SIRH group.
                groups_add_member($instance->customint1, $user->id);
            }
        }

        $oldusers = self::get_instance_users($instance->id);

        $enrol = enrol_get_plugin('sirh');

        // Unenrol missing users.
        foreach ($oldusers as $olduser) {
            if (!isset($enrolledusers[$olduser->email])) {
                // Unenrol user.
                $enrol->unenrol_user($instance, $olduser->id);
                if (intval($instance->customint1)) {

                    // Remove to SIRH group.
                    groups_remove_member($instance->customint1, $olduser->id);
                }
            }
        }

        // Enrolment went well.
        return true;
    }

    /**
     * Update synchronation date
     * and user if $updateuser is true
     *
     * @param \stdClass $instance
     * @param bool $updateuser
     * @return void
     */
    public static function update_sirh_instance_sync_data($instance, $updateuser = true) {
        global $USER;

        // Get enrol plugin.
        $enrol = enrol_get_plugin('sirh');

        // Set new instance data.
        $newinstancedata = new \stdClass();

        if ($updateuser) {
            $newinstancedata->customint2 = $USER->id; // Add last user to sync.
        }

        $newinstancedata->customint3 = time(); // Add last date of sync.

        // Update enrol SIRH instance.
        $enrol->update_instance($instance, $newinstancedata);
    }

    /**
     * Return enrol SIRH instance id.
     *
     * @param int $courseid
     * @param string $sirh
     * @param string $sirhtraining
     * @param string $sirhsession
     * @return false|\stdClass
     * @throws \dml_exception
     */
    public static function get_enrol_sirh_instance($courseid, $sirh, $sirhtraining, $sirhsession) {
        $dbi = \enrol_sirh\database_interface::get_instance();

        return $dbi->get_instance_sirh($courseid, $sirh, $sirhtraining, $sirhsession);
    }

    /**
     * Create enrol SIRH instance.
     *
     * @param int $courseid
     * @param string $sirh
     * @param string $sirhtraining
     * @param string $sirhsession
     * @return int
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function create_enrol_sirh_instance($courseid, $sirh, $sirhtraining, $sirhsession) {

        // Create new self enrol instance.
        $plugin = enrol_get_plugin('sirh');

        $instance = (object) $plugin->get_instance_defaults();
        $instance->status = 0;
        $instance->id = '';
        $instance->courseid = $courseid;
        $instance->expirythreshold = 0;
        $instance->enrolstartdate = 0;
        $instance->enrolenddate = 0;
        $instance->timecreated = time();
        $instance->timemodified = time();
        $instance->customchar1 = $sirh;
        $instance->customchar2 = $sirhtraining;
        $instance->customchar3 = $sirhsession;

        $fields = (array) $instance;

        return $plugin->add_instance(get_course($courseid), $fields);
    }

    /**
     * Get enrol SIRH instance
     * If not exist, create this before
     *
     * @param int $courseid
     * @param string $sirh
     * @param string $sirhtraining
     * @param string $sirhsession
     * @return false|mixed|\stdClass
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function get_or_create_enrol_sirh_instance($courseid, $sirh, $sirhtraining, $sirhsession) {
        global $DB;

        // Check if enrol instance exist.
        if (!$instance = self::get_enrol_sirh_instance($courseid, $sirh, $sirhtraining, $sirhsession)) {
            // Create enrol instance if not exist.
            $instanceid = self::create_enrol_sirh_instance($courseid, $sirh, $sirhtraining, $sirhsession);
            $instance = $DB->get_record('enrol', ['id' => $instanceid]);
        }

        return $instance;
    }

    /**
     * Create user if he does not exist,
     * and enrol him into the session's course.
     *
     * @param int $courseid
     * @param string $instanceid
     * @param int|array $user
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function create_and_enrol_user($courseid, $instanceid, $user) {

        $db = \enrol_sirh\database_interface::get_instance();

        if (is_int($user)) {
            $userid = $user;
        } else {
            if (is_array($user)) {

                $userdata = new \stdClass();

                $requiredkeys = ['email', 'firstname', 'lastname'];

                // Check if required fields exist.
                foreach ($requiredkeys as $requiredkey) {
                    if (!isset($user[$requiredkey]) || empty($user[$requiredkey])) {
                        throw new \Exception('Missing key ' . $requiredkey);
                    }
                }

                foreach ($user as $key => $item) {
                    $userdata->{$key} = $item;
                }

                $userdata->username = local_mentor_core_mail_to_username($user['email']);
                $user = $userdata;
            }

            // Get user if exists.
            $usersearch = $db->get_user_by_email($user->email);

            // Create user if doesn't exist.
            if (!$usersearch) {
                // Create a new user.
                $userid = \local_mentor_core\profile_api::create_user($user);
            } else {
                $userid = $usersearch->id;

                // Reactivate account if necessary.
                if ($usersearch->suspended == 1) {
                    $profile = profile_api::get_profile($usersearch);
                    $profile->reactivate();
                }
            }
        }

        // Enrol user.
        return \enrol_sirh_external::enrol_user($courseid, $instanceid, $userid);
    }

    /**
     * Check user capability to use enrolment action.
     *
     * @param int $contextid
     * @param \stdClass $user - optional default null for current user
     * @return void
     * @throws \coding_exception
     */
    public static function check_enrol_sirh_capability($contextid, $user = null) {
        // Use the current user if $user is null.
        if (empty($user)) {
            global $USER;
            $user = $USER;
        }

        $context = \context::instance_by_id($contextid, MUST_EXIST);

        // Check if the user can manage sirh configuration in course context.
        if (!has_capability('enrol/sirh:config', $context, $user)) {
            throw new \Exception('Permission denied');
        }
    }

    /**
     * Get SIRH REST API.
     *
     * @return sirh
     */
    public static function get_sirh_rest_api() {
        return \enrol_sirh\sirh::get_instance();
    }

    /**
     * Get enrolment instance users
     *
     * @param int $instanceid
     * @return \stdClass[]
     * @throws \dml_exception
     */
    public static function get_instance_users($instanceid) {
        $dbi = \enrol_sirh\database_interface::get_instance();

        return $dbi->get_instance_users_sirh($instanceid);
    }

    /**
     * Get SIRH instance object.
     *
     * @param int $instanceid
     * @return false|mixed
     * @throws \dml_exception
     */
    public static function get_instance($instanceid) {
        $dbi = \enrol_sirh\database_interface::get_instance();

        return $dbi->get_instance_sirh_by_id($instanceid);
    }

    /**
     * Get SIRH instance group.
     *
     * @param int $instanceid
     * @return bool|\stdClass
     * @throws \dml_exception
     */
    public static function get_group_sirh($instanceid) {
        $instance = self::get_instance($instanceid);

        // Check if it has group.
        if (is_null($instance->customint1)) {
            return false;
        }

        return groups_get_group($instance->customint1);
    }

    /**
     * Create group with SIRH instance name.
     *
     * @param object $instance
     * @return int group id
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public static function create_group_sirh($instance) {
        // Create a new group for the cohort.
        $groupdata = new \stdClass();
        $groupdata->courseid = $instance->courseid;
        $groupdata->name = get_string('sirh_group_name', 'enrol_sirh',
            [
                // SIRH.
                'c1' => $instance->customchar1,
                // SIRH training.
                'c2' => $instance->customchar2,
                // SIRH session.
                'c3' => $instance->customchar3,
            ]
        );

        return groups_create_group($groupdata);
    }

    /**
     * Set SIRH instance group.
     *
     * @param int $instanceid
     * @param int $groupid
     * @return bool
     * @throws \dml_exception
     */
    public static function set_group_sirh($instanceid, $groupid = null) {
        // Get SIRH instance object.
        $instance = self::get_instance($instanceid);

        // Update SIRH instance with new group.
        $enrol = enrol_get_plugin('sirh');
        $newinstancedata = new \stdClass();
        $newinstancedata->customint1 = $groupid;
        $enrol->update_instance($instance, $newinstancedata);

        return true;
    }

    /**
     * Check if default SIRH instance group exist
     * If the group exist return it else return false
     *
     * @param int $instanceid
     * @return false|\stdClass
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function default_sirh_group_exist($instanceid) {
        $dbi = \enrol_sirh\database_interface::get_instance();

        // Get SIRH instance object.
        $instance = self::get_instance($instanceid);

        // Return group object if there exist.
        return $dbi->get_course_group_by_name($instance->courseid, get_string('sirh_group_name', 'enrol_sirh',
            [
                'c1' => $instance->customchar1,
                'c2' => $instance->customchar2,
                'c3' => $instance->customchar3,
            ]
        ));
    }

    /**
     * Send session information to SIRH API
     * and return result.
     * If success is false, return errors message
     * Else return true.
     *
     * @param $sessioninformation
     * @return bool|\stdClass
     * @throws \Exception
     */
    public static function follow_up_session($sessioninformation) {

        // Check if session has users completion information.
        if (!($sessioninformation->utilisateurs) || empty($sessioninformation->utilisateurs)) {
            return false;
        }

        // Get SIRH REST API.
        $sirhrest = static::get_sirh_rest_api();

        // Send session information to SIRH API.
        $result = $sirhrest->follow_up_session(json_encode($sessioninformation));

        // Check if result is not success.
        if ($result->success == false) {
            // Return errors message.
            return $result;
        }

        // Updaye last sync time SIRH to session.
        $db = \enrol_sirh\database_interface::get_instance();
        $db->update_last_sync_sirh_session(
            // Session id.
            $sessioninformation->sessionMentor->identifiantSessionMentor,
            // Sync SIRH time - convert date to timestamp.
            strtotime($result->dateSynchronisationSessionMentor)
        );

        return true;
    }
}
