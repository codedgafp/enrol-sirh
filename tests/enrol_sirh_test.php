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
 *
 *
 * @package    enrol_sirh
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     Remi Colet <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/enrol/sirh/classes/api/sirh.php');
require_once($CFG->dirroot . '/enrol/sirh/externallib.php');

class enrol_sirh_testcase extends advanced_testcase {

    /**
     * Init $CFG
     */
    public function init_config() {
        global $CFG;

        $CFG->mentor_specializations = [
            '\\local_mentor_specialization\\mentor_specialization' =>
                'local/mentor_specialization/classes/mentor_specialization.php',
        ];

        // SIRH API.
        $CFG->sirh_api_url = "www.sirh.fr";
        $CFG->sirh_api_token = "FALSEKEY";
        $CFG->defaultauth = 'manual';
    }

    /**
     * Reset the singletons
     *
     * @throws ReflectionException
     */
    public function reset_singletons() {
        // Reset the mentor core specialization singleton.
        $specialization = \local_mentor_core\specialization::get_instance();
        $reflection = new ReflectionClass($specialization);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true); // Now we can modify that :).
        $instance->setValue(null, null); // Instance is gone.
        $instance->setAccessible(false); // Clean up.

        \local_mentor_core\training_api::clear_cache();

        // Reset the mentor core db interface singleton.
        $dbinterface = \format_edadmin\database_interface::get_instance();
        $reflection = new ReflectionClass($dbinterface);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true); // Now we can modify that :).
        $instance->setValue(null, null); // Instance is gone.
        $instance->setAccessible(false); // Clean up.
    }

    /**
     * Initialization of the user data
     *
     * @return int
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function init_create_user() {
        global $DB;

        // Create user.
        $user = new stdClass();
        $user->lastname = 'lastname';
        $user->firstname = 'firstname';
        $user->email = 'test@test.com';
        $user->username = 'testusername';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';

        $userid = local_mentor_core\profile_api::create_user($user);
        set_user_preference('auth_forcepasswordchange', 0, $user);

        $field = $DB->get_record('user_info_field', ['shortname' => 'mainentity']);

        $userdata = new stdClass();
        $userdata->fieldid = $field->id;
        $userdata->data = 'New Entity 1';
        $userdata->userid = $userid;

        $DB->insert_record('user_info_data', $userdata);

        return $userid;
    }

    /**
     * Initialization of the session or trainig data
     *
     * @param false $training
     * @param null $sessionid
     * @return stdClass
     */
    public function init_session_data($training = false, $sessionid = null) {
        $data = new stdClass();

        set_config('collections', 'accompagnement|Accompagnement des transitions professionnelles|#CECECE',
            'local_mentor_specialization');

        if ($training) {
            $data->name = 'fullname';
            $data->shortname = 'shortname';
            $data->content = 'summary';
            $data->status = 'ec';
        } else {
            $data->trainingname = 'fullname';
            $data->trainingshortname = 'shortname';
            $data->trainingcontent = 'summary';
            $data->trainingstatus = 'ec';
        }

        // Fields for taining.
        $data->teaser = 'http://www.edunao.com/';
        $data->teaserpicture = '';
        $data->prerequisite = 'TEST';
        $data->collection = 'accompagnement';
        $data->traininggoal = 'TEST TRAINING ';
        $data->idsirh = 'TEST ID SIRH';
        $data->licenseterms = 'cc-sa';
        $data->typicaljob = 'TEST';
        $data->skills = [];
        $data->certifying = '1';
        $data->presenceestimatedtimehours = '12';
        $data->presenceestimatedtimeminutes = '10';
        $data->remoteestimatedtimehours = '15';
        $data->remoteestimatedtimeminutes = '30';
        $data->trainingmodalities = 'd';
        $data->producingorganization = 'TEST';
        $data->producerorganizationlogo = '';
        $data->designers = 'TEST';
        $data->contactproducerorganization = 'TEST';
        $data->thumbnail = '';

        // Specific fields for session (only for update).
        if ($sessionid) {
            $data->id = $sessionid;
            $data->opento = 'all';
            $data->publiccible = 'TEST';
            $data->termsregistration = 'autre';
            $data->termsregistrationdetail = 'TEST';

            $data->onlinesessionestimatedtimehours = '10';
            $data->onlinesessionestimatedtimeminutes = '15';
            $data->presencesessionestimatedtimehours = '12';
            $data->presencesessionestimatedtimeminutes = '25';

            $data->sessionpermanent = 0;
            $data->sessionstartdate = 1609801200;
            $data->sessionenddate = 1609801200;
            $data->sessionmodalities = 'presentiel';
            $data->accompaniment = 'TEST';
            $data->maxparticipants = 10;
            $data->placesavailable = 8;
            $data->numberparticipants = 2;
            $data->location = 'PARIS';
            $data->organizingstructure = 'TEST ORGANISATION';
            $data->sessionnumber = 1;
            $data->opentolist = '';
        }

        return $data;
    }

    /**
     * Init training creation
     *
     * @return training
     * @throws moodle_exception
     */
    public function init_training_creation() {
        global $DB;

        // Remove the miscelleanous category.
        $DB->delete_records('course_categories', ['id' => 1]);

        // Init test data.
        $data = $this->init_session_data(true);

        try {
            // Get entity object for default category.
            $entityid = \local_mentor_core\entity_api::create_entity([
                'name' => 'New Entity 1',
                'shortname' => 'New Entity 1',
                'sirhlist' => 'RENOIRH_AES',
            ]);

            $entity = \local_mentor_core\entity_api::get_entity($entityid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Init data with entity data.
        $data = $this->init_training_entity($data, $entity);

        // Test standard training creation.
        try {
            $training = \local_mentor_core\training_api::create_training($data);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        return $training;
    }

    /**
     * Init session creation
     *
     * @return int
     * @throws moodle_exception
     */
    public function init_session_creation() {
        // Create training.
        $training = $this->init_training_creation();

        $sessionname = 'TESTUNITCREATESESSION';

        // Test standard session creation.
        try {
            $session = \local_mentor_core\session_api::create_session($training->id, $sessionname, true);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Open to current entity.
        $data = new stdClass();
        $data->opento = 'current_entity';
        $session->update($data);

        return $session->id;
    }

    /**
     * Init training categery by entity id
     */
    public function init_training_entity($data, $entity) {
        // Get "Formation" category id (child of entity category).
        $formationid = $entity->get_entity_formation_category();
        $data->categorychildid = $formationid;

        $data->categoryid = $entity->id;
        $data->creativestructure = $entity->id;

        return $data;
    }

    /**
     * Init training category by entity id
     */
    public function init_session_entity($data, $entity) {
        // Get "Formation" category id (child of entity category).
        $formationid = $entity->get_entity_formation_category();
        $data->categorychildid = $formationid;

        $data->categoryid = $entity->id;
        $data->creativestructure = $entity->id;

        return $data;
    }

    /**
     * Test get sirh session function OK
     *
     * @covers  \enrol_sirh\sirh_api::get_sirh_sessions
     * @covers  \enrol_sirh\sirh_api::check_enrol_sirh_capability
     */
    public function test_get_sirh_session_ok() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Set filter.
        $filter = [];
        $filter['listeIdentifiantSirhOrigine'] = "RENOIRH_AES";
        $filter['nombreElementPage'] = 50;
        $filter['numeroPage'] = 1;

        // Call get_sirh_sessions function.
        $sirhsessions = sirh_api_test_mocked_enrol_sirh::get_sirh_sessions($sessionid, $filter);

        self::assertIsArray($sirhsessions);
        self::assertCount(1, $sirhsessions);

        self::assertObjectHasAttribute('sirh', $sirhsessions[0]);
        self::assertEquals('RENOIRH_AES', $sirhsessions[0]->sirh);
        self::assertObjectHasAttribute('sirhtraining', $sirhsessions[0]);
        self::assertEquals('RENOIRH_AES_TRAINING', $sirhsessions[0]->sirhtraining);
        self::assertObjectHasAttribute('sirhtrainingname', $sirhsessions[0]);
        self::assertEquals('RENOIRH_AES_TRAINING_NAME', $sirhsessions[0]->sirhtrainingname);
        self::assertObjectHasAttribute('sirhsession', $sirhsessions[0]);
        self::assertEquals('RENOIRH_AES_SESSION', $sirhsessions[0]->sirhsession);
        self::assertObjectHasAttribute('sirhsessionname', $sirhsessions[0]);
        self::assertEquals('RENOIRH_AES_SESSION_NAME', $sirhsessions[0]->sirhsessionname);
        self::assertObjectHasAttribute('startdate', $sirhsessions[0]);
        self::assertEquals('RENOIRH_AES_START', $sirhsessions[0]->startdate);
        self::assertObjectHasAttribute('enddate', $sirhsessions[0]);
        self::assertEquals('RENOIRH_AES_END', $sirhsessions[0]->enddate);
        self::assertObjectHasAttribute('instanceexists', $sirhsessions[0]);
        self::assertFalse($sirhsessions[0]->instanceexists);

        // Enrol not exist.
        self::assertFalse($sirhsessions[0]->instanceexists);

        self::resetAllData();
    }

    /**
     * Test get sirh session function OK
     * With existing enrol
     *
     * @covers  \enrol_sirh\sirh_api::get_sirh_sessions
     * @covers  \enrol_sirh\sirh_api::check_enrol_sirh_capability
     */
    public function test_get_sirh_session_ok_existing_enrol() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $courseid = $session->get_course()->id;

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
        $instance->customchar1 = 'RENOIRH_AES';
        $instance->customchar2 = 'RENOIRH_AES_TRAINING';
        $instance->customchar3 = 'RENOIRH_AES_SESSION';
        $fields = (array) $instance;
        $plugin->add_instance(get_course($courseid), $fields);

        // Set filter.
        $filter = [];
        $filter['listeIdentifiantSirhOrigine'] = "RENOIRH_AES";
        $filter['nombreElementPage'] = 50;
        $filter['numeroPage'] = 1;

        // Call get_sirh_sessions function.
        $sirhsessions = sirh_api_test_mocked_enrol_sirh::get_sirh_sessions($sessionid, $filter);

        self::assertIsArray($sirhsessions);
        self::assertCount(1, $sirhsessions);

        self::assertObjectHasAttribute('sirh', $sirhsessions[0]);
        self::assertEquals('RENOIRH_AES', $sirhsessions[0]->sirh);
        self::assertObjectHasAttribute('sirhtraining', $sirhsessions[0]);
        self::assertEquals('RENOIRH_AES_TRAINING', $sirhsessions[0]->sirhtraining);
        self::assertObjectHasAttribute('sirhtrainingname', $sirhsessions[0]);
        self::assertEquals('RENOIRH_AES_TRAINING_NAME', $sirhsessions[0]->sirhtrainingname);
        self::assertObjectHasAttribute('sirhsession', $sirhsessions[0]);
        self::assertEquals('RENOIRH_AES_SESSION', $sirhsessions[0]->sirhsession);
        self::assertObjectHasAttribute('sirhsessionname', $sirhsessions[0]);
        self::assertEquals('RENOIRH_AES_SESSION_NAME', $sirhsessions[0]->sirhsessionname);
        self::assertObjectHasAttribute('startdate', $sirhsessions[0]);
        self::assertEquals('RENOIRH_AES_START', $sirhsessions[0]->startdate);
        self::assertObjectHasAttribute('enddate', $sirhsessions[0]);
        self::assertEquals('RENOIRH_AES_END', $sirhsessions[0]->enddate);
        self::assertObjectHasAttribute('instanceexists', $sirhsessions[0]);
        self::assertTrue($sirhsessions[0]->instanceexists);

        // Enrol Exist.
        self::assertTrue($sirhsessions[0]->instanceexists);

        self::resetAllData();
    }

    /**
     * Test get sirh session function OK
     * With multiple SIRH filter
     *
     * @covers  \enrol_sirh\sirh_api::get_sirh_sessions
     * @covers  \enrol_sirh\sirh_api::check_enrol_sirh_capability
     */
    public function test_get_sirh_session_ok_multiple_sirh_filter() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $courseid = $session->get_course()->id;

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
        $instance->customchar1 = 'RENOIRH_AES';
        $instance->customchar2 = 'RENOIRH_AES_TRAINING';
        $instance->customchar3 = 'RENOIRH_AES_SESSION';
        $fields = (array) $instance;
        $plugin->add_instance(get_course($courseid), $fields);

        // Set filter.
        $filter = [];
        $filter['listeIdentifiantSirhOrigine'] = "RENOIRH_AES,RENOIRH_AES_2";
        $filter['nombreElementPage'] = 50;
        $filter['numeroPage'] = 1;

        // Call get_sirh_sessions function.
        $sirhsessions = multiple_sirh_api_test_mocked_enrol_sirh::get_sirh_sessions($sessionid, $filter);

        self::assertIsArray($sirhsessions);
        self::assertCount(2, $sirhsessions);

        self::assertObjectHasAttribute('sirh', $sirhsessions[0]);
        self::assertEquals('RENOIRH_AES', $sirhsessions[0]->sirh);
        self::assertObjectHasAttribute('sirhtraining', $sirhsessions[0]);
        self::assertEquals('RENOIRH_AES_TRAINING', $sirhsessions[0]->sirhtraining);
        self::assertObjectHasAttribute('sirhtrainingname', $sirhsessions[0]);
        self::assertEquals('RENOIRH_AES_TRAINING_NAME', $sirhsessions[0]->sirhtrainingname);
        self::assertObjectHasAttribute('sirhsession', $sirhsessions[0]);
        self::assertEquals('RENOIRH_AES_SESSION', $sirhsessions[0]->sirhsession);
        self::assertObjectHasAttribute('sirhsessionname', $sirhsessions[0]);
        self::assertEquals('RENOIRH_AES_SESSION_NAME', $sirhsessions[0]->sirhsessionname);
        self::assertObjectHasAttribute('startdate', $sirhsessions[0]);
        self::assertEquals('RENOIRH_AES_START', $sirhsessions[0]->startdate);
        self::assertObjectHasAttribute('enddate', $sirhsessions[0]);
        self::assertEquals('RENOIRH_AES_END', $sirhsessions[0]->enddate);
        self::assertObjectHasAttribute('instanceexists', $sirhsessions[0]);

        // Enrol exist.
        self::assertTrue($sirhsessions[0]->instanceexists);

        self::assertObjectHasAttribute('sirh', $sirhsessions[1]);
        self::assertEquals('RENOIRH_AES_2', $sirhsessions[1]->sirh);
        self::assertObjectHasAttribute('sirhtraining', $sirhsessions[1]);
        self::assertEquals('RENOIRH_AES_TRAINING_2', $sirhsessions[1]->sirhtraining);
        self::assertObjectHasAttribute('sirhtrainingname', $sirhsessions[1]);
        self::assertEquals('RENOIRH_AES_TRAINING_NAME_2', $sirhsessions[1]->sirhtrainingname);
        self::assertObjectHasAttribute('sirhsession', $sirhsessions[1]);
        self::assertEquals('RENOIRH_AES_SESSION_2', $sirhsessions[1]->sirhsession);
        self::assertObjectHasAttribute('sirhsessionname', $sirhsessions[1]);
        self::assertEquals('RENOIRH_AES_SESSION_NAME_2', $sirhsessions[1]->sirhsessionname);
        self::assertObjectHasAttribute('startdate', $sirhsessions[1]);
        self::assertEquals('RENOIRH_AES_START_2', $sirhsessions[1]->startdate);
        self::assertObjectHasAttribute('enddate', $sirhsessions[1]);
        self::assertEquals('RENOIRH_AES_END_2', $sirhsessions[1]->enddate);
        self::assertObjectHasAttribute('instanceexists', $sirhsessions[1]);

        // Enrol not exist.
        self::assertFalse($sirhsessions[1]->instanceexists);

        self::resetAllData();
    }

    /**
     * Test get sirh session function OK
     * With multiple SIRH filter action direction
     *
     * @covers  \enrol_sirh\sirh_api::get_sirh_sessions
     * @covers  \enrol_sirh\sirh_api::check_enrol_sirh_capability
     */
    public function test_get_sirh_session_ok_multiple_sirh_filter_action_direction() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $courseid = $session->get_course()->id;

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
        $instance->customchar1 = 'RENOIRH_AES';
        $instance->customchar2 = 'RENOIRH_AES_TRAINING';
        $instance->customchar3 = 'RENOIRH_AES_SESSION';
        $fields = (array) $instance;
        $plugin->add_instance(get_course($courseid), $fields);

        // Set filter.
        $filter = [];
        $filter['listeIdentifiantSirhOrigine'] = "RENOIRH_AES,RENOIRH_AES_2";
        $filter['nombreElementPage'] = 50;
        $filter['numeroPage'] = 1;
        $filter['filterbyactions'] = 1;
        $filter['filterbyactionsdir'] = 'ASC';

        // Call get_sirh_sessions function.
        $sirhsessions = multiple_sirh_api_test_mocked_enrol_sirh::get_sirh_sessions($sessionid, $filter);

        self::assertIsArray($sirhsessions);
        self::assertCount(2, $sirhsessions);

        self::assertTrue($sirhsessions[0]->instanceexists);
        self::assertFalse($sirhsessions[1]->instanceexists);

        // Set action filter.
        $filter['filterbyactionsdir'] = 'DESC';

        // Call get_sirh_sessions function.
        $sirhsessions = multiple_sirh_api_test_mocked_enrol_sirh::get_sirh_sessions($sessionid, $filter);

        self::assertFalse($sirhsessions[0]->instanceexists);
        self::assertTrue($sirhsessions[1]->instanceexists);

        self::resetAllData();
    }

    /**
     * Test get sirh session function not ok
     * Permission denied
     *
     * @covers  \enrol_sirh\sirh_api::get_sirh_sessions
     * @covers  \enrol_sirh\sirh_api::check_enrol_sirh_capability
     */
    public function test_get_sirh_session_nok_permission_denied() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Set filter.
        $filter = [];
        $filter['listeIdentifiantSirhOrigine'] = ["RENOIRH_AES"];
        $filter['nombreElementPage'] = 50;
        $filter['numeroPage'] = 1;

        // Does not have capacity.
        self::setGuestUser();

        try {
            // Call get_sirh_sessions function.
            \enrol_sirh\sirh_api::get_sirh_sessions($sessionid, $filter);
        } catch (\Exception $e) {
            self::isInstanceOf('Exception');
            self::assertEquals('Permission denied', $e->getMessage());
        }

        self::resetAllData();
    }

    /**
     * Test get sirh session function not ok
     * Missing filter : listeIdentifiantSirhOrigine
     *
     * @covers  \enrol_sirh\sirh_api::get_sirh_sessions
     * @covers  \enrol_sirh\sirh_api::check_enrol_sirh_capability
     */
    public function test_get_sirh_session_nok_missing_filter() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Set empty filter.
        $filter = [];

        try {
            // Call get_sirh_sessions function.
            \enrol_sirh\sirh_api::get_sirh_sessions($sessionid, $filter);
        } catch (\Exception $e) {
            self::isInstanceOf('Exception');
            self::assertEquals('Missing filter : listeIdentifiantSirhOrigine', $e->getMessage());
        }

        self::resetAllData();
    }

    /**
     * Test get sirh session function not ok
     * Permission denied missing sirh
     *
     * @covers  \enrol_sirh\sirh_api::get_sirh_sessions
     * @covers  \enrol_sirh\sirh_api::check_enrol_sirh_capability
     */
    public function test_get_sirh_session_nok_permission_denied_missing_sirh() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Set filter with a bad SIRH.
        $filter = [];
        $filter['listeIdentifiantSirhOrigine'] = "RENOIRH_FALSE";
        $filter['nombreElementPage'] = 50;
        $filter['numeroPage'] = 1;

        try {
            // Call get_sirh_sessions function.
            \enrol_sirh\sirh_api::get_sirh_sessions($sessionid, $filter);
        } catch (\Exception $e) {
            self::isInstanceOf('Exception');
            self::assertEquals('Permission denied', $e->getMessage());
        }

        self::resetAllData();
    }

    /**
     * Test get session users function OK
     *
     * @covers  \enrol_sirh\sirh_api::get_session_users
     * @covers  \enrol_sirh\sirh_api::check_enrol_sirh_capability
     */
    public function test_get_session_users_ok() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Call get_session_users function.
        $sirhsessionusers = sirh_api_test_mocked_enrol_sirh::get_session_users($sessionid, "RENOIRH_AES", "SIRHTRAINING1",
            "SIRHSESSION1", 10);

        self::assertCount(1, $sirhsessionusers['users']);

        self::assertEquals($sirhsessionusers['users'][0]->email, "user1@sirh.fr");
        self::assertEquals($sirhsessionusers['users'][0]->username, "user1@sirh.fr");
        self::assertEquals($sirhsessionusers['users'][0]->lastname, "user1lastname");
        self::assertEquals($sirhsessionusers['users'][0]->firstname, "user1firstname");
        self::assertEquals($sirhsessionusers['users'][0]->password, "to be generated");
        self::assertEquals($sirhsessionusers['users'][0]->confirmed, 1);
        self::assertEquals($sirhsessionusers['users'][0]->auth, "manual");
        self::assertEquals($sirhsessionusers['users'][0]->mnethostid, 1);

        self::resetAllData();
    }

    /**
     * Test get session users function NOT OK
     * Permission denied not capability
     *
     * @covers  \enrol_sirh\sirh_api::get_session_users
     * @covers  \enrol_sirh\sirh_api::check_enrol_sirh_capability
     */
    public function test_get_session_users_nok_permission_denied_not_capability() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Does not have capacity.
        self::setGuestUser();

        try {
            // Call get_session_users function.
            \enrol_sirh\sirh_api::get_session_users($sessionid, "SIRHFALSE", "SIRHTRAINING1", "SIRHSESSION1", 10);
            self::fail();
        } catch (\Exception $e) {
            self::assertInstanceOf('Exception', $e);
            self::assertEquals($e->getMessage(), 'Permission denied');
        }

        self::resetAllData();
    }

    /**
     * Test get session users function NOT OK
     * SIRH ID in filter does not link with session main entity
     *
     * @covers  \enrol_sirh\sirh_api::get_session_users
     * @covers  \enrol_sirh\sirh_api::check_enrol_sirh_capability
     */
    public function test_get_session_users_nok_not_link_sirh() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        try {
            // Call get_session_users function with a bad SIRH.
            \enrol_sirh\sirh_api::get_session_users($sessionid, "SIRHFALSE", "SIRHTRAINING1", "SIRHSESSION1", 10);
            self::fail();
        } catch (\Exception $e) {
            self::assertInstanceOf('Exception', $e);
            self::assertEquals($e->getMessage(), 'Not access');
        }

        self::resetAllData();
    }

    /**
     * Test enrol users sirh function OK
     *
     * @covers  \enrol_sirh\sirh_api::enrol_users_sirh
     * @covers  \enrol_sirh\sirh_api::check_enrol_sirh_capability
     */
    public function test_enrol_users_sirh_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $session->sessionstartdate = time();
        $session->update($session);

        // Set call function attribute.
        $sirh = "RENOIRH_AES";
        $sirhtraining = "SIRHTRAINING1";
        $sirhsession = "SIRHSESSION1";

        // Call enrol_users_sirh function.
        self::assertTrue(sirh_api_test_mocked_enrol_sirh::enrol_users_sirh($sessionid, $sirh, $sirhtraining, $sirhsession));

        // Check if enrol has been created.
        $enrolinstance = $DB->get_record_sql('
            SELECT e.*
            FROM {enrol} e
            WHERE e.customchar1 = :sirh
                AND e.customchar2 = :sirhtraining
                AND e.customchar3 = :sirhsession
        ', [
            'sirh' => $sirh,
            'sirhtraining' => $sirhtraining,
            'sirhsession' => $sirhsession,
        ]);

        self::assertEquals($enrolinstance->courseid, $session->get_course()->id);
        self::assertEquals($enrolinstance->customchar1, $sirh);
        self::assertEquals($enrolinstance->customchar2, $sirhtraining);
        self::assertEquals($enrolinstance->customchar3, $sirhsession);

        // Check if user is enrolled.
        $enrolusers = $DB->get_records_sql('
            SELECT u.*
            FROM {user} u
            JOIN {user_enrolments} ue ON ue.userid = u.id
            WHERE ue.enrolid = :enrolid
        ', ['enrolid' => $enrolinstance->id]);

        self::assertCount(1, $enrolusers);

        $enrolusers = array_values($enrolusers);

        self::assertEquals($enrolusers[0]->email, "user1@sirh.fr");
        self::assertEquals($enrolusers[0]->username, "user1@sirh_fr");
        self::assertEquals($enrolusers[0]->confirmed, 1);
        self::assertEquals($enrolusers[0]->auth, "manual");
        self::assertEquals($enrolusers[0]->mnethostid, 1);

        self::resetAllData();
    }

    /**
     * Test enrol users sirh function OK
     * With multiple user
     *
     * @covers  \enrol_sirh\sirh_api::enrol_users_sirh
     * @covers  \enrol_sirh\sirh_api::check_enrol_sirh_capability
     */
    public function test_enrol_users_sirh_ok_multiple_user() {
        global $DB;

        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $session->sessionstartdate = time();
        $session->update($session);

        // Set call function attribute.
        $sirh = "RENOIRH_AES";
        $sirhtraining = "SIRHTRAINING1";
        $sirhsession = "SIRHSESSION1";

        // Call enrol_users_sirh function.
        self::assertTrue(multiple_sirh_api_test_mocked_enrol_sirh::enrol_users_sirh($sessionid, $sirh, $sirhtraining,
            $sirhsession));

        // Check if enrol has been created.
        $enrolinstance = $DB->get_record_sql('
            SELECT e.*
            FROM {enrol} e
            WHERE e.customchar1 = :sirh
                AND e.customchar2 = :sirhtraining
                AND e.customchar3 = :sirhsession
        ', [
            'sirh' => $sirh,
            'sirhtraining' => $sirhtraining,
            'sirhsession' => $sirhsession,
        ]);

        self::assertEquals($enrolinstance->courseid, $session->get_course()->id);
        self::assertEquals($enrolinstance->customchar1, $sirh);
        self::assertEquals($enrolinstance->customchar2, $sirhtraining);
        self::assertEquals($enrolinstance->customchar3, $sirhsession);

        // Check if users are enrolled.
        $enrolusers = $DB->get_records_sql('
            SELECT u.*
            FROM {user} u
            JOIN {user_enrolments} ue ON ue.userid = u.id
            WHERE ue.enrolid = :enrolid
        ', ['enrolid' => $enrolinstance->id]);

        self::assertCount(2, $enrolusers);

        $enrolusers = array_values($enrolusers);

        self::assertEquals($enrolusers[0]->email, "user1@sirh.fr");
        self::assertEquals($enrolusers[0]->username, "user1@sirh_fr");
        self::assertEquals($enrolusers[0]->confirmed, 1);
        self::assertEquals($enrolusers[0]->auth, "manual");
        self::assertEquals($enrolusers[0]->mnethostid, 1);

        self::assertEquals($enrolusers[1]->email, "user2@sirh.fr");
        self::assertEquals($enrolusers[1]->username, "user2@sirh_fr");
        self::assertEquals($enrolusers[1]->confirmed, 1);
        self::assertEquals($enrolusers[1]->auth, "manual");
        self::assertEquals($enrolusers[1]->mnethostid, 1);

        // Remove user and re-add user to exist instance.
        $user = $DB->get_record('user', ['email' => "user1@sirh.fr"]);
        $DB->delete_records('user_enrolments', [
            'enrolid' => $enrolinstance->id,
            'userid' => $user->id,
        ]);

        $enrolusers = $DB->get_records_sql('
            SELECT u.*
            FROM {user} u
            JOIN {user_enrolments} ue ON ue.userid = u.id
            WHERE ue.enrolid = :enrolid
        ', ['enrolid' => $enrolinstance->id]);

        self::assertCount(1, $enrolusers);

        self::assertTrue(multiple_sirh_api_test_mocked_enrol_sirh::enrol_users_sirh($sessionid, $sirh, $sirhtraining,
            $sirhsession));

        $enrolusers = $DB->get_records_sql('
            SELECT u.*
            FROM {user} u
            JOIN {user_enrolments} ue ON ue.userid = u.id
            WHERE ue.enrolid = :enrolid
        ', ['enrolid' => $enrolinstance->id]);

        self::assertCount(2, $enrolusers);

        self::resetAllData();
    }

    /**
     * Test enrol users sirh function NOT OK
     * Not capability
     *
     * @covers  \enrol_sirh\sirh_api::enrol_users_sirh
     * @covers  \enrol_sirh\sirh_api::check_enrol_sirh_capability
     */
    public function test_enrol_users_sirh_nok_not_capability() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Set call function attribute.
        $sirh = "RENOIRH_AES";
        $sirhtraining = "SIRHTRAINING1";
        $sirhsession = "SIRHSESSION1";

        // Does not have capacity.
        self::setGuestUser();

        try {
            // Call enrol_users_sirh function.
            self::assertTrue(sirh_api_test_mocked_enrol_sirh::enrol_users_sirh($sessionid, $sirh, $sirhtraining, $sirhsession));
            self::fail();
        } catch (\Exception $e) {
            self::isInstanceOf('Exception', $e);
            self::assertEquals('Permission denied', $e->getMessage());
        }

        self::resetAllData();
    }

    /**
     * Test get enrol sirh instance_id function OK
     *
     * @covers  \enrol_sirh\sirh_api::enrol_users_sirh
     * @covers  \enrol_sirh\sirh_api::get_enrol_sirh_instance
     */
    public function test_get_enrol_sirh_instance_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $session->sessionstartdate = time();
        $session->update($session);

        // Set call function attribute.
        $sirh = "RENOIRH_AES";
        $sirhtraining = "SIRHTRAINING1";
        $sirhsession = "SIRHSESSION1";

        \enrol_sirh\sirh_api::enrol_users_sirh($sessionid, $sirh, $sirhtraining, $sirhsession);

        // Call get_enrol_sirh_instance function.
        $enrolinstance = \enrol_sirh\sirh_api::get_enrol_sirh_instance($session->get_course()->id, $sirh, $sirhtraining,
            $sirhsession);

        $dbenrolinstance = $DB->get_record('enrol', ["id" => $enrolinstance->id]);

        self::assertEquals($dbenrolinstance->id, $enrolinstance->id);
        self::assertEquals($enrolinstance->courseid, $session->get_course()->id);
        self::assertEquals($enrolinstance->customchar1, $sirh);
        self::assertEquals($enrolinstance->customchar2, $sirhtraining);
        self::assertEquals($enrolinstance->customchar3, $sirhsession);

        self::resetAllData();
    }

    /**
     * Test create enrol sirh instance function OK
     *
     * @covers  \enrol_sirh\sirh_api::create_enrol_sirh_instance
     */
    public function test_create_enrol_sirh_instance_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);

        // Set call function attribute.
        $sirh = "RENOIRH_AES";
        $sirhtraining = "SIRHTRAINING1";
        $sirhsession = "SIRHSESSION1";

        // Call create_enrol_sirh_instance function.
        $enrolinstanceid = \enrol_sirh\sirh_api::create_enrol_sirh_instance($session->get_course()->id, $sirh, $sirhtraining,
            $sirhsession);

        $enrolinstance = $DB->get_record('enrol', ['id' => $enrolinstanceid]);

        self::assertEquals($enrolinstance->id, $enrolinstanceid);
        self::assertEquals($enrolinstance->courseid, $session->get_course()->id);
        self::assertEquals($enrolinstance->customchar1, $sirh);
        self::assertEquals($enrolinstance->customchar2, $sirhtraining);
        self::assertEquals($enrolinstance->customchar3, $sirhsession);
        self::assertEquals($enrolinstance->status, 0);
        self::assertEquals($enrolinstance->expirythreshold, 0);
        self::assertEquals($enrolinstance->enrolstartdate, 0);
        self::assertEquals($enrolinstance->enrolenddate, 0);

        self::resetAllData();
    }

    /**
     * Test create_and_enrol_user function OK
     * with array
     *
     * @covers  \enrol_sirh\sirh_api::create_and_enrol_user
     */
    public function test_create_and_enrol_user_with_array_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $session->sessionstartdate = time();
        $session->update($session);

        // Set call function attribute.
        $sirh = "RENOIRH_AES";
        $sirhtraining = "SIRHTRAINING1";
        $sirhsession = "SIRHSESSION1";

        // Call create_enrol_sirh_instance function.
        $enrolinstanceid = \enrol_sirh\sirh_api::create_enrol_sirh_instance($session->get_course()->id, $sirh, $sirhtraining,
            $sirhsession);

        $user = [
            'email' => 'user1@sirh.fr',
            'firstname' => 'user1firstname',
            'lastname' => 'user1lastname',
            'password' => 'to be generated',
            'mnethostid' => 1,
            'confirmed' => 1,
            'auth' => 'manual',
        ];

        // Call create_and_enrol_user function.
        \enrol_sirh\sirh_api::create_and_enrol_user($session->get_course()->id, $enrolinstanceid, $user);

        // Check if user are created and enrolled.
        $user = \core_user::get_user_by_email('user1@sirh.fr');
        self::assertTrue($DB->record_exists('user_enrolments', ['enrolid' => $enrolinstanceid, 'userid' => $user->id]));

        self::resetAllData();
    }

    /**
     * Test create_and_enrol_user function OK
     * with object
     *
     * @covers  \enrol_sirh\sirh_api::create_and_enrol_user
     */
    public function test_create_and_enrol_user_with_object_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $session->sessionstartdate = time();
        $session->update($session);

        // Set call function attribute.
        $sirh = "RENOIRH_AES";
        $sirhtraining = "SIRHTRAINING1";
        $sirhsession = "SIRHSESSION1";

        // Call create_enrol_sirh_instance funciton.
        $enrolinstanceid = \enrol_sirh\sirh_api::create_enrol_sirh_instance($session->get_course()->id, $sirh, $sirhtraining,
            $sirhsession);

        // Set new user.
        $user = new \stdClass();
        $user->email = 'user1@sirh.fr';
        $user->username = 'user1@sirh.fr';
        $user->firstname = 'user1firstname';
        $user->lastname = 'user1lastname';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';

        // Call create_and_enrol_user function.
        \enrol_sirh\sirh_api::create_and_enrol_user($session->get_course()->id, $enrolinstanceid, $user);

        // Check if user are created and enrolled.
        $user = \core_user::get_user_by_email('user1@sirh.fr');
        self::assertTrue($DB->record_exists('user_enrolments', ['enrolid' => $enrolinstanceid, 'userid' => $user->id]));

        self::resetAllData();
    }

    /**
     * Test create_and_enrol_user function OK
     * with user id
     *
     * @covers  \enrol_sirh\sirh_api::create_and_enrol_user
     */
    public function test_create_and_enrol_user_with_userid_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $session->sessionstartdate = time();
        $session->update($session);

        // Set call function attribute.
        $sirh = "RENOIRH_AES";
        $sirhtraining = "SIRHTRAINING1";
        $sirhsession = "SIRHSESSION1";

        // Call create_enrol_sirh_instance function.
        $enrolinstanceid = \enrol_sirh\sirh_api::create_enrol_sirh_instance($session->get_course()->id, $sirh, $sirhtraining,
            $sirhsession);

        // Set new user.
        $user = new \stdClass();
        $user->email = 'user1@sirh.fr';
        $user->username = 'user1@sirh.fr';
        $user->firstname = 'user1firstname';
        $user->lastname = 'user1lastname';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';

        // Create new user.
        $userid = \local_mentor_core\profile_api::create_user($user);

        // Check if user is not enrolled.
        self::assertFalse($DB->record_exists('user_enrolments', ['enrolid' => $enrolinstanceid, 'userid' => $userid]));

        // Call create_and_enrol_user function.
        \enrol_sirh\sirh_api::create_and_enrol_user($session->get_course()->id, $enrolinstanceid, $userid);

        // Check if user is enrolled.
        self::assertTrue($DB->record_exists('user_enrolments', ['enrolid' => $enrolinstanceid, 'userid' => $userid]));

        self::resetAllData();
    }

    /**
     * Test create_and_enrol_user function OK
     * Missing key
     *
     * @covers  \enrol_sirh\sirh_api::create_and_enrol_user
     */
    public function test_create_and_enrol_user_missing_key_nok() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);

        // Set call function attribute.
        $sirh = "RENOIRH_AES";
        $sirhtraining = "SIRHTRAINING1";
        $sirhsession = "SIRHSESSION1";

        // Call create_enrol_sirh_instance function.
        $enrolinstanceid = \enrol_sirh\sirh_api::create_enrol_sirh_instance($session->get_course()->id, $sirh, $sirhtraining,
            $sirhsession);

        // Create empty user data.
        $user = [];

        try {
            // Call create_and_enrol_user function.
            \enrol_sirh\sirh_api::create_and_enrol_user($session->get_course()->id, $enrolinstanceid, $user);
            self::fail();
        } catch (\Exception $e) {
            self::assertInstanceOf('Exception', $e);
            self::assertEquals($e->getMessage(), 'Missing key email');
        }

        // Set user data with missing data.
        $user['email'] = 'user1@sirh.fr';

        try {
            // Call create_and_enrol_user function.
            \enrol_sirh\sirh_api::create_and_enrol_user($session->get_course()->id, $enrolinstanceid, $user);
            self::fail();
        } catch (\Exception $e) {
            self::assertInstanceOf('Exception', $e);
            self::assertEquals($e->getMessage(), 'Missing key firstname');
        }

        // Set user data with missing data.
        $user['firstname'] = 'user1firstname';

        try {
            // Call create_and_enrol_user function.
            \enrol_sirh\sirh_api::create_and_enrol_user($session->get_course()->id, $enrolinstanceid, $user);
            self::fail();
        } catch (\Exception $e) {
            self::assertInstanceOf('Exception', $e);
            self::assertEquals($e->getMessage(), 'Missing key lastname');
        }

        self::resetAllData();
    }

    /**
     * Test check enrol sirh capability function OK
     *
     * @covers  \enrol_sirh\sirh_api::check_enrol_sirh_capability
     */
    public function test_check_enrol_sirh_capability_ok() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);

        try {
            // Call check_enrol_sirh_capability function.
            \enrol_sirh\sirh_api::check_enrol_sirh_capability($session->get_context()->id);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Does not have capacity.
        self::setGuestUser();

        try {
            // Call check_enrol_sirh_capability function.
            \enrol_sirh\sirh_api::check_enrol_sirh_capability($session->get_context()->id);
            self::fail();
        } catch (\Exception $e) {
            self::assertInstanceOf('Exception', $e);
            self::assertEquals($e->getMessage(), 'Permission denied');
        }

        self::resetAllData();
    }

    /**
     * Test count sirh sessions function not ok
     * Permission denied
     *
     * @covers  \enrol_sirh\sirh_api::count_sirh_sessions
     * @covers  \enrol_sirh\sirh_api::check_enrol_sirh_capability
     */
    public function test_count_sirh_sessions_nok_permission_denied() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Set filter.
        $filter = [];
        $filter['listeIdentifiantSirhOrigine'] = ["RENOIRH_AES"];

        // Does not have capacity.
        self::setGuestUser();

        try {
            // Call count_sirh_sessions function.
            \enrol_sirh\sirh_api::count_sirh_sessions($sessionid, $filter);
        } catch (\Exception $e) {
            self::isInstanceOf('Exception');
            self::assertEquals('Permission denied', $e->getMessage());
        }

        self::resetAllData();
    }

    /**
     * Test count sirh session function not ok
     * Missing filter : listeIdentifiantSirhOrigine
     *
     * @covers  \enrol_sirh\sirh_api::count_sirh_sessions
     * @covers  \enrol_sirh\sirh_api::check_enrol_sirh_capability
     */
    public function test_count_sirh_session_nok_missing_filter() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Set empty filter.
        $filter = [];

        try {
            // Call count_sirh_sessions function.
            \enrol_sirh\sirh_api::count_sirh_sessions($sessionid, $filter);
        } catch (\Exception $e) {
            self::isInstanceOf('Exception');
            self::assertEquals('Missing filter : listeIdentifiantSirhOrigine', $e->getMessage());
        }

        self::resetAllData();
    }

    /**
     * Test count sirh sessions function not ok
     * Permission denied missing sirh
     *
     * @covers  \enrol_sirh\sirh_api::count_sirh_sessions
     * @covers  \enrol_sirh\sirh_api::check_enrol_sirh_capability
     */
    public function test_count_sirh_sessions_nok_permission_denied_missing_sirh() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Set filter with a bad SIRH.
        $filter = [];
        $filter['listeIdentifiantSirhOrigine'] = "RENOIRH_FALSE";

        try {
            // Call count_sirh_sessions function.
            \enrol_sirh\sirh_api::count_sirh_sessions($sessionid, $filter);
        } catch (\Exception $e) {
            self::isInstanceOf('Exception');
            self::assertEquals('Permission denied', $e->getMessage());
        }

        self::resetAllData();
    }

    /**
     * Test count sirh sessions function OK
     *
     * @covers  \enrol_sirh\sirh_api::count_sirh_sessions
     * @covers  \enrol_sirh\sirh_api::check_enrol_sirh_capability
     */
    public function test_count_sirh_sessions_ok() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Set filter.
        $filter = [];
        $filter['listeIdentifiantSirhOrigine'] = "RENOIRH_AES";

        // Call count_sirh_sessions function.
        $countsirhsessions = sirh_api_test_mocked_enrol_sirh::count_sirh_sessions($sessionid, $filter);

        self::assertEquals(1, $countsirhsessions);

        self::resetAllData();
    }

    /**
     * Test get sirh rest api OK
     *
     * @covers  \enrol_sirh\sirh_api::get_sirh_rest_api
     */
    public function test_get_sirh_rest_api_ok() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        $sirhrestapi = \enrol_sirh\sirh_api::get_sirh_rest_api();

        self::assertInstanceOf('enrol_sirh\sirh', $sirhrestapi);

        self::resetAllData();
    }

    /**
     * Test get instance users function OK
     *
     * @covers  \enrol_sirh\sirh_api::get_instance_users
     */
    public function test_get_instance_users_ok() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $sirh = 'SIRH';
        $sirhtraining = 'SIRH training';
        $sirhsession = 'SIRH session';

        $instanceid = \enrol_sirh\sirh_api::create_enrol_sirh_instance(
            $course->id,
            $sirh,
            $sirhtraining,
            $sirhsession
        );

        $usersinstance = \enrol_sirh\sirh_api::get_instance_users($instanceid);

        self::assertEmpty($usersinstance);

        $user = $this->getDataGenerator()->create_user();
        \enrol_sirh_external::enrol_user($course->id, $instanceid, $user->id);

        $usersinstance = \enrol_sirh\sirh_api::get_instance_users($instanceid);

        self::assertCount(1, $usersinstance);
        self::assertArrayHasKey($user->id, $usersinstance);

        self::resetAllData();
    }

    /**
     * Test get instance function OK
     *
     * @covers  \enrol_sirh\sirh_api::get_instance
     */
    public function test_get_instance_ok() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $course = $session->get_course();

        $sirh = 'SIRH';
        $sirhtraining = 'SIRH training';
        $sirhsession = 'SIRH session';

        $instanceid = \enrol_sirh\sirh_api::create_enrol_sirh_instance(
            $course->id,
            $sirh,
            $sirhtraining,
            $sirhsession
        );

        $instance = \enrol_sirh\sirh_api::get_instance($instanceid);

        self::assertIsObject($instance);
        self::assertEquals($instance->id, $instanceid);
        self::assertEquals($instance->customchar1, $sirh);
        self::assertEquals($instance->customchar2, $sirhtraining);
        self::assertEquals($instance->customchar3, $sirhsession);

        self::resetAllData();
    }

    /**
     * Test get instance function NOT OK
     *
     * @covers  \enrol_sirh\sirh_api::get_instance
     */
    public function test_get_instance_nok() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        self::assertFalse(\enrol_sirh\sirh_api::get_instance('50'));

        self::resetAllData();
    }

    /**
     * Test get group sirh function OK
     *
     * @covers  \enrol_sirh\sirh_api::get_group_sirh
     */
    public function test_get_group_sirh_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $sirh = 'SIRH';
        $sirhtraining = 'SIRH training';
        $sirhsession = 'SIRH session';

        $instanceid = \enrol_sirh\sirh_api::create_enrol_sirh_instance(
            $course->id,
            $sirh,
            $sirhtraining,
            $sirhsession
        );

        self::assertFalse(\enrol_sirh\sirh_api::get_group_sirh($instanceid));

        $group = $this->getDataGenerator()->create_group(
            ['courseid' => $course->id, 'name' => 'SIRH group']
        );

        $instance = \enrol_sirh\sirh_api::get_instance($instanceid);

        $instance->customint1 = $group->id;
        $DB->update_record('enrol', $instance);

        $instancegroup = \enrol_sirh\sirh_api::get_group_sirh($instanceid);

        self::assertIsObject($instancegroup);
        self::assertEquals($instancegroup->id, $group->id);
        self::assertEquals($instancegroup->name, 'SIRH group');
        self::assertEquals($instancegroup->courseid, $course->id);

        self::resetAllData();
    }

    /**
     * Test create group sirh function OK
     *
     * @covers  \enrol_sirh\sirh_api::create_group_sirh
     */
    public function test_create_group_sirh_ok() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $sirh = 'SIRH';
        $sirhtraining = 'SIRH training';
        $sirhsession = 'SIRH session';

        $instanceid = \enrol_sirh\sirh_api::create_enrol_sirh_instance(
            $course->id,
            $sirh,
            $sirhtraining,
            $sirhsession
        );

        $instance = \enrol_sirh\sirh_api::get_instance($instanceid);

        $sirhgroupid = \enrol_sirh\sirh_api::create_group_sirh($instance);
        $sirhgroup = groups_get_group($sirhgroupid);

        self::assertIsObject($sirhgroup);
        self::assertEquals(
            $sirhgroup->name,
            'Liaison SIRH - ' . $instance->customchar1 .
            ' - ' . $instance->customchar2 .
            ' - ' . $instance->customchar3
        );
        self::assertEquals($sirhgroup->courseid, $course->id);

        self::resetAllData();
    }

    /**
     * Test set group sirh function OK
     *
     * @covers  \enrol_sirh\sirh_api::set_group_sirh
     */
    public function test_set_group_sirh_ok() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $sirh = 'SIRH';
        $sirhtraining = 'SIRH training';
        $sirhsession = 'SIRH session';

        $instanceid = \enrol_sirh\sirh_api::create_enrol_sirh_instance(
            $course->id,
            $sirh,
            $sirhtraining,
            $sirhsession
        );

        $group = $this->getDataGenerator()->create_group(
            ['courseid' => $course->id]
        );

        $instance = \enrol_sirh\sirh_api::get_instance($instanceid);

        self::assertEmpty($instance->customint1);

        self::assertTrue(\enrol_sirh\sirh_api::set_group_sirh($instanceid, $group->id));

        $instance = \enrol_sirh\sirh_api::get_instance($instanceid);

        self::assertEquals($instance->customint1, $group->id);

        self::resetAllData();
    }

    /**
     * Test default sirh group exist function OK
     *
     * @covers  \enrol_sirh\sirh_api::default_sirh_group_exist
     */
    public function test_default_sirh_group_exist_ok() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $sirh = 'SIRH';
        $sirhtraining = 'SIRH training';
        $sirhsession = 'SIRH session';

        $instanceid = \enrol_sirh\sirh_api::create_enrol_sirh_instance(
            $course->id,
            $sirh,
            $sirhtraining,
            $sirhsession
        );

        self::assertFalse(\enrol_sirh\sirh_api::default_sirh_group_exist($instanceid));

        $instance = \enrol_sirh\sirh_api::get_instance($instanceid);

        $group = $this->getDataGenerator()->create_group(
            [
                'courseid' => $course->id, 'name' => 'Liaison SIRH - ' . $instance->customchar1 .
                                                     ' - ' . $instance->customchar2 .
                                                     ' - ' . $instance->customchar3,
            ]
        );

        $sirhgroup = \enrol_sirh\sirh_api::default_sirh_group_exist($instanceid);

        self::assertIsObject($sirhgroup);
        self::assertEquals($sirhgroup->id, $group->id);
        self::assertEquals(
            $sirhgroup->name,
            'Liaison SIRH - ' . $instance->customchar1 .
            ' - ' . $instance->customchar2 .
            ' - ' . $instance->customchar3
        );
        self::assertEquals($sirhgroup->courseid, $course->id);

        self::resetAllData();
    }

    /**
     * Test synchronize users function OK
     *
     * @covers  \enrol_sirh\sirh_api::synchronize_users
     */
    public function test_synchronize_users_ok() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $group1 = $this->getDataGenerator()->create_group(
            ['courseid' => $course->id, 'name' => 'SIRH group 1']
        );
        $group2 = $this->getDataGenerator()->create_group(
            ['courseid' => $course->id, 'name' => 'SIRH group 2']
        );

        $sirh = 'SIRH';
        $sirhtraining = 'SIRH training';
        $sirhsession = 'SIRH session';

        $instanceid = \enrol_sirh\sirh_api::create_enrol_sirh_instance(
            $course->id,
            $sirh,
            $sirhtraining,
            $sirhsession
        );

        \enrol_sirh\sirh_api::set_group_sirh($instanceid, $group1->id);

        $instance = \enrol_sirh\sirh_api::get_instance($instanceid);

        self::assertEmpty(\enrol_sirh\sirh_api::get_instance_users($instanceid));
        self::assertEmpty(groups_get_members($group1->id));
        self::assertEmpty(groups_get_members($group2->id));

        // Set user data.
        $user1 = new \stdClass();
        $user1->firstname = 'firstname1';
        $user1->lastname = 'lastname1';
        $user1->username = 'username1@mail.fr';
        $user1->email = 'username1@mail.fr';
        $user1->mnethostid = 1;
        $user1->confirmed = 1;

        $user2 = new \stdClass();
        $user2->firstname = 'firstname2';
        $user2->lastname = 'lastname2';
        $user2->username = 'username2@mail.fr';
        $user2->email = 'username2@mail.fr';
        $user2->mnethostid = 1;
        $user2->confirmed = 1;

        // First part.
        $userdata = [$user1];

        \enrol_sirh\sirh_api::synchronize_users($instance, $userdata);
        $userinstance = \enrol_sirh\sirh_api::get_instance_users($instanceid);

        self::assertCount(1, $userinstance);

        // New user (user1).
        $user1instance1 = current($userinstance);
        self::assertEquals($user1instance1->firstname, 'firstname1');
        self::assertEquals($user1instance1->lastname, 'lastname1');
        self::assertEquals($user1instance1->username, 'username1@mail_fr');
        self::assertEquals($user1instance1->email, 'username1@mail.fr');

        $usergroup1 = groups_get_members($group1->id);
        self::assertCount(1, $usergroup1);
        self::assertArrayHasKey($user1instance1->id, $usergroup1);
        self::assertEquals($usergroup1[$user1instance1->id]->firstname, 'firstname1');
        self::assertEquals($usergroup1[$user1instance1->id]->lastname, 'lastname1');
        self::assertEquals($usergroup1[$user1instance1->id]->username, 'username1@mail_fr');
        self::assertEquals($usergroup1[$user1instance1->id]->email, 'username1@mail.fr');

        self::assertEmpty(groups_get_members($group2->id));

        // Second part.
        $userdata = [$user1, $user2];

        \enrol_sirh\sirh_api::synchronize_users($instance, $userdata);
        $userinstance2 = \enrol_sirh\sirh_api::get_instance_users($instanceid);

        self::assertCount(2, $userinstance2);

        // Is same user (user1).
        self::assertArrayHasKey($user1instance1->id, $userinstance2);
        self::assertEquals($userinstance2[$user1instance1->id]->firstname, 'firstname1');
        self::assertEquals($userinstance2[$user1instance1->id]->lastname, 'lastname1');
        self::assertEquals($userinstance2[$user1instance1->id]->username, 'username1@mail_fr');
        self::assertEquals($userinstance2[$user1instance1->id]->email, 'username1@mail.fr');

        // New user (user2).
        $user2instance2 = end($userinstance2);
        self::assertEquals($user2instance2->firstname, 'firstname2');
        self::assertEquals($user2instance2->lastname, 'lastname2');
        self::assertEquals($user2instance2->username, 'username2@mail_fr');
        self::assertEquals($user2instance2->email, 'username2@mail.fr');

        $usergroup1 = groups_get_members($group1->id);
        self::assertCount(2, $usergroup1);

        // Is same user (user1).
        self::assertArrayHasKey($user1instance1->id, $usergroup1);
        self::assertEquals($usergroup1[$user1instance1->id]->firstname, 'firstname1');
        self::assertEquals($usergroup1[$user1instance1->id]->lastname, 'lastname1');
        self::assertEquals($usergroup1[$user1instance1->id]->username, 'username1@mail_fr');
        self::assertEquals($usergroup1[$user1instance1->id]->email, 'username1@mail.fr');

        // New user (user2).
        self::assertArrayHasKey($user2instance2->id, $usergroup1);
        self::assertEquals($usergroup1[$user2instance2->id]->firstname, 'firstname2');
        self::assertEquals($usergroup1[$user2instance2->id]->lastname, 'lastname2');
        self::assertEquals($usergroup1[$user2instance2->id]->username, 'username2@mail_fr');
        self::assertEquals($usergroup1[$user2instance2->id]->email, 'username2@mail.fr');

        self::assertEmpty(groups_get_members($group2->id));

        // Third part.
        $userdata = [$user1, $user2];

        // Change group.
        \enrol_sirh\sirh_api::set_group_sirh($instanceid, $group2->id);

        // Refresh data instance.
        $instance = \enrol_sirh\sirh_api::get_instance($instanceid);

        \enrol_sirh\sirh_api::synchronize_users($instance, $userdata);
        $userinstance3 = \enrol_sirh\sirh_api::get_instance_users($instanceid);

        self::assertCount(2, $userinstance3);

        // Is same user (user1).
        self::assertArrayHasKey($user1instance1->id, $userinstance3);
        self::assertEquals($userinstance2[$user1instance1->id]->firstname, 'firstname1');
        self::assertEquals($userinstance2[$user1instance1->id]->lastname, 'lastname1');
        self::assertEquals($userinstance2[$user1instance1->id]->username, 'username1@mail_fr');
        self::assertEquals($userinstance2[$user1instance1->id]->email, 'username1@mail.fr');

        // Is same user (user2).
        self::assertArrayHasKey($user2instance2->id, $userinstance3);
        self::assertEquals($userinstance3[$user2instance2->id]->firstname, 'firstname2');
        self::assertEquals($userinstance3[$user2instance2->id]->lastname, 'lastname2');
        self::assertEquals($userinstance3[$user2instance2->id]->username, 'username2@mail_fr');
        self::assertEquals($userinstance3[$user2instance2->id]->email, 'username2@mail.fr');

        // Instance group change.
        self::assertEmpty(groups_get_members($group1->id));
        $usergroup2 = groups_get_members($group2->id);
        self::assertCount(2, $usergroup2);

        // Is same user (user1).
        self::assertArrayHasKey($user1instance1->id, $usergroup2);
        self::assertEquals($usergroup2[$user1instance1->id]->firstname, 'firstname1');
        self::assertEquals($usergroup2[$user1instance1->id]->lastname, 'lastname1');
        self::assertEquals($usergroup2[$user1instance1->id]->username, 'username1@mail_fr');
        self::assertEquals($usergroup2[$user1instance1->id]->email, 'username1@mail.fr');

        // Is same user (user2).
        self::assertArrayHasKey($user2instance2->id, $usergroup2);
        self::assertEquals($usergroup2[$user2instance2->id]->firstname, 'firstname2');
        self::assertEquals($usergroup2[$user2instance2->id]->lastname, 'lastname2');
        self::assertEquals($usergroup2[$user2instance2->id]->username, 'username2@mail_fr');
        self::assertEquals($usergroup2[$user2instance2->id]->email, 'username2@mail.fr');

        // Last part.
        $userdata = [$user2];

        \enrol_sirh\sirh_api::synchronize_users($instance, $userdata);
        $userinstance4 = \enrol_sirh\sirh_api::get_instance_users($instanceid);

        self::assertCount(1, $userinstance);

        // User 1 remove.
        self::assertArrayNotHasKey($user1instance1->id, $userinstance4);

        // Is same user (user2).
        self::assertArrayHasKey($user2instance2->id, $userinstance4);
        self::assertEquals($userinstance4[$user2instance2->id]->firstname, 'firstname2');
        self::assertEquals($userinstance4[$user2instance2->id]->lastname, 'lastname2');
        self::assertEquals($userinstance4[$user2instance2->id]->username, 'username2@mail_fr');
        self::assertEquals($userinstance4[$user2instance2->id]->email, 'username2@mail.fr');

        self::assertEmpty(groups_get_members($group1->id));

        $usergroup2 = groups_get_members($group2->id);
        self::assertCount(1, $usergroup2);

        // User 1 remove.
        self::assertArrayNotHasKey($user1instance1->id, $usergroup2);

        // Is same user (user2).
        self::assertArrayHasKey($user2instance2->id, $usergroup2);
        self::assertEquals($usergroup2[$user2instance2->id]->firstname, 'firstname2');
        self::assertEquals($usergroup2[$user2instance2->id]->lastname, 'lastname2');
        self::assertEquals($usergroup2[$user2instance2->id]->username, 'username2@mail_fr');
        self::assertEquals($usergroup2[$user2instance2->id]->email, 'username2@mail.fr');

        self::resetAllData();
    }

    /**
     * Test update sirh instance sync data function OK
     *
     * @covers  \enrol_sirh\sirh_api::update_sirh_instance_sync_data
     */
    public function test_update_sirh_instance_sync_data_ok() {
        global $USER;

        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();

        self::setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $sirh = 'SIRH';
        $sirhtraining = 'SIRH training';
        $sirhsession = 'SIRH session';

        $instanceid = \enrol_sirh\sirh_api::create_enrol_sirh_instance(
            $course->id,
            $sirh,
            $sirhtraining,
            $sirhsession
        );

        $instance = (object) \enrol_sirh_external::get_instance_info($instanceid);

        self::assertNull($instance->customint2);
        self::assertNull($instance->customint3);

        \enrol_sirh\sirh_api::update_sirh_instance_sync_data($instance, false);

        $instance = (object) \enrol_sirh_external::get_instance_info($instanceid);

        self::assertNull($instance->customint2);
        self::assertIsNumeric($instance->customint3);
        \enrol_sirh\sirh_api::update_sirh_instance_sync_data($instance, true);

        $instance = (object) \enrol_sirh_external::get_instance_info($instanceid);

        self::assertEquals($instance->customint2, $USER->id);
        self::assertIsNumeric($instance->customint3);

        self::resetAllData();
    }
}

/**
 * SIRH API Mocked class
 * With simple data
 */
class sirh_api_test_mocked_enrol_sirh extends \enrol_sirh\sirh_api {

    /**
     * Extend get_sirh_rest_api method
     *
     * @return \enrol_sirh\sirh|sirh_test_mocked_enrol_sirh
     */
    public static function get_sirh_rest_api() {
        return sirh_test_mocked_enrol_sirh::get_instance();
    }
}

/**
 * SIRH Mocked class
 * with simple data
 */
class sirh_test_mocked_enrol_sirh extends \enrol_sirh\sirh {

    /**
     * Extend get_instance method
     *
     * @return sirh_test_mocked_enrol_sirh
     */
    public static function get_instance() {

        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }
        return self::$instance;

    }

    /**
     * Extend get_sirh_sessions method
     *
     * @return false[]
     */
    public function get_sirh_sessions($filters) {

        $sirhsdata = [
            [
                'RENOIRH_AES',
                'RENOIRH_AES_TRAINING',
                'RENOIRH_AES_TRAINING_NAME',
                'RENOIRH_AES_SESSION',
                'RENOIRH_AES_SESSION_NAME',
                'RENOIRH_AES_START',
                'RENOIRH_AES_END',
            ],
        ];

        $sirhs = [];

        foreach ($sirhsdata as $sirhdata) {
            $session = new \stdClass();
            $session->sirh = $sirhdata[0];
            $session->sirhtraining = $sirhdata[1];
            $session->sirhtrainingname = $sirhdata[2];
            $session->sirhsession = $sirhdata[3];
            $session->sirhsessionname = $sirhdata[4];
            $session->startdate = $sirhdata[5];
            $session->enddate = $sirhdata[6];
            $sirhs[] = $session;
        }

        return $sirhs;
    }

    /**
     * Extend get_session_users method
     *
     * @param string $sirh
     * @param string $sirhtraining
     * @param string $sirhsession
     * @return array
     */
    public function get_session_users($sirh, $sirhtraining, $sirhsession, $nbusers = null, $lastsync = null) {
        $user = new \stdClass();
        $user->email = "user1@sirh.fr";
        $user->username = "user1@sirh.fr";
        $user->lastname = "user1lastname";
        $user->firstname = "user1firstname";
        $user->password = 'to be generated';
        $user->confirmed = 1;
        $user->auth = "manual";
        $user->mnethostid = 1;

        return ['users' => [$user]];
    }

    /**
     * Count sirh_sessions
     *
     * @param array $filters
     * @return int
     * @throws \Exception
     */
    public function count_sirh_sessions($filters) {
        return 1;
    }
}

/**
 * SIRH API Mocked class
 * With multiple data
 */
class multiple_sirh_api_test_mocked_enrol_sirh extends \enrol_sirh\sirh_api {
    public static function get_sirh_rest_api() {
        return multiple_sirh_test_mocked_enrol_sirh::get_instance();
    }
}

/**
 * SIRH Mocked class
 * with multiple data
 */
class multiple_sirh_test_mocked_enrol_sirh extends \enrol_sirh\sirh {
    /**
     * Extend get_instance method
     *
     * @return sirh_test_mocked_enrol_sirh
     */
    public static function get_instance() {

        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }
        return self::$instance;

    }

    /**
     * Extend get_sirh_sessions method
     *
     * @return false[]
     */
    public function get_sirh_sessions($filters) {

        $sirhsdata = [
            [
                'RENOIRH_AES',
                'RENOIRH_AES_TRAINING',
                'RENOIRH_AES_TRAINING_NAME',
                'RENOIRH_AES_SESSION',
                'RENOIRH_AES_SESSION_NAME',
                'RENOIRH_AES_START',
                'RENOIRH_AES_END',
            ],
            [
                'RENOIRH_AES_2',
                'RENOIRH_AES_TRAINING_2',
                'RENOIRH_AES_TRAINING_NAME_2',
                'RENOIRH_AES_SESSION_2',
                'RENOIRH_AES_SESSION_NAME_2',
                'RENOIRH_AES_START_2',
                'RENOIRH_AES_END_2',
            ],
        ];

        $sirhs = [];

        foreach ($sirhsdata as $sirhdata) {
            $session = new \stdClass();
            $session->sirh = $sirhdata[0];
            $session->sirhtraining = $sirhdata[1];
            $session->sirhtrainingname = $sirhdata[2];
            $session->sirhsession = $sirhdata[3];
            $session->sirhsessionname = $sirhdata[4];
            $session->startdate = $sirhdata[5];
            $session->enddate = $sirhdata[6];
            $sirhs[] = $session;
        }

        return $sirhs;
    }

    /**
     * Extend get_session_users method
     *
     * @param string $sirh
     * @param string $sirhtraining
     * @param string $sirhsession
     * @return array
     */
    public function get_session_users($sirh, $sirhtraining, $sirhsession, $nbusers = null, $lastsync = null) {
        $user = new \stdClass();
        $user->email = "user1@sirh.fr";
        $user->username = "user1@sirh.fr";
        $user->lastname = "user1lastname";
        $user->firstname = "user1firstname";
        $user->password = 'to be generated';
        $user->confirmed = 1;
        $user->auth = "manual";
        $user->mnethostid = 1;

        $user2 = new \stdClass();
        $user2->email = "user2@sirh.fr";
        $user2->username = "user2@sirh.fr";
        $user2->lastname = "user2lastname";
        $user2->firstname = "user2firstname";
        $user2->password = 'to be generated';
        $user2->confirmed = 1;
        $user2->auth = "manual";
        $user2->mnethostid = 1;

        return ['users' => [$user, $user2]];
    }

    /**
     * Count sirh_sessions
     *
     * @param array $filters
     * @return int
     * @throws \Exception
     */
    public function count_sirh_sessions($filters) {
        return 50;
    }
}
