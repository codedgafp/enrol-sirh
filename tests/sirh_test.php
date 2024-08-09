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

require_once($CFG->dirroot . '/enrol/sirh/classes/sirh.php');

class sirh_testcase extends advanced_testcase {

    /**
     * Reset the singletons
     *
     * @throws ReflectionException
     */
    public function reset_singletons() {
        global $CFG;

        $CFG->sirh_api_url = "www.sirh.fr";
        $CFG->sirh_api_token = "FALSEKEY";

        // Reset the mentor core specialization singleton.
        $sirh = \enrol_sirh\sirh::get_instance();
        $reflection = new ReflectionClass($sirh);
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
    }

    protected static function get_method($name) {
        $class = new ReflectionClass('enrol_sirh\sirh');
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }

    /**
     * Test get instance function OK
     *
     * @covers  \enrol_sirh\sirh::__construct
     * @covers  \enrol_sirh\sirh::get_instance
     */
    public function test_get_instance_ok() {
        global $CFG;

        $this->resetAfterTest(true);
        $this->init_config();
        self::setAdminUser();

        $sirh = \enrol_sirh\sirh::get_instance();

        $property = new ReflectionProperty("enrol_sirh\sirh", "url");
        $property->setAccessible(true);

        self::assertEquals($CFG->sirh_api_url, $property->getValue($sirh));

        $property = new ReflectionProperty("enrol_sirh\sirh", "key");
        $property->setAccessible(true);

        self::assertEquals($CFG->sirh_api_token, $property->getValue($sirh));

        $property = new ReflectionProperty("enrol_sirh\sirh", "inputsufix");
        $property->setAccessible(true);

        self::assertEquals("Input", $property->getValue($sirh));

        $property = new ReflectionProperty("enrol_sirh\sirh", "outputsufix");
        $property->setAccessible(true);

        self::assertEquals("Output", $property->getValue($sirh));

        $property = new ReflectionProperty("enrol_sirh\sirh", "sirhapi");
        $property->setAccessible(true);

        $sirhapi = $property->getValue($sirh);

        self::isInstanceOf('\RestClient', $sirhapi);
        self::assertEquals($sirhapi->options['base_url'], $CFG->sirh_api_url);
        self::assertEquals($sirhapi->options['curl_options'], [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => 'CURL_HTTP_VERSION_1_1',
        ]);
        self::assertEquals($sirhapi->options['headers'], [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $CFG->sirh_api_token,
        ]);

        self::resetAllData();
    }

    /**
     * Test status function OK
     *
     * @covers  \enrol_sirh\sirh::__construct
     * @covers  \enrol_sirh\sirh::get_instance
     * @covers  \enrol_sirh\sirh::check_status
     */
    public function test_status_ok() {
        $this->resetAfterTest(true);
        $this->init_config();
        self::setAdminUser();

        $sirh = \enrol_sirh\sirh::get_instance();

        $result = new \stdClass();
        $result->response_status_lines = ['HTTP/1.1 200'];

        $reflection = new \ReflectionClass(get_class($sirh));
        $method = $reflection->getMethod('check_status');
        $method->setAccessible(true);

        self::assertEmpty($method->invokeArgs($sirh, [$result]));

        $result->response_status_lines = ['HTTP/1.1 404'];

        try {
            $method->invokeArgs($sirh, [$result]);
            self::fail();
        } catch (\moodle_exception $e) {
            self::assertInstanceOf('moodle_exception', $e);
            self::assertEquals('Service SIRH is not available.', $e->getMessage());
        }

        $result->response_status_lines = ['HTTP/1.1 500'];

        try {
            $method->invokeArgs($sirh, [$result]);
            self::fail();
        } catch (\moodle_exception $e) {
            self::assertInstanceOf('moodle_exception', $e);
            self::assertEquals('Service SIRH is not available.', $e->getMessage());
        }

        $result->response_status_lines = ['HTTP/1.1 504'];

        try {
            $method->invokeArgs($sirh, [$result]);
            self::fail();
        } catch (\moodle_exception $e) {
            self::assertInstanceOf('moodle_exception', $e);
            self::assertEquals('Service SIRH is not available.', $e->getMessage());
        }

        self::resetAllData();
    }

    /**
     * Test get sirh sessions function OK
     *
     * @covers  \enrol_sirh\sirh::__construct
     * @covers  \enrol_sirh\sirh::get_instance
     * @covers  \enrol_sirh\sirh::get_sirh_sessions
     */
    public function test_get_sirh_sessions_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_config();
        self::setAdminUser();

        $sirh = \enrol_sirh\sirh::get_instance();

        $result = new RestClient();
        $result->response_status_lines = ['HTTP/1.1 200'];
        $result->response
            = '{"contenu":[{"identifiantSirhOrigine": "SIRH", ' .
              '"identifiantFormation": "SIRHTRAINING","libelleFormation": ' .
              '"SIRHTRAININGNAME","identifiantSession": "SIRHSESSION","libelleSession": ' .
              '"SIRHSESSIONAME","dateDebut": "2020-06-22", "dateFin": "2020-06-30"}]}';

        // Create REST Client Mock.
        $restclientmock = $this->getMockBuilder('\RestClient')
            ->setMethods(['get'])
            ->disableOriginalConstructor()
            ->disallowMockingUnknownTypes()
            ->getMock();

        // Return 1 value when get_course_format_option function call one time.
        $restclientmock->expects($this->once())
            ->method('get')
            ->will($this->returnValue($result));

        // Replace dbinterface data with database interface Mock in training Mock.
        $reflection = new ReflectionClass($sirh);
        $reflectionproperty = $reflection->getProperty('sirhapi');
        $reflectionproperty->setAccessible(true);
        $reflectionproperty->setValue($sirh, $restclientmock);

        $sirhsessions = $sirh->get_sirh_sessions(['']);

        self::assertCount(1, $sirhsessions);

        self::assertEquals($sirhsessions[0]->sirh, "SIRH");
        self::assertEquals($sirhsessions[0]->sirhtraining, "SIRHTRAINING");
        self::assertEquals($sirhsessions[0]->sirhtrainingname, "SIRHTRAININGNAME");
        self::assertEquals($sirhsessions[0]->sirhsession, "SIRHSESSION");
        self::assertEquals($sirhsessions[0]->sirhsessionname, "SIRHSESSIONAME");
        self::assertEquals($sirhsessions[0]->startdate, "22/06/2020");
        self::assertEquals($sirhsessions[0]->enddate, "30/06/2020");

        self::resetAllData();
    }

    /**
     * Test get sirh sessions function NOT OK
     * ERROR 400
     *
     * @covers  \enrol_sirh\sirh::__construct
     * @covers  \enrol_sirh\sirh::get_instance
     * @covers  \enrol_sirh\sirh::get_sirh_sessions
     */
    public function test_get_sirh_sessions_nok_error_400() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_config();
        self::setAdminUser();

        $sirh = \enrol_sirh\sirh::get_instance();

        $result = new RestClient();
        $result->response_status_lines = ['HTTP/1.1 400'];
        $result->response = '';

        // Create REST Client Mock.
        $restclientmock = $this->getMockBuilder('\RestClient')
            ->setMethods(['get'])
            ->disableOriginalConstructor()
            ->disallowMockingUnknownTypes()
            ->getMock();

        $restclientmock->expects($this->once())
            ->method('get')
            ->will($this->returnValue($result));

        $reflection = new ReflectionClass($sirh);
        $reflectionproperty = $reflection->getProperty('sirhapi');
        $reflectionproperty->setAccessible(true);
        $reflectionproperty->setValue($sirh, $restclientmock);

        $sirhsessions = $sirh->get_sirh_sessions([]);

        self::assertEmpty($sirhsessions);

        self::resetAllData();
    }

    /**
     * Test count sirh sessions function OK
     *
     * @covers  \enrol_sirh\sirh::__construct
     * @covers  \enrol_sirh\sirh::get_instance
     * @covers  \enrol_sirh\sirh::count_sirh_sessions
     */
    public function test_count_sirh_sessions_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_config();
        self::setAdminUser();

        $sirh = \enrol_sirh\sirh::get_instance();

        $result = new RestClient();
        $result->response_status_lines = ['HTTP/1.1 200'];
        $result->response = '{"totalElements":1}';

        // Create REST Client Mock.
        $restclientmock = $this->getMockBuilder('\RestClient')
            ->setMethods(['get'])
            ->disableOriginalConstructor()
            ->disallowMockingUnknownTypes()
            ->getMock();

        $restclientmock->expects($this->once())
            ->method('get')
            ->will($this->returnValue($result));

        $reflection = new ReflectionClass($sirh);
        $reflectionproperty = $reflection->getProperty('sirhapi');
        $reflectionproperty->setAccessible(true);
        $reflectionproperty->setValue($sirh, $restclientmock);

        $sirhsessionscount = $sirh->count_sirh_sessions(['']);

        self::assertEquals(1, $sirhsessionscount);

        self::resetAllData();
    }

    /**
     * Test count sirh sessions function NOT OK
     * ERROR 400
     *
     * @covers  \enrol_sirh\sirh::__construct
     * @covers  \enrol_sirh\sirh::get_instance
     * @covers  \enrol_sirh\sirh::count_sirh_sessions
     */
    public function test_count_sirh_sessions_nok_error_400() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_config();
        self::setAdminUser();

        $sirh = \enrol_sirh\sirh::get_instance();

        $result = new RestClient();
        $result->response_status_lines = ['HTTP/1.1 400'];
        $result->response
            = '{"totalElements":1}';

        // Create REST Client Mock.
        $restclientmock = $this->getMockBuilder('\RestClient')
            ->setMethods(['get'])
            ->disableOriginalConstructor()
            ->disallowMockingUnknownTypes()
            ->getMock();

        $restclientmock->expects($this->once())
            ->method('get')
            ->will($this->returnValue($result));

        $reflection = new ReflectionClass($sirh);
        $reflectionproperty = $reflection->getProperty('sirhapi');
        $reflectionproperty->setAccessible(true);
        $reflectionproperty->setValue($sirh, $restclientmock);

        $sirhsessionscount = $sirh->count_sirh_sessions([]);

        self::assertEquals(0, $sirhsessionscount);

        self::resetAllData();
    }

    /**
     * Test get session users function OK
     *
     * @covers  \enrol_sirh\sirh::__construct
     * @covers  \enrol_sirh\sirh::get_instance
     * @covers  \enrol_sirh\sirh::get_session_users
     */
    public function test_get_session_users_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_config();
        self::setAdminUser();

        $sirh = \enrol_sirh\sirh::get_instance();

        $result = new RestClient();

        $data = new \stdClass();
        $data->NombreUtilisateursInscrits = 3;
        $data->IndicateurMajSession = false;
        $data->IndicateurMajInscriptions = true;
        $data->SessionSirh = [];

        $user1 = new \stdClass();
        $user1->nom = 'lastname1';
        $user1->prenom = 'firstname1';
        $user1->email = 'user1@mail.fr';

        $user2 = new \stdClass();
        $user2->nom = 'Lastname2';
        $user2->prenom = 'Firstname2';
        $user2->email = 'User2@Mail.fr';

        $user3 = new \stdClass();
        $user3->nom = 'LASTNAME3';
        $user3->prenom = 'FIRSTNAME3';
        $user3->email = 'USER3@MAIL.FR';

        $data->UtilisateurSirh = [$user1, $user2, $user3];

        $result->response = json_encode($data);

        $result->response_status_lines = 'HTTP/1.1 200';

        // Create REST Client Mock.
        $restclientmock = $this->getMockBuilder('\RestClient')
            ->setMethods(['get'])
            ->disableOriginalConstructor()
            ->disallowMockingUnknownTypes()
            ->getMock();

        $restclientmock->expects($this->once())
            ->method('get')
            ->will($this->returnValue($result));

        $reflection = new ReflectionClass($sirh);
        $reflectionproperty = $reflection->getProperty('sirhapi');
        $reflectionproperty->setAccessible(true);
        $reflectionproperty->setValue($sirh, $restclientmock);

        $sessionusers = $sirh->get_session_users('sirh', 'sirhtraining', 'sirhsession', null, time());

        self::assertCount(6, $sessionusers);

        self::assertArrayHasKey('nbTotalUsers', $sessionusers);
        self::assertEquals($sessionusers['nbTotalUsers'], 3);

        self::assertArrayHasKey('users', $sessionusers);
        self::assertIsArray($sessionusers['users']);
        self::assertCount(3, $sessionusers['users']);

        self::assertEquals($sessionusers['users'][0]->lastname, 'lastname1');
        self::assertEquals($sessionusers['users'][0]->firstname, 'firstname1');
        self::assertEquals($sessionusers['users'][0]->email, 'user1@mail.fr');
        self::assertEquals($sessionusers['users'][0]->username, 'user1@mail.fr');
        self::assertEquals($sessionusers['users'][0]->mnethostid, 1);
        self::assertEquals($sessionusers['users'][0]->confirmed, 1);

        self::assertEquals($sessionusers['users'][1]->lastname, 'Lastname2');
        self::assertEquals($sessionusers['users'][1]->firstname, 'Firstname2');
        self::assertEquals($sessionusers['users'][1]->email, 'user2@mail.fr');
        self::assertEquals($sessionusers['users'][1]->username, 'user2@mail.fr');
        self::assertEquals($sessionusers['users'][1]->mnethostid, 1);
        self::assertEquals($sessionusers['users'][1]->confirmed, 1);

        self::assertEquals($sessionusers['users'][2]->lastname, 'LASTNAME3');
        self::assertEquals($sessionusers['users'][2]->firstname, 'FIRSTNAME3');
        self::assertEquals($sessionusers['users'][2]->email, 'user3@mail.fr');
        self::assertEquals($sessionusers['users'][2]->username, 'user3@mail.fr');
        self::assertEquals($sessionusers['users'][2]->mnethostid, 1);
        self::assertEquals($sessionusers['users'][2]->confirmed, 1);

        self::assertArrayHasKey('nbUsers', $sessionusers);
        self::assertEquals($sessionusers['nbUsers'], 3);

        self::resetAllData();
    }

    /**
     * Test get session users function OK
     * With nbusers argument
     *
     * @covers  \enrol_sirh\sirh::__construct
     * @covers  \enrol_sirh\sirh::get_instance
     * @covers  \enrol_sirh\sirh::get_session_users
     */
    public function test_get_session_users_ok_with_nbusers() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_config();
        self::setAdminUser();

        $sirh = \enrol_sirh\sirh::get_instance();

        $result = new RestClient();

        $data = new \stdClass();
        $data->NombreUtilisateursInscrits = 3;
        $data->IndicateurMajSession = false;
        $data->IndicateurMajInscriptions = true;
        $data->SessionSirh = [];

        $user1 = new \stdClass();
        $user1->nom = 'lastname1';
        $user1->prenom = 'firstname1';
        $user1->email = 'user1@mail.fr';

        $user2 = new \stdClass();
        $user2->nom = 'Lastname2';
        $user2->prenom = 'Firstname2';
        $user2->email = 'User2@Mail.fr';

        $user3 = new \stdClass();
        $user3->nom = 'LASTNAME3';
        $user3->prenom = 'FIRSTNAME3';
        $user3->email = 'USER3@MAIL.FR';

        $data->UtilisateurSirh = [$user1, $user2, $user3];

        $result->response = json_encode($data);

        $result->response_status_lines = 'HTTP/1.1 200';

        // Create REST Client Mock.
        $restclientmock = $this->getMockBuilder('\RestClient')
            ->setMethods(['get'])
            ->disableOriginalConstructor()
            ->disallowMockingUnknownTypes()
            ->getMock();

        $restclientmock->expects($this->once())
            ->method('get')
            ->will($this->returnValue($result));

        $reflection = new ReflectionClass($sirh);
        $reflectionproperty = $reflection->getProperty('sirhapi');
        $reflectionproperty->setAccessible(true);
        $reflectionproperty->setValue($sirh, $restclientmock);

        $sessionusers = $sirh->get_session_users('sirh', 'sirhtraining', 'sirhsession', 2, time());

        self::assertCount(6, $sessionusers);

        self::assertArrayHasKey('nbTotalUsers', $sessionusers);
        self::assertEquals($sessionusers['nbTotalUsers'], 3);

        self::assertArrayHasKey('users', $sessionusers);
        self::assertIsArray($sessionusers['users']);
        self::assertCount(2, $sessionusers['users']);

        self::assertEquals($sessionusers['users'][0]->lastname, 'lastname1');
        self::assertEquals($sessionusers['users'][0]->firstname, 'firstname1');
        self::assertEquals($sessionusers['users'][0]->email, 'user1@mail.fr');
        self::assertEquals($sessionusers['users'][0]->username, 'user1@mail.fr');
        self::assertEquals($sessionusers['users'][0]->mnethostid, 1);
        self::assertEquals($sessionusers['users'][0]->confirmed, 1);

        self::assertEquals($sessionusers['users'][1]->lastname, 'Lastname2');
        self::assertEquals($sessionusers['users'][1]->firstname, 'Firstname2');
        self::assertEquals($sessionusers['users'][1]->email, 'user2@mail.fr');
        self::assertEquals($sessionusers['users'][1]->username, 'user2@mail.fr');
        self::assertEquals($sessionusers['users'][1]->mnethostid, 1);
        self::assertEquals($sessionusers['users'][1]->confirmed, 1);

        self::assertArrayHasKey('nbUsers', $sessionusers);
        self::assertEquals($sessionusers['nbUsers'], 2);

        self::resetAllData();
    }
}
