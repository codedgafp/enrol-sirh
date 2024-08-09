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
 * User controller
 *
 * @package    enrol_sirh
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     remi <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_sirh;

use local_mentor_core\controller_base;

require_once(__DIR__ . '/../../../../config.php');

require_login();

require_once($CFG->dirroot . '/local/user/lib.php');
require_once($CFG->dirroot . '/local/mentor_core/api/profile.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/controllers/controller_base.php');
require_once($CFG->dirroot . '/enrol/sirh/externallib.php');
require_once($CFG->dirroot . '/enrol/sirh/classes/api/sirh.php');

defined('MOODLE_INTERNAL') || die();

class sirh_controller extends controller_base {

    /**
     * Execute action
     *
     * @return array
     */
    public function execute() {

        try {
            $action = $this->get_param('action');

            switch ($action) {
                case 'get_sirh_sessions':

                    $sessionid = $this->get_param('sessionid', PARAM_INT);

                    // Count all sessions.
                    $data = new \stdClass();
                    $filter['listeIdentifiantSirhOrigine'] = implode(',', $this->get_param('sirh', PARAM_TEXT));
                    $data->recordsTotal = $this->count_sirh_sessions($sessionid, $filter);

                    // Count filtered sessions.
                    $filter['identifiantFormation'] = $this->get_param('sirhtraining', PARAM_TEXT, null);
                    $filter['identifiantSession'] = $this->get_param('sirhsession', PARAM_TEXT, null);
                    $filter['libelleSession'] = $this->get_param('sirhsessionname', PARAM_TEXT, null);
                    $filter['libelleFormation'] = $this->get_param('sirhtrainingname', PARAM_TEXT, null);
                    $filter['dateDebut'] = $this->get_param('datestart', PARAM_TEXT, null);
                    $filter['dateFin'] = $this->get_param('dateend', PARAM_TEXT, null);
                    $data->recordsFiltered = $this->count_sirh_sessions($sessionid, $filter);

                    $order = $this->get_param('order', PARAM_RAW, null);
                    $order = is_null($order) ? $order : $order[0];

                    // Manage sort order.
                    if (!is_null($order)) {
                        $columnsorder = [
                            'identifiantFormation',
                            'libelleFormation',
                            'identifiantSession',
                            'libelleSession',
                            'dateDebut',
                            'dateFin',
                            'actions',
                        ];

                        if ($columnsorder[$order['column']] != 'actions') {
                            $filter['tris'] = $columnsorder[$order['column']] . ' ' . strtoupper($order['dir']);
                        } else {
                            $filter['filterbyactions'] = 1;
                            $filter['filterbyactionsdir'] = strtoupper($order['dir']);
                        }
                    } else {
                        $filter['filterbyactions'] = 1;
                        $filter['filterbyactionsdir'] = 'ASC';
                    }

                    $filter['nombreElementPage'] = $this->get_param('length', PARAM_INT, null);

                    if (!is_null($filter['nombreElementPage'])) {

                        $filter['numeroPage'] = $this->get_param('start', PARAM_INT, null);

                        if (!is_null($filter['numeroPage'])) {
                            $filter['numeroPage'] = $filter['numeroPage'] / $filter['nombreElementPage'] + 1;
                        }
                    }

                    // Get filtered results.
                    $data->data = $this->get_sirh_sessions($sessionid, $filter);

                    $data->length = $this->get_param('length', PARAM_INT, null);
                    $data->start = $this->get_param('start', PARAM_INT, null);
                    $data->order = false;
                    $data->draw = $this->get_param('draw', PARAM_INT, null);
                    $data->filters = $this->get_param('filter', PARAM_RAW, []);

                    return $data;
                case 'get_session_users':
                    $sessionid = $this->get_param('sessionid', PARAM_INT);
                    $sirh = $this->get_param('sirh', PARAM_TEXT);
                    $sirhtraining = $this->get_param('sirhtraining', PARAM_TEXT);
                    $sirhsession = $this->get_param('sirhsession', PARAM_TEXT);
                    $nbuser = $this->get_param('nbuser', PARAM_INT, 10);
                    return $this->success($this->get_session_users($sessionid, $sirh, $sirhtraining, $sirhsession, $nbuser));
                case 'enrol_users_sirh':
                    $sessionid = $this->get_param('sessionid', PARAM_INT);
                    $sirh = $this->get_param('sirh', PARAM_TEXT);
                    $sirhtraining = $this->get_param('sirhtraining', PARAM_TEXT);
                    $sirhsession = $this->get_param('sirhsession', PARAM_TEXT);
                    return $this->success($this->enrol_users_sirh($sessionid, $sirh, $sirhtraining, $sirhsession));
                default:
                    return $this->error('Action not found . ');
            }

        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

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
        return sirh_api::get_sirh_sessions($sessionid, $filter);
    }

    /**
     * Count SIRH sessions
     *
     * @param int $sessionid
     * @param array $filter
     * @return int
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function count_sirh_sessions($sessionid, $filter) {
        return sirh_api::count_sirh_sessions($sessionid, $filter);
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
    public static function get_session_users($sessionid, $sirh, $sirhtraining, $sirhsession, $nbuser) {
        return sirh_api::get_session_users($sessionid, $sirh, $sirhtraining, $sirhsession, $nbuser);
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
        return sirh_api::enrol_users_sirh($sessionid, $sirh, $sirhtraining, $sirhsession);
    }
}
