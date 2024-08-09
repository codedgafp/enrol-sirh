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
 * SIRH API REST client.
 *
 * @package    enrol_sirh
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     remi <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_sirh;

use mysql_xdevapi\Exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/enrol/sirh/classes/restclient.php');

/**
 * SIRH REST client class.
 */
class sirh {

    /**
     * @var string
     */
    protected $key;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var string
     */
    protected $inputsufix;

    /**
     * @var string
     */
    protected $outputsufix;

    /**
     * @var \RestClient
     */
    protected $sirhapi;

    /**
     * @var self
     */
    protected static $instance;

    /**
     * __construct Class.
     */
    public function __construct() {
        global $CFG;

        $this->url = $CFG->sirh_api_url;
        $this->key = $CFG->sirh_api_token;
        $this->inputsufix = "Input";
        $this->outputsufix = "Output";

        $this->sirhapi = new \RestClient([
            'base_url' => $CFG->sirh_api_url,
            'curl_options' => [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => 'CURL_HTTP_VERSION_1_1',
            ],
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $CFG->sirh_api_token,
            ],
        ]);
    }

    /**
     * Create a singleton
     *
     * @return sirh
     */
    public static function get_instance() {

        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }
        return self::$instance;

    }

    /**
     * Check response status
     *
     * @param $result
     * @throws \Exception
     */
    private function check_status($result) {
        $invalidstatus = [
            'HTTP/1.1 404',
            'HTTP/1.1 500',
            'HTTP/1.1 504',
        ];

        // Check service status.
        if (!isset($result->response_status_lines) || in_array($result->response_status_lines[0], $invalidstatus)) {
            throw new \moodle_exception('servicenotavailable', 'enrol_sirh', '');
        }
    }

    /**
     * Get sirh_sessions
     *
     * @param array $filters
     * @return array
     * @throws \Exception
     */
    public function get_sirh_sessions($filters) {

        // Remove empty values.
        foreach ($filters as $filterindex => $filtervalue) {
            if (empty($filtervalue) || is_null($filtervalue)) {
                unset($filters[$filterindex]);
            }
        }

        // Get users with API.
        $result = $this->sirhapi->get("v1/sessions", $filters);

        $this->check_status($result);

        // No results.
        if ($result->response_status_lines[0] == 'HTTP/1.1 400') {
            return [];
        }

        // Decode the response.
        $response = json_decode($result->response);

        $sirhsessions = $response->contenu;

        $sirhs = [];

        foreach ($sirhsessions as $sirhsession) {
            $session = new \stdClass();
            $session->sirh = $sirhsession->identifiantSirhOrigine;
            $session->sirhtraining = $sirhsession->identifiantFormation;
            $session->sirhtrainingname = $sirhsession->libelleFormation;
            $session->sirhsession = $sirhsession->identifiantSession;
            $session->sirhsessionname = $sirhsession->libelleSession;

            // Convert startdate format.
            $explodedstart = explode('-', $sirhsession->dateDebut);
            $session->startdate = $explodedstart[2] . '/' . $explodedstart[1] . '/' . $explodedstart[0];

            // Convert enddate format.
            $explodedend = explode('-', $sirhsession->dateFin);
            $session->enddate = $explodedend[2] . '/' . $explodedend[1] . '/' . $explodedend[0];

            $sirhs[] = $session;
        }

        return $sirhs;
    }

    /**
     * Count sirh_sessions
     *
     * @param array $filters
     * @return int
     * @throws \Exception
     */
    public function count_sirh_sessions($filters) {

        // Remove empty values.
        foreach ($filters as $filterindex => $filtervalue) {
            if (empty($filtervalue) || is_null($filtervalue)) {
                unset($filters[$filterindex]);
            }
        }

        // Get users with API.
        $result = $this->sirhapi->get("v1/sessions", $filters);

        $this->check_status($result);

        // No results.
        if ($result->response_status_lines[0] == 'HTTP/1.1 400') {
            return 0;
        }

        // Decode the response.
        $response = json_decode($result->response);

        return $response->totalElements;
    }

    /**
     * Get sirh session users
     *
     * @param $sirh
     * @param $sirhtraining
     * @param $sirhsession
     * @param null|int $nbusers
     * @param null|int $lastsync
     * @return array|false
     */
    public function get_session_users($sirh, $sirhtraining, $sirhsession, $nbusers = null, $lastsync = null) {
        // Get users with API.
        $filters = [
            'identifiantSirhOrigine' => $sirh,
            'identifiantFormation' => $sirhtraining,
            'identifiantSession' => $sirhsession,
        ];

        // Add last sync date.
        if (!is_null($lastsync)) {
            $date = date('Y-m-d\TH:i:s', $lastsync);
            $filters['dateDerniereSynchronisation'] = $date;
        }

        // Call GET request.
        $result = $this->sirhapi->get("v1/inscriptions", $filters);

        $this->check_status($result);

        // No results.
        if ($result->response_status_lines[0] == 'HTTP/1.1 400') {
            return false;
        }

        $response = json_decode($result->response);

        $sirhusers = $response->UtilisateurSirh;

        $users = [];

        foreach ($sirhusers as $sirhuser) {
            $user = new \stdClass();
            $user->lastname = $sirhuser->nom;
            $user->firstname = $sirhuser->prenom;
            $user->email = strtolower($sirhuser->email);
            $user->username = strtolower($sirhuser->email);
            $user->mnethostid = 1;
            $user->confirmed = 1;

            $users[] = $user;
        }

        // Slice session SIRH users if user requests it.
        $finalusers = is_null($nbusers) ? $users : array_slice($users, 0, $nbusers);

        return [
            'nbTotalUsers' => $response->NombreUtilisateursInscrits,
            'users' => $finalusers,
            'nbUsers' => count($finalusers),
            'updateSession' => $response->IndicateurMajSession,
            'updateUsers' => $response->IndicateurMajInscriptions,
            'sessionSirh' => $response->SessionSirh,
        ];
    }

    /**
     * Send session information to API SIRH and
     * return response with success information.
     *
     * @param string $sessionjson
     * @return \stdClass
     * @throws \Exception
     */
    public function follow_up_session(string $sessionjson) {
        // PUT API call.
        $result = $this->sirhapi->put(
            "v1/suiviSession",
            $sessionjson,
            [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        );

        // Check status result.
        $this->check_status($result);

        // Decode the response.
        $response = json_decode($result->response);

        // Init success data.
        $response->success = true;

        // Check status.
        if ($result->response_status_lines[0] !== 'HTTP/1.1 201') {
            // Result has errors - success is false.
            $response->success = false;
            $response->status = $result->response_status_lines[0];
        }

        // Return result.
        return $response;
    }
}
