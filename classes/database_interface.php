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
 * Database Interface
 *
 * @package    enrol_sirh
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     remi <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_sirh;

use core_course\search\course;

defined('MOODLE_INTERNAL') || die();

class database_interface {

    /**
     * @var \moodle_database
     */
    protected $db;

    /**
     * @var self
     */
    protected static $instance;

    public function __construct() {

        global $DB;

        $this->db = $DB;
    }

    /**
     * Create a singleton
     *
     * @return database_interface
     */
    public static function get_instance() {

        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }
        return self::$instance;

    }

    /**
     * Get user object with this email.
     *
     * @param false|string $email
     */
    public function get_user_by_email($email) {
        return $this->db->get_record('user', ['email' => $email]);
    }

    /**
     * Get enrolment instance users
     *
     * @param int $instanceid
     * @return \stdClass[]
     * @throws \dml_exception
     */
    public function get_instance_users_sirh($instanceid) {
        global $DB;

        return $DB->get_records_sql('
            SELECT u.*
            FROM {user} u
            JOIN {user_enrolments} ue ON ue.userid = u.id
            WHERE
                ue.enrolid = :instanceid
        ', ['instanceid' => $instanceid]);
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
    public function get_instance_sirh($courseid, $sirh, $sirhtraining, $sirhsession) {
        return $this->db->get_record_sql('
            SELECT e.*
            FROM {enrol} e
            JOIN {course} c ON c.id = e.courseid
            JOIN {session} s ON s.courseshortname = c.shortname
            WHERE e.courseid = :courseid
                AND e.customchar1 = :sirh
                AND e.customchar2 = :sirhtraining
                AND e.customchar3 = :sirhsession
        ', [
            'courseid' => $courseid,
            'sirh' => $sirh,
            'sirhtraining' => $sirhtraining,
            'sirhsession' => $sirhsession,
        ]);
    }

    /**
     * Get SIRH instance object.
     *
     * @param int $instanceid
     * @return false|mixed
     * @throws \dml_exception
     */
    public function get_instance_sirh_by_id($instanceid) {
        return $this->db->get_record_sql('
            SELECT *
            FROM {enrol} e
            WHERE e.id = :instanceid
        ', ['instanceid' => $instanceid]);
    }

    /**
     * Get course group object by name.
     *
     * @param int $courseid
     * @param string $groupname
     * @return false|mixed
     * @throws \dml_exception
     */
    public function get_course_group_by_name($courseid, $groupname) {
        // Return group object if there exist.
        return $this->db->get_record_sql('
        SELECT g.*
        from {groups} g
        WHERE g.courseid = :courseid AND
            ' . $this->db->sql_like('g.name', ':defaultname')
            , [
                'courseid' => $courseid,
                'defaultname' => $this->db->sql_like_escape($groupname),
            ]);
    }

    /**
     * Check if user enrolment exist.
     *
     * @param int $instanceid
     * @param int $userid
     * @return bool
     * @throws \dml_exception
     */
    public function user_enrolment_exist($instanceid, $userid) {
        return $this->db->record_exists(
            'user_enrolments',
            ['enrolid' => $instanceid, 'userid' => $userid]
        );
    }

    /**
     * Return all instance SIRH object.
     *
     * @return \stdClass[];
     * @throws \dml_exception
     */
    public function get_all_instance_sirh() {
        return $this->db->get_records_sql('
            SELECT e.*, c.id courseid, s.courseshortname sessionname
            FROM {session} s
            JOIN {course} c ON s.courseshortname = c.shortname
            JOIN {enrol} e ON c.id = e.courseid
            WHERE
                e.enrol = :sirh
        ', ['sirh' => 'sirh']);
    }

    /**
     * Return all instance SIRH with sension id and users id.
     *
     * @return \stdClass[];
     * @throws \dml_exception
     */
    public function get_all_instance_sirh_with_user_id() {
        global $CFG;

        if ($CFG->dbtype == 'mysqli') {
            // Not tested yet on Mysql.
            $aggregateusers = "GROUP_CONCAT(u.id SEPARATOR ',')";
        } else {
            $aggregateusers = 'array_agg(u.id)';
        }

        return $this->db->get_records_sql('
            SELECT
                e.*,
                c.id as courseid,
                s.courseshortname as sessionname,
                s.id as sessionid,
                ' . $aggregateusers . ' as usersid
            FROM {session} s
            JOIN {course} c ON s.courseshortname = c.shortname
            JOIN {enrol} e ON c.id = e.courseid
            JOIN {user_enrolments} ue ON ue.enrolid = e.id
            JOIN {user} u ON u.id = ue.userid
            WHERE e.enrol = :sirh
            GROUP BY e.id, c.id, s.courseshortname, sessionid
        ', ['sirh' => 'sirh']);
    }

    /**
     * Get all course completion completed link to session
     * after its last sync information to sirh API.
     *
     * @return array
     * @throws \dml_exception
     */
    public function get_sessions_completed_by_user($statusfilter = []) {
        // Status condition.
        if (empty($statusfilter)) {
            $statusfilter = [
                \local_mentor_core\session::STATUS_IN_PROGRESS,
                \local_mentor_core\session::STATUS_COMPLETED,
            ];
        }

        $wherestatus = '(';

        foreach ($statusfilter as $status) {
            $wherestatus .= 's.status = \'' . $status . '\' OR ';
        }

        $wherestatus = substr($wherestatus, 0, -4);
        $wherestatus .= ')';

        return $this->db->get_records_sql('
            SELECT cc.*
            FROM {course_completions} cc
            JOIN {course} c ON cc.course = c.id
            JOIN {session} s ON c.shortname = s.courseshortname
            JOIN {user} u ON u.id = cc.userid
            WHERE ' . $wherestatus . ' AND
                (s.lastsyncsirh < cc.timecompleted OR s.lastsyncsirh IS NULL) AND
                cc.timecompleted IS NOT NULL AND
                u.deleted = 0
        ');
    }

    /**
     * Update last sync SIRH time for session.
     *
     * @param int $sessionid
     * @param int $lastsync
     * @return void
     * @throws \dml_exception
     */
    public function update_last_sync_sirh_session($sessionid, $lastsync) {
        $session = new \stdClass();
        $session->id = $sessionid;
        $session->lastsyncsirh = $lastsync;
        $this->db->update_record('session', $session);
    }
}
