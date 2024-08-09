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
 * Automatically check update sirh
 *
 * @package    enrol_sirh
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     remi colet <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_sirh\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/enrol/sirh/classes/api/sirh.php');
require_once($CFG->dirroot . '/enrol/sirh/locallib.php');
require_once($CFG->dirroot . '/local/mentor_core/api/session.php');

class check_update_sirh extends \core\task\scheduled_task {

    protected $dbi;

    protected $sirhrest;

    public function __construct() {
        $this->dbi = \enrol_sirh\database_interface::get_instance();
        $this->sirhrest = \enrol_sirh\sirh_api::get_sirh_rest_api();
    }

    /**
     * Task name.
     *
     * @return \lang_string|string
     * @throws \coding_exception
     */
    public function get_name() {
        // Shown in admin screens.
        return get_string('task_check_update_sirh', 'enrol_sirh');
    }

    public function execute() {
        // Get all instance SIRH.
        $enrolsirhinstances = $this->dbi->get_all_instance_sirh();

        foreach ($enrolsirhinstances as $enrolsirhinstance) {
            // Get all users link with SIRH session.
            $users = $this->sirhrest->get_session_users($enrolsirhinstance->customchar1, $enrolsirhinstance->customchar2,
                $enrolsirhinstance->customchar3, null, $enrolsirhinstance->customint3);

            // No update to session SIRH data or list user sync.
            if (!$users || (!$users['updateSession'] && !$users['updateUsers'])) {
                continue;
            }

            // Get last user id sync enrol SIRH instance.
            $syncuserid = (int) $enrolsirhinstance->customint2;

            // Update to list user sync to session SIRH.
            if ($users['updateUsers']) {
                // Get validate users.
                enrol_sirh_validate_users($users['users'], $enrolsirhinstance, SIRH_NOTIFICATION_TYPE_MTRACE, $preview);

                if (\enrol_sirh\sirh_api::synchronize_users($enrolsirhinstance, $preview['list'])) {
                    \enrol_sirh\sirh_api::update_sirh_instance_sync_data($enrolsirhinstance, false);

                    // Update to session SIRH data.
                    if ($users['updateSession']) {
                        $this->send_email_update_data($enrolsirhinstance, $users['sessionSirh'], $enrolsirhinstance->courseid,
                            $syncuserid);
                    }

                    $this->send_email_update_user($enrolsirhinstance->sessionname, $enrolsirhinstance->courseid, $enrolsirhinstance,
                        $syncuserid);
                }

                continue;
            }

            // Update to session SIRH data.
            if ($users['updateSession']) {
                \enrol_sirh\sirh_api::update_sirh_instance_sync_data($enrolsirhinstance, false);

                $this->send_email_update_data($enrolsirhinstance, $users['sessionSirh'], $enrolsirhinstance->courseid,
                    $syncuserid);
            }
        }

    }

    /**
     * Send email to user with update session SIRH information.
     *
     * @param \stdClass $enrolsirhinstance
     * @param \stdClass $apisirh
     * @param int $courseid
     * @param \stdClass|int $user
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function send_email_update_data($enrolsirhinstance, $apisirh, $courseid, $user) {

        // Get session course url.
        $sessioncourseurl = new \moodle_url('/course/view.php', ['id' => $courseid]);

        // Message text.
        $messagetext = get_string(
            'upate_data_sirh_email',
            'enrol_sirh',
            [
                'sirh' => $enrolsirhinstance->customchar1,
                'trainingsirh' => $enrolsirhinstance->customchar2,
                'sessionsirh' => $enrolsirhinstance->customchar3,
                'nametrainingsirh' => $apisirh->libelleFormation,
                'namesessionsirh' => $apisirh->libelleSession,
                'startdate' => $apisirh->dateDebut,
                'enddate' => $apisirh->dateFin,
                'sessionurl' => $sessioncourseurl->out(),
            ]
        );

        // Message subject.
        $subject = get_string('upate_data_sirh_subject', 'enrol_sirh');

        // Send email.
        return $this->send_email($messagetext, $subject, $user);
    }

    /**
     * Send email to user with update enrol user information.
     *
     * @param string $sessionname
     * @param int $courseid
     * @param \stdClass $enrolsirhinstance
     * @param \stdClass|int $user
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function send_email_update_user($sessionname, $courseid, $enrolsirhinstance, $user) {

        // Get session course url.
        $sessioncourseurl = new \moodle_url('/course/view.php', ['id' => $courseid]);

        // Message text.
        $messagetext = get_string(
            'upate_user_sirh_email',
            'enrol_sirh',
            [
                'sessionname' => $sessionname,
                'sirh' => $enrolsirhinstance->customchar1,
                'trainingsirh' => $enrolsirhinstance->customchar2,
                'sessionsirh' => $enrolsirhinstance->customchar3,
                'sessionurl' => $sessioncourseurl->out(),
            ]
        );

        // Message subject.
        $subject = get_string('upate_user_sirh_subject', 'enrol_sirh');

        // Send email.
        return $this->send_email($messagetext, $subject, $user);
    }

    /**
     * Send email to user.
     *
     * @param string $messagetext
     * @param string $subject
     * @param \stdClass|int $user
     * @return bool
     * @throws \dml_exception
     */
    public function send_email($messagetext, $subject, $user) {

        // Check if variable is int.
        if (is_int($user)) {
            $user = \core_user::get_user($user);
        }

        // Send message as support user.
        $supportuser = \core_user::get_support_user();

        // Message HTML.
        $messagehtml = text_to_html($messagetext, false, false, true);

        // Send email to user.
        return email_to_user($user, $supportuser, $subject, $messagetext, $messagehtml);
    }
}
