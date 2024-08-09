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
 * Tests for dbinterface class
 *
 * @package    enrol_sirh
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     Remi Colet <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/enrol/sirh/classes/database_interface.php');

class enrol_sirh_dbinterface_testcase extends advanced_testcase {

    /**
     * Reset the singletons
     *
     * @throws ReflectionException
     */
    public function reset_singletons() {
        // Reset the mentor core db interface singleton.
        $dbinterface = \enrol_sirh\database_interface::get_instance();
        $reflection = new ReflectionClass($dbinterface);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true); // Now we can modify that :).
        $instance->setValue(null, null); // Instance is gone.
        $instance->setAccessible(false); // Clean up.
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
     * @return \local_mentor_core\training
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
     * Test get user by email function OK
     *
     * @covers  \enrol_sirh\database_interface::__construct
     * @covers  \enrol_sirh\database_interface::get_instance
     * @covers  \enrol_sirh\database_interface::get_user_by_email
     */
    public function test_get_user_by_email_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        $this->getDataGenerator()->create_user([
            'username' => 'test@gmail.com',
            'email' => 'test@gmail.com',
        ]);

        $dbi = \enrol_sirh\database_interface::get_instance();
        $user = $dbi->get_user_by_email('test@gmail.com');

        self::assertIsObject($user);
        self::assertEquals($user->email, 'test@gmail.com');
        self::assertEquals($user->username, 'test@gmail.com');

        self::resetAllData();
    }

    /**
     * Test get user by email function NOT OK
     *
     * @covers  \enrol_sirh\database_interface::get_instance
     * @covers  \enrol_sirh\database_interface::get_user_by_email
     */
    public function test_get_user_by_email_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        $dbi = \enrol_sirh\database_interface::get_instance();
        $user = $dbi->get_user_by_email('test@gmail.com');

        self::assertFalse($user);

        self::resetAllData();
    }

    /**
     * Test get instance users sirh function OK
     *
     * @covers  \enrol_sirh\database_interface::get_instance
     * @covers  \enrol_sirh\database_interface::get_instance_users_sirh
     */
    public function test_get_instance_users_sirh_ok() {
        $this->resetAfterTest(true);
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

        $dbi = \enrol_sirh\database_interface::get_instance();
        $usersinstance = $dbi->get_instance_users_sirh($instanceid);

        self::assertEmpty($usersinstance);

        $user = $this->getDataGenerator()->create_user();
        \enrol_sirh_external::enrol_user($course->id, $instanceid, $user->id);

        $dbi = \enrol_sirh\database_interface::get_instance();
        $usersinstance = $dbi->get_instance_users_sirh($instanceid);

        self::assertCount(1, $usersinstance);
        self::assertArrayHasKey($user->id, $usersinstance);

        self::resetAllData();
    }

    /**
     * Test get instance sirh function OK
     *
     * @covers  \enrol_sirh\database_interface::get_instance
     * @covers  \enrol_sirh\database_interface::get_instance_sirh
     */
    public function test_get_instance_sirh_ok() {
        $this->resetAfterTest(true);
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

        $dbi = \enrol_sirh\database_interface::get_instance();
        $instance = $dbi->get_instance_sirh(
            $course->id,
            $sirh,
            $sirhtraining,
            $sirhsession
        );

        self::assertIsObject($instance);
        self::assertEquals($instance->id, $instanceid);
        self::assertEquals($instance->customchar1, $sirh);
        self::assertEquals($instance->customchar2, $sirhtraining);
        self::assertEquals($instance->customchar3, $sirhsession);

        self::resetAllData();
    }

    /**
     * Test get instance sirh function NOT OK
     * Not link with session
     *
     * @covers  \enrol_sirh\database_interface::get_instance
     * @covers  \enrol_sirh\database_interface::get_instance_sirh
     */
    public function test_get_instance_sirh_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $sirh = 'SIRH';
        $sirhtraining = 'SIRH training';
        $sirhsession = 'SIRH session';

        \enrol_sirh\sirh_api::create_enrol_sirh_instance(
            $course->id,
            $sirh,
            $sirhtraining,
            $sirhsession
        );

        $dbi = \enrol_sirh\database_interface::get_instance();
        $instance = $dbi->get_instance_sirh(
            $course->id,
            $sirh,
            $sirhtraining,
            $sirhsession
        );

        self::assertFalse($instance);

        self::resetAllData();
    }

    /**
     * Test get instance sirh by id function OK
     *
     * @covers  \enrol_sirh\database_interface::get_instance
     * @covers  \enrol_sirh\database_interface::get_instance_sirh_by_id
     */
    public function test_get_instance_sirh_by_id_ok() {
        $this->resetAfterTest(true);
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

        $dbi = \enrol_sirh\database_interface::get_instance();
        $instance = $dbi->get_instance_sirh_by_id($instanceid);

        self::assertIsObject($instance);
        self::assertEquals($instance->id, $instanceid);
        self::assertEquals($instance->customchar1, $sirh);
        self::assertEquals($instance->customchar2, $sirhtraining);
        self::assertEquals($instance->customchar3, $sirhsession);

        self::resetAllData();
    }

    /**
     * Test get instance sirh by id function NOT NOK
     *
     * @covers  \enrol_sirh\database_interface::get_instance
     * @covers  \enrol_sirh\database_interface::get_instance_sirh_by_id
     */
    public function test_get_instance_sirh_by_id_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $dbi = \enrol_sirh\database_interface::get_instance();
        self::assertFalse($dbi->get_instance_sirh_by_id('50'));

        self::resetAllData();
    }

    /**
     * Test get course group by name function OK
     *
     * @covers  \enrol_sirh\database_interface::get_instance
     * @covers  \enrol_sirh\database_interface::get_course_group_by_name
     */
    public function test_get_course_group_by_name_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        // Group creation.
        $group = new stdClass();
        $group->name = 'testgroup';
        $group->courseid = $course->id;
        $groupid = groups_create_group($group);

        $dbi = \enrol_sirh\database_interface::get_instance();
        $groupdata = $dbi->get_course_group_by_name($course->id, 'testgroup');

        self::assertIsObject($groupdata);
        self::assertEquals($groupdata->id, $groupid);
        self::assertEquals($groupdata->name, 'testgroup');
        self::assertEquals($groupdata->courseid, $course->id);

        self::resetAllData();
    }

    /**
     * Test get course group by name function NOT OK
     *
     * @covers  \enrol_sirh\database_interface::get_instance
     * @covers  \enrol_sirh\database_interface::get_course_group_by_name
     */
    public function test_get_course_group_by_name_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $dbi = \enrol_sirh\database_interface::get_instance();
        self::assertFalse($dbi->get_course_group_by_name($course->id, 'testgroup'));

        self::resetAllData();
    }

    /**
     * Test user enrolment exist function
     *
     * @covers  \enrol_sirh\database_interface::get_instance
     * @covers  \enrol_sirh\database_interface::user_enrolment_exist
     */
    public function test_user_enrolment_exist() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $instanceid = \enrol_sirh\sirh_api::create_enrol_sirh_instance(
            $course->id,
            'SIRH',
            'SIRH training',
            'SIRH session'
        );

        $dbi = \enrol_sirh\database_interface::get_instance();
        self::assertFalse($dbi->user_enrolment_exist($instanceid, $user->id));

        \enrol_sirh_external::enrol_user($course->id, $instanceid, $user->id);

        self::assertTrue($dbi->user_enrolment_exist($instanceid, $user->id));

        self::resetAllData();
    }

    /**
     * Test get all instance sirh function
     *
     * @covers  \enrol_sirh\database_interface::get_instance
     * @covers  \enrol_sirh\database_interface::get_all_instance_sirh
     */
    public function test_get_all_instance_sirh() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $dbi = \enrol_sirh\database_interface::get_instance();

        self::assertEmpty($dbi->get_all_instance_sirh());// Create training.
        $training = $this->init_training_creation();

        $sessionname = 'TESTUNITCREATESESSION';

        // Test standard session creation.
        try {
            $session = \local_mentor_core\session_api::create_session($training->id, $sessionname, true);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        $instanceid = \enrol_sirh\sirh_api::create_enrol_sirh_instance(
            $session->get_course()->id,
            'SIRH',
            'SIRH training',
            'SIRH session'
        );

        $allinstance = $dbi->get_all_instance_sirh();

        self::assertCount(1, $allinstance);
        self::assertArrayHasKey($instanceid, $allinstance);

        $instanceid2 = \enrol_sirh\sirh_api::create_enrol_sirh_instance(
            $session->get_course()->id,
            'SIRH2',
            'SIRH training2',
            'SIRH session2'
        );

        $allinstance = $dbi->get_all_instance_sirh();

        self::assertCount(2, $allinstance);
        self::assertArrayHasKey($instanceid, $allinstance);
        self::assertArrayHasKey($instanceid2, $allinstance);

        self::resetAllData();
    }
}
