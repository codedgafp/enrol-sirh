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

class send_session_followup_information extends \core\task\scheduled_task {

    /**
     * @var \enrol_sirh\database_interface
     */
    protected $dbi;

    /**
     * @var \enrol_sirh\sirh
     */
    protected $sirhrest;

    /**
     * Errors list.
     *
     * @var array
     */
    protected $errors;

    /**
     * Errors list.
     *
     * @var array
     */
    protected $statusfilter;

    /**
     * Send scheduled failed if it has error.
     *
     * @var bool
     */
    protected $scheduledfailed;

    public function __construct($statusfilter
    = [
        \local_mentor_core\session::STATUS_IN_PROGRESS,
        \local_mentor_core\session::STATUS_COMPLETED,
    ], $scheduledfailed = true) {
        $this->dbi = \enrol_sirh\database_interface::get_instance();
        $this->sirhrest = \enrol_sirh\sirh_api::get_sirh_rest_api();
        $this->errors = [];
        $this->statusfilter = $statusfilter;
        $this->scheduledfailed = $scheduledfailed;
    }

    /**
     * Task name.
     *
     * @return \lang_string|string
     * @throws \coding_exception
     */
    public function get_name() {
        // Shown in admin screens.
        return get_string('task_send_session_followup_information', 'enrol_sirh');
    }

    public function execute() {
        // Get all completion link to session after its last sync information to sirh API.
        $completions = $this->dbi->get_sessions_completed_by_user($this->statusfilter);

        // Get session completion information.
        $sessionscompletion = $this->get_sessions_completion_information($completions);

        // Create data for API.
        $sessiondata = $this->create_data_for_api($sessionscompletion);

        // Send to API.
        $hasnoerror = $this->send_to_api($sessiondata);

        if ($this->scheduledfailed && !$hasnoerror) {
            // Set task to fail.
            \core\task\manager::scheduled_task_failed($this);
        }
    }

    /**
     * Create data sessions completion information
     * with users and users time when course completed.
     *
     * @param array $completions
     * @return array
     */
    public function get_sessions_completion_information($completions) {
        // Init data.
        $sessionscompletedinformation = [];

        // Parse data to get session completed information with user id and time completed.
        foreach ($completions as $completion) {
            // Init data by session.
            if (!isset($sessionscompletedinformation[$completion->course])) {
                $sessionscompletedinformation[$completion->course] = [];
            }

            // Add user completed completion and time when is course is completed.
            $sessionscompletedinformation[$completion->course][$completion->userid] = $completion->timecompleted;
        }

        return $sessionscompletedinformation;
    }

    /**
     * Create data with right format for API SIRH.
     *
     * @param $sessionscompletion
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function create_data_for_api($sessionscompletion) {
        // Init cache.
        $users = [];
        $sirhbyentity = [];

        // Init result data.
        $sendinformation = [];

        // Get all instance SIRH data.
        $sessionswithinstancesirh = $this->dbi->get_all_instance_sirh_with_user_id();
        // Sirh data : key SIRH id => value courseid (session course).
        $instancesirhbycoursid = array_column($sessionswithinstancesirh, 'courseid', 'id');

        // Create data for SIRH API.
        foreach ($sessionscompletion as $courseid => $sessioncompletion) {

            // Get session.
            $session = \local_mentor_core\session_api::get_session_by_course_id($courseid);

            // Get session training.
            $training = $session->get_training();

            // Init session date.
            $sessionmentor = new \stdClass();
            $sessionmentor->identifiantSessionMentor = $session->id;
            $sessionmentor->nomAbregeFormation = $training->shortname;
            $sessionmentor->nomAbregeSession = $session->courseshortname;
            $sessionmentor->libelleFormation = $training->name;
            $sessionmentor->libelleSession = $session->fullname;
            $sessionmentor->identifiantSirhOrigineFormation = $training->idsirh;
            $sessionmentor->dateDebut = date('Y-m-d', $session->sessionstartdate);
            $sessionmentor->dateFin = date('Y-m-d', $session->sessionenddate);
            $sendinformation[$session->id] = new \stdClass();
            $sendinformation[$session->id]->sessionMentor = $sessionmentor;

            // Init user session data.
            $usersinfo = [];

            // Get SIRH instance with user list.
            $enrolsirhid = array_search($courseid, $instancesirhbycoursid);

            // Create user session data.
            foreach ($sessioncompletion as $userid => $timecompleted) {
                // Init user data.
                $userinfo = new \stdClass();
                $userinfo->{'Suivi session utilisateur'} = new \stdClass();
                $userinfo->{'Suivi session utilisateur'}->dateAchevement = date('Y-m-d', $timecompleted);
                $userinfo->{'Suivi session utilisateur'}->identifiantSirhOrigine = null;
                $userinfo->{'Suivi session utilisateur'}->identifiantFormation = null;
                $userinfo->{'Suivi session utilisateur'}->identifiantSession = null;

                // Session has enrol SIRH instance.
                if ($enrolsirhid !== false) {
                    // Check if user enrolled to enrol SIRH instance.
                    $enrolsirh = $sessionswithinstancesirh[$enrolsirhid];
                    $usersidenroled = explode(',', trim($enrolsirh->usersid, '{}'));
                    if (array_search($userid, $usersidenroled)) {
                        // Add SIRH data.
                        $userinfo->{'Suivi session utilisateur'}->identifiantSirhOrigine = $enrolsirh->customchar1;
                        $userinfo->{'Suivi session utilisateur'}->identifiantFormation = $enrolsirh->customchar2;
                        $userinfo->{'Suivi session utilisateur'}->identifiantSession = $enrolsirh->customchar3;
                    }
                }

                // User is not to cache.
                if (!isset($users[$userid])) {
                    // Set user cache.
                    $users[$userid] = \core_user::get_user($userid);
                }

                // Set user data.
                $userinfo->email = $users[$userid]->email;
                $userinfo->nom = $users[$userid]->lastname;
                $userinfo->prenom = $users[$userid]->firstname;

                // Get main user entity.
                $mainentity = profile_user_record($userid)->mainentity;

                // SIRH entity data is not to cache.
                if (!isset($sirhbyentity[$mainentity]) || empty($sirhbyentity[$mainentity])) {
                    // Get SIRH entity data.
                    $usermainentity = \local_mentor_core\entity_api::get_entity_by_name($mainentity);
                    $sirhlist = $usermainentity ? $usermainentity->get_sirh_list() : [];
                    $sirhbyentity[$mainentity] = [];

                    // Add SIRH entity data to cache.
                    foreach ($sirhlist as $sirh) {
                        $sirhdata = new \stdClass();
                        $sirhdata->identifiantSIRH = $sirh;
                        $sirhbyentity[$mainentity][] = $sirhdata;
                    }
                }

                // Set user SIRH main entity data.
                $userinfo->listeSIRHUtilisateur = $sirhbyentity[$mainentity];

                // Add user data.
                $usersinfo[] = $userinfo;
            }

            // Add users data.
            $sendinformation[$session->id]->utilisateurs = $usersinfo;
        }

        // Return sessions data with SIRH API format.
        return $sendinformation;
    }

    /**
     * Send session data one by one
     * return true if it has no error
     * Else return false
     *
     * @param array $sessionsdata
     * @return bool
     * @throws \Exception
     */
    public function send_to_api($sessionsdata) {
        $hasnoerror = true;

        foreach ($sessionsdata as $sessiondata) {
            // Sent in bundles of a hundred.
            while (count($sessiondata->utilisateurs) > 100) {
                $otheruser = array_splice($sessiondata->utilisateurs, 100);

                // Send session data to SIRH API.
                $response = \enrol_sirh\sirh_api::follow_up_session($sessiondata);

                // Check error.
                $hasnoerror = !$this->check_errors($response, $sessiondata) && $hasnoerror;

                $sessiondata->utilisateurs = $otheruser;
            }

            // Send session data to SIRH API.
            $response = \enrol_sirh\sirh_api::follow_up_session($sessiondata);

            // Check error.
            $hasnoerror = !$this->check_errors($response, $sessiondata) && $hasnoerror;
        }

        return $hasnoerror;
    }

    /**
     * Check errors result and manages them.
     *
     * @param $result
     * @return bool
     */
    public function check_errors($result, $sessiondata) {
        global $CFG;

        // No error.
        if ($result === true) {
            return false;
        }

        // Show call API information.
        mtrace('------------');
        mtrace('Erreur durant l\'appel ' . $CFG->sirh_api_url . 'v1/suiviSession');
        mtrace('Status  : ' . $result->status);
        mtrace('Données : ' . json_encode($sessiondata, JSON_PRETTY_PRINT));

        // If is standard errors.
        if (!isset($result->erreurs)) {
            mtrace('Erreur : ' . json_encode($result, JSON_PRETTY_PRINT));
            return true;
        }

        // Is Mentor API errors.
        mtrace('liste des erreurs :');
        foreach ($result->erreurs as $key => $error) {
            mtrace(' - ' . $key . ' : ' . $error);
        }

        return true;
    }
}
