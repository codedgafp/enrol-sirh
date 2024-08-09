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

require_once($CFG->dirroot . '/enrol/sirh/classes/task/check_update_sirh.php');

class enrol_sirh_task_testcase extends advanced_testcase {

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
     * Init $CFG
     */
    public function init_config() {
        global $CFG;

        // SIRH API.
        $CFG->sirh_api_url = "www.sirh.fr";
        $CFG->sirh_api_token = "FALSEKEY";
        $CFG->defaultauth = 'manual';
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
     * Test check_update_sirh::get_name ok
     *
     * @covers \enrol_sirh\task\check_update_sirh::__construct
     * @covers \enrol_sirh\task\check_update_sirh::get_name
     */
    public function test_check_update_sirh_task_get_name() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_config();

        self::setAdminUser();

        $task = new \enrol_sirh\task\check_update_sirh();

        self::assertEquals($task->get_name(), get_string('task_check_update_sirh', 'enrol_sirh'));

        self::resetAllData();
    }

    /**
     * Test check_update_sirh::execute ok
     * Empty instance SIRH
     *
     * @covers \enrol_sirh\task\check_update_sirh::execute
     */
    public function test_check_update_sirh_execute_ok_empty_instance() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_config();

        self::setAdminUser();

        $taskmock = $this->getMockBuilder('\enrol_sirh\task\check_update_sirh')
            ->setMethods(['send_email_update_data', 'send_email_update_user'])
            ->enableOriginalConstructor()
            ->getMock();

        // Check if never execute send_email_update_data and send_email_update_user function.
        $taskmock->expects($this->never())
            ->method('send_email_update_data');
        $taskmock->expects($this->never())
            ->method('send_email_update_user');

        $dbimock = $this->getMockBuilder('\enrol_sirh\database_interface')
            ->setMethods(['get_all_instance_sirh'])
            ->disableOriginalConstructor()
            ->getMock();

        // Redefined get_all_instance_sirh return value and check if execute this function.
        $dbimock->expects($this->once())
            ->method('get_all_instance_sirh')
            ->will($this->returnValue([]));

        // Replace database interface by mock.
        $reflection = new ReflectionClass($taskmock);
        $reflectionproperty = $reflection->getProperty('dbi');
        $reflectionproperty->setAccessible(true);
        $reflectionproperty->setValue($taskmock, $dbimock);

        $taskmock->execute();

        self::resetAllData();
    }

    /**
     * Test check_update_sirh::execute ok
     * User session function return false
     *
     * @covers \enrol_sirh\task\check_update_sirh::execute
     */
    public function test_check_update_sirh_execute_nok_user_session_false() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_config();

        self::setAdminUser();

        $taskmock = $this->getMockBuilder('\enrol_sirh\task\check_update_sirh')
            ->setMethods(['send_email_update_data', 'send_email_update_user'])
            ->enableOriginalConstructor()
            ->getMock();

        // Check if never execute send_email_update_data and send_email_update_user function.
        $taskmock->expects($this->never())
            ->method('send_email_update_data');
        $taskmock->expects($this->never())
            ->method('send_email_update_user');

        $dbimock = $this->getMockBuilder('\enrol_sirh\database_interface')
            ->setMethods(['get_all_instance_sirh'])
            ->disableOriginalConstructor()
            ->getMock();

        $instancedata = new \stdClass();
        $instancedata->customchar1 = 'customchar1';
        $instancedata->customchar2 = 'customchar2';
        $instancedata->customchar3 = 'customchar3';
        $instancedata->customint3 = 'customint3';

        // Redefined get_all_instance_sirh return new value and check if execute this function.
        $dbimock->expects($this->once())
            ->method('get_all_instance_sirh')
            ->will($this->returnValue([$instancedata]));

        // Replace database interface by mock.
        $reflection = new ReflectionClass($taskmock);
        $reflectionproperty = $reflection->getProperty('dbi');
        $reflectionproperty->setAccessible(true);
        $reflectionproperty->setValue($taskmock, $dbimock);

        $apimock = $this->getMockBuilder('\enrol_sirh\sirh')
            ->setMethods(['get_session_users'])
            ->disableOriginalConstructor()
            ->getMock();

        // Redefined get_session_users return false and check if execute this function.
        $apimock->expects($this->once())
            ->method('get_session_users')
            ->will($this->returnValue(false));

        $reflectionproperty = $reflection->getProperty('sirhrest');
        $reflectionproperty->setAccessible(true);
        $reflectionproperty->setValue($taskmock, $apimock);

        $taskmock->execute();

        self::resetAllData();
    }

    /**
     * Test check_update_sirh::execute ok
     * Update session SIRH data
     *
     * @covers \enrol_sirh\task\check_update_sirh::execute
     * @covers \enrol_sirh\task\check_update_sirh::send_email_update_data
     * @covers \enrol_sirh\task\check_update_sirh::send_email
     */
    public function test_check_update_sirh_execute_ok_update_session_sirh_data() {
        global $DB, $USER, $CFG;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_config();

        self::setAdminUser();

        // Close the default email sink.
        $sink = $this->redirectEmails();
        $sink->close();
        unset_config('noemailever');
        $sink = $this->redirectEmails();

        $taskmock = $this->getMockBuilder('\enrol_sirh\task\check_update_sirh')
            ->setMethods(['send_email_update_user'])
            ->enableOriginalConstructor()
            ->getMock();

        // Check if never execute send_email_update_user function.
        $taskmock->expects($this->never())
            ->method('send_email_update_user');

        // Create instance SIRH.
        $course = self::getDataGenerator()->create_course();
        $sirh = 'SIRH';
        $sirhtraining = 'SIRH training';
        $sirhsession = 'SIRH session';

        $instanceid = \enrol_sirh\sirh_api::create_enrol_sirh_instance(
            $course->id,
            $sirh,
            $sirhtraining,
            $sirhsession
        );
        $instance1 = (object) \enrol_sirh_external::get_instance_info($instanceid);
        $instance1->customint2 = $USER->id;
        $oldtimeinstance1 = time() - 1000;
        $instance1->customint3 = $oldtimeinstance1;
        $DB->update_record('enrol', $instance1);

        // No enrol user.
        self::assertEmpty(\enrol_sirh\sirh_api::get_instance_users($instanceid));

        $dbimock = $this->getMockBuilder('\enrol_sirh\database_interface')
            ->setMethods(['get_all_instance_sirh'])
            ->disableOriginalConstructor()
            ->getMock();

        // Redefined get_all_instance_sirh return new value and check if execute this function.
        $dbimock->expects($this->once())
            ->method('get_all_instance_sirh')
            ->will($this->returnValue([$instance1]));

        // Replace database interface by mock.
        $reflection = new ReflectionClass($taskmock);
        $reflectionproperty = $reflection->getProperty('dbi');
        $reflectionproperty->setAccessible(true);
        $reflectionproperty->setValue($taskmock, $dbimock);

        $apimock = $this->getMockBuilder('\enrol_sirh\sirh')
            ->setMethods(['get_session_users'])
            ->disableOriginalConstructor()
            ->getMock();

        // Session_users data.
        $sessionusersdata = [];
        $sessionusersdata['updateSession'] = true;
        $sessionusersdata['updateUsers'] = false;
        $sessionusersdata['sessionSirh'] = new \stdClass();
        $sessionusersdata['sessionSirh']->libelleFormation = 'SIRH training bis';
        $sessionusersdata['sessionSirh']->libelleSession = 'SIRH session bis';
        $sessionusersdata['sessionSirh']->dateDebut = '10/10/2022';
        $sessionusersdata['sessionSirh']->dateFin = '10/11/2022';

        // Redefined get_session_users return false and check if execute this function.
        $apimock->expects($this->once())
            ->method('get_session_users')
            ->will($this->returnValue($sessionusersdata));

        $reflectionproperty = $reflection->getProperty('sirhrest');
        $reflectionproperty->setAccessible(true);
        $reflectionproperty->setValue($taskmock, $apimock);

        $taskmock->execute();

        // Check if send mail.
        $this->assertSame(1, $sink->count());
        $resultmail = $sink->get_messages();
        $this->assertCount(1, $resultmail);
        $sink->close();

        // Check content mail.
        $this->assertSame('Mentor : Modification des informations d\'une session SIRH', $resultmail[0]->subject);
        $this->assertSame($USER->email, $resultmail[0]->to);
        $this->assertSame('noreply@' . get_host_from_url($CFG->wwwroot), $resultmail[0]->from);
        $this->assertStringNotContainsString('Content-Type: text/plain', $resultmail[0]->header);

        // Check updated instance.
        $instance2 = (object) \enrol_sirh_external::get_instance_info($instanceid);

        // Same instance.
        self::assertEquals($instance1->id, $instance2->id);
        // User sync : Not change.
        self::assertEquals($instance1->customint2, $instance2->customint2);
        // Date sync : Change.
        self::assertNotEquals($oldtimeinstance1, $instance2->customint3);
        self::assertGreaterThan($oldtimeinstance1, $instance2->customint3);

        // No enrol user.
        self::assertEmpty(\enrol_sirh\sirh_api::get_instance_users($instanceid));

        self::resetAllData();
    }

    /**
     * Test check_update_sirh::execute ok
     * Update session SIRH user
     *
     * @covers \enrol_sirh\task\check_update_sirh::execute
     * @covers \enrol_sirh\task\check_update_sirh::send_email_update_user
     * @covers \enrol_sirh\task\check_update_sirh::send_email
     */
    public function test_check_update_sirh_execute_ok_update_session_sirh_user() {
        global $DB, $USER, $CFG;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_config();

        self::setAdminUser();

        $taskmock = $this->getMockBuilder('\enrol_sirh\task\check_update_sirh')
            ->setMethods(['send_email_update_data'])
            ->enableOriginalConstructor()
            ->getMock();

        // Check if never execute send_email_update_data function.
        $taskmock->expects($this->never())
            ->method('send_email_update_data');

        // Create session course.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $session->sessionstartdate = time();
        $session->update($session);
        $course = $session->get_course();

        // Close the default email sink.
        $sink = $this->redirectEmails();
        $sink->close();
        unset_config('noemailever');
        $sink = $this->redirectEmails();

        // Create instance SIRH.
        $sirh = 'SIRH';
        $sirhtraining = 'SIRH training';
        $sirhsession = 'SIRH session';

        $instanceid = \enrol_sirh\sirh_api::create_enrol_sirh_instance(
            $course->id,
            $sirh,
            $sirhtraining,
            $sirhsession
        );
        $instance1 = (object) \enrol_sirh_external::get_instance_info($instanceid);
        $instance1->customint2 = $USER->id;
        $oldtimeinstance1 = time() - 1000;
        $instance1->customint3 = $oldtimeinstance1;
        $DB->update_record('enrol', $instance1);
        $instance1->sessionname = $session->fullname;

        // No enrol user.
        self::assertEmpty(\enrol_sirh\sirh_api::get_instance_users($instanceid));

        $dbimock = $this->getMockBuilder('\enrol_sirh\database_interface')
            ->setMethods(['get_all_instance_sirh'])
            ->disableOriginalConstructor()
            ->getMock();

        // Redefined get_all_instance_sirh return new value and check if execute this function.
        $dbimock->expects($this->once())
            ->method('get_all_instance_sirh')
            ->will($this->returnValue([$instance1]));

        // Replace database interface by mock.
        $reflection = new ReflectionClass($taskmock);
        $reflectionproperty = $reflection->getProperty('dbi');
        $reflectionproperty->setAccessible(true);
        $reflectionproperty->setValue($taskmock, $dbimock);

        $apimock = $this->getMockBuilder('\enrol_sirh\sirh')
            ->setMethods(['get_session_users'])
            ->disableOriginalConstructor()
            ->getMock();

        // Session_users data.
        $user = new \stdClass();
        $user->lastname = 'lastname';
        $user->firstname = 'firstname';
        $user->email = 'moodle@mail.fr';
        $user->username = 'moodle@mail.fr';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $sessionusersdata = [];
        $sessionusersdata['users'] = [$user];
        $sessionusersdata['updateSession'] = false;
        $sessionusersdata['updateUsers'] = true;
        $sessionusersdata['sessionSirh'] = new \stdClass();
        $sessionusersdata['sessionSirh']->libelleFormation = 'SIRH training bis';
        $sessionusersdata['sessionSirh']->libelleSession = 'SIRH session bis';
        $sessionusersdata['sessionSirh']->dateDebut = '10/10/2022';
        $sessionusersdata['sessionSirh']->dateFin = '10/11/2022';

        // Redefined get_session_users return false and check if execute this function.
        $apimock->expects($this->once())
            ->method('get_session_users')
            ->will($this->returnValue($sessionusersdata));

        $reflectionproperty = $reflection->getProperty('sirhrest');
        $reflectionproperty->setAccessible(true);
        $reflectionproperty->setValue($taskmock, $apimock);

        $taskmock->execute();

        // Check if send mail.
        $this->assertSame(3, $sink->count());
        $resultmail = $sink->get_messages();
        $this->assertCount(3, $resultmail);
        $sink->close();

        // Check content mail.
        // First mail.
        $this->assertSame(format_string(get_site()->fullname) . ': ' . get_string('newusernewpasswordsubj'),
            $resultmail[0]->subject);
        $this->assertSame($user->email, $resultmail[0]->to);
        $this->assertSame('noreply@' . get_host_from_url($CFG->wwwroot), $resultmail[0]->from);
        $this->assertStringNotContainsString('Content-Type: text/plain', $resultmail[0]->header);

        // Second mail.
        $this->assertSame('[Mentor] Votre inscription à la formation fullname', $resultmail[1]->subject);
        $this->assertSame($user->email, $resultmail[1]->to);
        $this->assertSame('noreply@' . get_host_from_url($CFG->wwwroot), $resultmail[1]->from);
        $this->assertStringNotContainsString('Content-Type: text/plain', $resultmail[1]->header);

        // Third mail.
        $this->assertSame('Mentor : Modification des inscriptions à une session Mentor par un SIRH', $resultmail[2]->subject);
        $this->assertSame($USER->email, $resultmail[2]->to);
        $this->assertSame('noreply@' . get_host_from_url($CFG->wwwroot), $resultmail[2]->from);
        $this->assertStringNotContainsString('Content-Type: text/plain', $resultmail[2]->header);

        // Check updated instance.
        $instance2 = (object) \enrol_sirh_external::get_instance_info($instanceid);

        // Same instance.
        self::assertEquals($instance1->id, $instance2->id);
        // User sync : Not change.
        self::assertEquals($instance1->customint2, $instance2->customint2);
        // Date sync : Change.
        self::assertNotEquals($oldtimeinstance1, $instance2->customint3);
        self::assertGreaterThan($oldtimeinstance1, $instance2->customint3);

        // Enrol user.
        $instanceusers = \enrol_sirh\sirh_api::get_instance_users($instanceid);
        self::assertCount(1, $instanceusers);
        self::assertArrayHasKey($user->id, $instanceusers);

        self::resetAllData();
    }

    /**
     * Test check_update_sirh::execute ok
     * Update session SIRH user and data
     *
     * @covers \enrol_sirh\task\check_update_sirh::execute
     * @covers \enrol_sirh\task\check_update_sirh::send_email_update_user
     * @covers \enrol_sirh\task\check_update_sirh::send_email_update_data
     * @covers \enrol_sirh\task\check_update_sirh::send_email
     * @covers ::enrol_sirh_validate_users
     */
    public function test_check_update_sirh_execute_ok_update_session_sirh_user_and_data() {
        global $DB, $USER, $CFG;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_config();

        self::setAdminUser();

        $taskapi = new \enrol_sirh\task\check_update_sirh();

        // Create session course.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $session->sessionstartdate = time();
        $session->update($session);
        $course = $session->get_course();

        // Close the default email sink.
        $sink = $this->redirectEmails();
        $sink->close();
        unset_config('noemailever');
        $sink = $this->redirectEmails();

        // Create instance SIRH.
        $sirh = 'SIRH';
        $sirhtraining = 'SIRH training';
        $sirhsession = 'SIRH session';

        $instanceid = \enrol_sirh\sirh_api::create_enrol_sirh_instance(
            $course->id,
            $sirh,
            $sirhtraining,
            $sirhsession
        );
        $instance1 = (object) \enrol_sirh_external::get_instance_info($instanceid);
        $instance1->customint2 = $USER->id;
        $oldtimeinstance1 = time() - 1000;
        $instance1->customint3 = $oldtimeinstance1;
        $DB->update_record('enrol', $instance1);
        $instance1->sessionname = $session->fullname;

        // No enrol user.
        self::assertEmpty(\enrol_sirh\sirh_api::get_instance_users($instanceid));

        $dbimock = $this->getMockBuilder('\enrol_sirh\database_interface')
            ->setMethods(['get_all_instance_sirh'])
            ->disableOriginalConstructor()
            ->getMock();

        // Redefined get_all_instance_sirh return new value and check if execute this function.
        $dbimock->expects($this->once())
            ->method('get_all_instance_sirh')
            ->will($this->returnValue([$instance1]));

        // Replace database interface by mock.
        $reflection = new ReflectionClass($taskapi);
        $reflectionproperty = $reflection->getProperty('dbi');
        $reflectionproperty->setAccessible(true);
        $reflectionproperty->setValue($taskapi, $dbimock);

        $apimock = $this->getMockBuilder('\enrol_sirh\sirh')
            ->setMethods(['get_session_users'])
            ->disableOriginalConstructor()
            ->getMock();

        // Session_users data.
        $user = new \stdClass();
        $user->lastname = 'lastname';
        $user->firstname = 'firstname';
        $user->email = 'moodle@mail.fr';
        $user->username = 'moodle@mail.fr';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $sessionusersdata = [];
        $sessionusersdata['users'] = [$user];
        $sessionusersdata['updateSession'] = true;
        $sessionusersdata['updateUsers'] = true;
        $sessionusersdata['sessionSirh'] = new \stdClass();
        $sessionusersdata['sessionSirh']->libelleFormation = 'SIRH training bis';
        $sessionusersdata['sessionSirh']->libelleSession = 'SIRH session bis';
        $sessionusersdata['sessionSirh']->dateDebut = '10/10/2022';
        $sessionusersdata['sessionSirh']->dateFin = '10/11/2022';

        // Redefined get_session_users return false and check if execute this function.
        $apimock->expects($this->once())
            ->method('get_session_users')
            ->will($this->returnValue($sessionusersdata));

        $reflectionproperty = $reflection->getProperty('sirhrest');
        $reflectionproperty->setAccessible(true);
        $reflectionproperty->setValue($taskapi, $apimock);

        $taskapi->execute();

        // Check if send mail.
        $this->assertSame(4, $sink->count());
        $resultmail = $sink->get_messages();
        $this->assertCount(4, $resultmail);
        $sink->close();

        // Check content mail.
        // First mail.
        $this->assertSame(format_string(get_site()->fullname) . ': ' . get_string('newusernewpasswordsubj'),
            $resultmail[0]->subject);
        $this->assertSame($user->email, $resultmail[0]->to);
        $this->assertSame('noreply@' . get_host_from_url($CFG->wwwroot), $resultmail[0]->from);
        $this->assertStringNotContainsString('Content-Type: text/plain', $resultmail[0]->header);

        // Second mail.
        $this->assertSame('[Mentor] Votre inscription à la formation fullname', $resultmail[1]->subject);
        $this->assertSame($user->email, $resultmail[1]->to);
        $this->assertSame('noreply@' . get_host_from_url($CFG->wwwroot), $resultmail[1]->from);
        $this->assertStringNotContainsString('Content-Type: text/plain', $resultmail[1]->header);

        // Third mail.
        $this->assertSame('Mentor : Modification des informations d\'une session SIRH', $resultmail[2]->subject);
        $this->assertSame($USER->email, $resultmail[2]->to);
        $this->assertSame('noreply@' . get_host_from_url($CFG->wwwroot), $resultmail[2]->from);
        $this->assertStringNotContainsString('Content-Type: text/plain', $resultmail[2]->header);

        // Last mail.
        $this->assertSame('Mentor : Modification des inscriptions à une session Mentor par un SIRH', $resultmail[3]->subject);
        $this->assertSame($USER->email, $resultmail[3]->to);
        $this->assertSame('noreply@' . get_host_from_url($CFG->wwwroot), $resultmail[3]->from);
        $this->assertStringNotContainsString('Content-Type: text/plain', $resultmail[3]->header);

        // Check updated instance.
        $instance2 = (object) \enrol_sirh_external::get_instance_info($instanceid);

        // Same instance.
        self::assertEquals($instance1->id, $instance2->id);
        // User sync : Not change.
        self::assertEquals($instance1->customint2, $instance2->customint2);
        // Date sync : Change.
        self::assertNotEquals($oldtimeinstance1, $instance2->customint3);
        self::assertGreaterThan($oldtimeinstance1, $instance2->customint3);

        // Enrol user.
        $instanceusers = \enrol_sirh\sirh_api::get_instance_users($instanceid);
        self::assertCount(1, $instanceusers);
        self::assertArrayHasKey($user->id, $instanceusers);

        self::resetAllData();
    }
}
