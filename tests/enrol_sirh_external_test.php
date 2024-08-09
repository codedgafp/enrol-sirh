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

require_once($CFG->dirroot . '/enrol/sirh/externallib.php');

class enrol_sirh_external_testcase extends advanced_testcase {

    public function get_instance_data($courseid) {
        // Create new self enrol instance.
        $sirhplugin = enrol_get_plugin('sirh');

        $instance = (object) $sirhplugin->get_instance_defaults();
        $instance->status = 0;
        $instance->id = '';
        $instance->courseid = $courseid;
        $instance->expirythreshold = 0;
        $instance->enrolstartdate = 0;
        $instance->enrolenddate = 0;
        $instance->timecreated = time();
        $instance->timemodified = time();
        $instance->customchar1 = 'sirh';
        $instance->customchar2 = 'sirhtraining';
        $instance->customchar3 = 'sirhsession';
        $instance->roleid = $sirhplugin->get_config('roleid');

        return $instance;
    }

    /**
     * Test get instance info parameters function
     *
     * @covers  \enrol_sirh_external::get_instance_info_parameters
     */
    public function test_get_instance_info_parameters_ok() {
        $this->resetAfterTest(true);

        $infoparamaters = enrol_sirh_external::get_instance_info_parameters();

        self::assertCount(1, $infoparamaters->keys);

        self::arrayHasKey("instanceid", $infoparamaters->keys);
        self::assertEquals("int", $infoparamaters->keys['instanceid']->type);
        self::assertTrue($infoparamaters->keys['instanceid']->allownull);
        self::assertEquals("instance id of sirh enrolment plugin.", $infoparamaters->keys['instanceid']->desc);
        self::assertEquals(1, $infoparamaters->keys['instanceid']->required);
        self::assertNull($infoparamaters->keys['instanceid']->default);

        self::resetAllData();
    }

    /**
     * Test get instance info function
     *
     * @covers  \enrol_sirh_external::get_instance_info
     */
    public function test_get_instance_info_ok() {
        global $DB;

        $this->resetAfterTest(true);

        self::setAdminUser();

        $course = self::getDataGenerator()->create_course();

        // Create new self enrol instance.
        $sirhplugin = enrol_get_plugin('sirh');
        $instance = $this->get_instance_data($course->id);
        $instanceid = $sirhplugin->add_instance($course, (array) $instance);

        self::assertEquals(
            $DB->get_record('enrol', ["id" => $instanceid]),
            (object) enrol_sirh_external::get_instance_info($instanceid)
        );

        self::resetAllData();
    }

    /**
     * Test get instance info returns function
     *
     * @covers  \enrol_sirh_external::get_instance_info_returns
     */
    public function test_get_instance_info_returns_ok() {
        $this->resetAfterTest(true);

        $infoparamaters = enrol_sirh_external::get_instance_info_returns();

        self::assertCount(11, $infoparamaters->keys);

        self::arrayHasKey("id", $infoparamaters->keys);
        self::assertEquals("int", $infoparamaters->keys['id']->type);
        self::assertTrue($infoparamaters->keys['id']->allownull);
        self::assertEquals("id of course enrolment instance", $infoparamaters->keys['id']->desc);
        self::assertEquals(1, $infoparamaters->keys['id']->required);
        self::assertNull($infoparamaters->keys['id']->default);

        self::arrayHasKey("courseid", $infoparamaters->keys);
        self::assertEquals("int", $infoparamaters->keys['courseid']->type);
        self::assertTrue($infoparamaters->keys['courseid']->allownull);
        self::assertEquals("id of course", $infoparamaters->keys['courseid']->desc);
        self::assertEquals(1, $infoparamaters->keys['courseid']->required);
        self::assertNull($infoparamaters->keys['courseid']->default);

        self::arrayHasKey("type", $infoparamaters->keys);
        self::assertEquals("plugin", $infoparamaters->keys['type']->type);
        self::assertTrue($infoparamaters->keys['type']->allownull);
        self::assertEquals("type of enrolment plugin", $infoparamaters->keys['type']->desc);
        self::assertEquals(1, $infoparamaters->keys['type']->required);
        self::assertNull($infoparamaters->keys['type']->default);

        self::arrayHasKey("name", $infoparamaters->keys);
        self::assertEquals("raw", $infoparamaters->keys['name']->type);
        self::assertTrue($infoparamaters->keys['name']->allownull);
        self::assertEquals("name of enrolment plugin", $infoparamaters->keys['name']->desc);
        self::assertEquals(1, $infoparamaters->keys['name']->required);
        self::assertNull($infoparamaters->keys['name']->default);

        self::arrayHasKey("status", $infoparamaters->keys);
        self::assertEquals("raw", $infoparamaters->keys['status']->type);
        self::assertTrue($infoparamaters->keys['status']->allownull);
        self::assertEquals("status of enrolment plugin", $infoparamaters->keys['status']->desc);
        self::assertEquals(1, $infoparamaters->keys['status']->required);
        self::assertNull($infoparamaters->keys['status']->default);

        self::arrayHasKey("customchar1", $infoparamaters->keys);
        self::assertEquals("raw", $infoparamaters->keys['customchar1']->type);
        self::assertTrue($infoparamaters->keys['customchar1']->allownull);
        self::assertEquals("SIRH id", $infoparamaters->keys['customchar1']->desc);
        self::assertEquals(1, $infoparamaters->keys['customchar1']->required);
        self::assertNull($infoparamaters->keys['customchar1']->default);

        self::arrayHasKey("customchar2", $infoparamaters->keys);
        self::assertEquals("raw", $infoparamaters->keys['customchar2']->type);
        self::assertTrue($infoparamaters->keys['customchar2']->allownull);
        self::assertEquals("SIRH training id", $infoparamaters->keys['customchar2']->desc);
        self::assertEquals(1, $infoparamaters->keys['customchar2']->required);
        self::assertNull($infoparamaters->keys['customchar2']->default);

        self::arrayHasKey("customchar3", $infoparamaters->keys);
        self::assertEquals("raw", $infoparamaters->keys['customchar3']->type);
        self::assertTrue($infoparamaters->keys['customchar3']->allownull);
        self::assertEquals("SIRH session id", $infoparamaters->keys['customchar3']->desc);
        self::assertEquals(1, $infoparamaters->keys['customchar3']->required);
        self::assertNull($infoparamaters->keys['customchar3']->default);

        self::arrayHasKey("customint1", $infoparamaters->keys);
        self::assertEquals("int", $infoparamaters->keys['customint1']->type);
        self::assertTrue($infoparamaters->keys['customint1']->allownull);
        self::assertEquals("Group id", $infoparamaters->keys['customint1']->desc);
        self::assertEquals(1, $infoparamaters->keys['customint1']->required);
        self::assertNull($infoparamaters->keys['customint1']->default);

        self::arrayHasKey("customint2", $infoparamaters->keys);
        self::assertEquals("int", $infoparamaters->keys['customint2']->type);
        self::assertTrue($infoparamaters->keys['customint2']->allownull);
        self::assertEquals("Last user id to sync", $infoparamaters->keys['customint2']->desc);
        self::assertEquals(1, $infoparamaters->keys['customint2']->required);
        self::assertNull($infoparamaters->keys['customint2']->default);

        self::arrayHasKey("customint3", $infoparamaters->keys);
        self::assertEquals("int", $infoparamaters->keys['customint3']->type);
        self::assertTrue($infoparamaters->keys['customint3']->allownull);
        self::assertEquals("Last date to sync", $infoparamaters->keys['customint3']->desc);
        self::assertEquals(1, $infoparamaters->keys['customint3']->required);
        self::assertNull($infoparamaters->keys['customint3']->default);

        self::resetAllData();
    }

    /**
     * Test enrol user parameters function
     *
     * @covers  \enrol_sirh_external::enrol_user_parameters
     */
    public function test_enrol_user_parameters_ok() {
        $this->resetAfterTest(true);

        $infoparamaters = enrol_sirh_external::enrol_user_parameters();

        self::assertCount(3, $infoparamaters->keys);

        self::arrayHasKey("courseid", $infoparamaters->keys);
        self::assertEquals("int", $infoparamaters->keys['courseid']->type);
        self::assertTrue($infoparamaters->keys['courseid']->allownull);
        self::assertEquals("Id of the course", $infoparamaters->keys['courseid']->desc);
        self::assertEquals(1, $infoparamaters->keys['courseid']->required);
        self::assertNull($infoparamaters->keys['courseid']->default);

        self::arrayHasKey("instanceid", $infoparamaters->keys);
        self::assertEquals("int", $infoparamaters->keys['instanceid']->type);
        self::assertTrue($infoparamaters->keys['instanceid']->allownull);
        self::assertEquals("Instance id of self enrolment plugin.", $infoparamaters->keys['instanceid']->desc);
        self::assertEquals(0, $infoparamaters->keys['instanceid']->required);
        self::assertEquals(0, $infoparamaters->keys['instanceid']->default);

        self::arrayHasKey("userid", $infoparamaters->keys);
        self::assertEquals("int", $infoparamaters->keys['userid']->type);
        self::assertTrue($infoparamaters->keys['userid']->allownull);
        self::assertEquals("User id", $infoparamaters->keys['userid']->desc);
        self::assertEquals(0, $infoparamaters->keys['userid']->required);
        self::assertEquals(0, $infoparamaters->keys['userid']->default);

        self::resetAllData();
    }

    /**
     * Test enrol user function
     *
     * @covers  \enrol_sirh_external::enrol_user
     */
    public function test_enrol_user_ok() {
        global $DB;

        $this->resetAfterTest(true);

        self::setAdminUser();

        $course = self::getDataGenerator()->create_course();

        // Create new self enrol instance.
        $sirhplugin = enrol_get_plugin('sirh');
        $instance = $this->get_instance_data($course->id);
        $instanceid = $sirhplugin->add_instance($course, (array) $instance);
        $instance = (object) enrol_sirh_external::get_instance_info($instanceid);

        self::assertFalse($DB->record_exists('user_enrolments', ['enrolid' => $instanceid]));

        $user1 = $this->getDataGenerator()->create_user();
        $result = enrol_sirh_external::enrol_user($instance->courseid, $instance->id, $user1->id);

        self::assertTrue($result['status']);
        self::assertEmpty($result["warnings"]);

        $usersenrol = $DB->get_records('user_enrolments', ['enrolid' => $instanceid]);

        self::assertCount(1, $usersenrol);
        self::assertEquals($user1->id, current($usersenrol)->userid);
        self::assertEquals($instanceid, current($usersenrol)->enrolid);

        self::resetAllData();
    }

    /**
     * Test enrol user returns function
     *
     * @covers  \enrol_sirh_external::enrol_user_returns
     */
    public function test_enrol_user_returns_ok() {
        $this->resetAfterTest(true);

        $infoparamaters = enrol_sirh_external::enrol_user_returns();

        self::assertCount(2, $infoparamaters->keys);

        self::arrayHasKey("status", $infoparamaters->keys);
        self::assertEquals("bool", $infoparamaters->keys['status']->type);
        self::assertTrue($infoparamaters->keys['status']->allownull);
        self::assertEquals("status: true if the user is enrolled, false otherwise", $infoparamaters->keys['status']->desc);
        self::assertEquals(1, $infoparamaters->keys['status']->required);
        self::assertNull($infoparamaters->keys['status']->default);

        self::arrayHasKey("warnings", $infoparamaters->keys);
        self::assertIsObject($infoparamaters->keys['warnings']);
        self::assertInstanceOf('external_warnings', $infoparamaters->keys['warnings']);

        self::resetAllData();
    }
}
