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

use enrol_sirh\helper\testhelper;

require_once($CFG->dirroot . '/enrol/sirh/lib.php');

class enrol_sirh_plugin_testcase extends advanced_testcase {

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
        $instance->customint1 = null;
        $instance->roleid = $sirhplugin->get_config('roleid');

        return $instance;
    }

    /**
     * Test can delete instance function
     *
     * @covers  \enrol_sirh_plugin::can_delete_instance
     */
    public function test_can_delete_instance_ok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $course = self::getDataGenerator()->create_course();

        // Create new self enrol instance.
        $sirhplugin = enrol_get_plugin('sirh');

        $instance = $this->get_instance_data($course->id);

        self::assertTrue($sirhplugin->can_delete_instance($instance));

        self::setGuestUser();

        self::assertFalse($sirhplugin->can_delete_instance($instance));

        self::resetAllData();
    }

    /**
     * Test get instance name function
     *
     * @covers  \enrol_sirh_plugin::get_instance_name
     */
    public function test_get_instance_name_ok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $course = self::getDataGenerator()->create_course();

        // Create new self enrol instance.
        $sirhplugin = enrol_get_plugin('sirh');

        $instance = $this->get_instance_data($course->id);

        self::assertEquals(
            $sirhplugin->get_instance_name($instance),
            'Inscription SIRH (sirh - sirhtraining - sirhsession)'
        );

        self::resetAllData();
    }

    /**
     * Test can add instance function
     *
     * @covers  \enrol_sirh_plugin::can_add_instance
     */
    public function test_can_add_instance_ok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $course = self::getDataGenerator()->create_course();

        // Create new self enrol instance.
        $sirhplugin = enrol_get_plugin('sirh');

        self::assertFalse($sirhplugin->can_add_instance($course->id));

        self::resetAllData();
    }

    /**
     * Test add instance function
     *
     * @covers  \enrol_sirh_plugin::add_instance
     */
    public function test_add_instance_ok() {
        global $DB;

        $this->resetAfterTest(true);

        self::setAdminUser();

        $course = self::getDataGenerator()->create_course();

        // No sirh enrol instance.
        self::assertFalse($DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'sirh']));

        // Create new self enrol instance.
        $sirhplugin = enrol_get_plugin('sirh');
        $instance = $this->get_instance_data($course->id);
        $instanceid = $sirhplugin->add_instance($course, (array) $instance);

        // Sirh enrol instance create.
        $sirhinstances = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'sirh']);
        self::assertCount(1, $sirhinstances);
        self::assertArrayHasKey($instanceid, $sirhinstances);

        self::resetAllData();
    }

    /**
     * Test enrol sirh function
     *
     * @covers  \enrol_sirh_plugin::enrol_sirh
     * @covers  \enrol_sirh_plugin::get_enrol_info
     * @covers  \enrol_sirh_external::get_instance_info
     * @covers  \enrol_sirh_external::get_instance_info_parameters
     */
    public function test_enrol_sirh_ok() {
        global $DB;

        $this->resetAfterTest(true);
        testhelper::create_default_entity($this);

        self::setAdminUser();

        $course = self::getDataGenerator()->create_course();

        // Create new self enrol instance.
        $sirhplugin = enrol_get_plugin('sirh');
        $instance = $this->get_instance_data($course->id);
        $instanceid = $sirhplugin->add_instance($course, (array) $instance);
        $instance = (object) enrol_sirh_external::get_instance_info($instanceid);

        self::assertFalse($DB->record_exists('user_enrolments', ['enrolid' => $instanceid]));

        $user1 = $this->getDataGenerator()->create_user();

        $data = new \stdClass();
        $data->userid = $user1->id;
        $sirhplugin->enrol_sirh($instance, $data);

        $usersenrol = $DB->get_records('user_enrolments', ['enrolid' => $instanceid]);

        self::assertCount(1, $usersenrol);
        self::assertEquals($user1->id, current($usersenrol)->userid);
        self::assertEquals($instanceid, current($usersenrol)->enrolid);

        self::resetAllData();
    }

    /**
     * Test update status function
     *
     * @covers  \enrol_sirh_plugin::update_status
     */
    public function test_update_status_ok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $course = self::getDataGenerator()->create_course();

        // Create new self enrol instance.
        $sirhplugin = enrol_get_plugin('sirh');
        $instance = $this->get_instance_data($course->id);
        $instanceid = $sirhplugin->add_instance($course, (array) $instance);

        $instance = (object) enrol_sirh_external::get_instance_info($instanceid);
        self::assertEquals(ENROL_INSTANCE_ENABLED, $instance->status);

        $sirhplugin->update_status($instance, ENROL_INSTANCE_DISABLED);

        $instance = (object) enrol_sirh_external::get_instance_info($instanceid);
        self::assertEquals(ENROL_INSTANCE_DISABLED, $instance->status);

        $sirhplugin->update_status($instance, ENROL_INSTANCE_ENABLED);

        $instance = (object) enrol_sirh_external::get_instance_info($instanceid);
        self::assertEquals(ENROL_INSTANCE_ENABLED, $instance->status);

        self::resetAllData();
    }

    /**
     * Test allow unenrol user function
     *
     * @covers  \enrol_sirh_plugin::allow_unenrol_user
     */
    public function test_allow_unenrol_user_ok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create new self enrol instance.
        $sirhplugin = enrol_get_plugin('sirh');

        self::assertFalse($sirhplugin->allow_unenrol_user(new \stdClass(), new \stdClass()));

        self::resetAllData();
    }

    /**
     * Test update instance function
     *
     * @covers  \enrol_sirh_plugin::update_instance
     */
    public function test_update_instance_ok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $course = self::getDataGenerator()->create_course();

        // Create new self enrol instance.
        $sirhplugin = enrol_get_plugin('sirh');
        $instance = $this->get_instance_data($course->id);
        $instanceid = $sirhplugin->add_instance($course, (array) $instance);
        $instance = (object) enrol_sirh_external::get_instance_info($instanceid);

        self::assertEquals($instance->customchar1, 'sirh');
        self::assertEquals($instance->customchar2, 'sirhtraining');
        self::assertEquals($instance->customchar3, 'sirhsession');

        $data = new \stdClass();
        $data->customchar1 = 'newsirh';
        $data->customchar2 = 'newsirhtraining';
        $data->customchar3 = 'newsirhtraining';
        $data->roleid = $sirhplugin->get_config('roleid');
        $sirhplugin->update_instance($instance, $data);

        $newinstance = (object) enrol_sirh_external::get_instance_info($instanceid);

        self::assertEquals($newinstance->customchar1, 'newsirh');
        self::assertEquals($newinstance->customchar2, 'newsirhtraining');
        self::assertEquals($newinstance->customchar3, 'newsirhtraining');

        self::resetAllData();
    }

    /**
     * Test update instance function
     * Change group
     *
     * @covers  \enrol_sirh_plugin::update_instance
     */
    public function test_update_instance_ok_change_group() {
        $this->resetAfterTest(true);

        self::setAdminUser();
        testhelper::create_default_entity($this);

        $dbi = \enrol_sirh\database_interface::get_instance();

        $course = self::getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $group1 = $this->getDataGenerator()->create_group(
            ['courseid' => $course->id, 'name' => 'SIRH group 1']
        );
        $group2 = $this->getDataGenerator()->create_group(
            ['courseid' => $course->id, 'name' => 'SIRH group 2']
        );

        self::assertEmpty(groups_get_members($group1->id));
        self::assertEmpty(groups_get_members($group2->id));

        // Create new self enrol instance.
        $sirhplugin = enrol_get_plugin('sirh');
        $instance = $this->get_instance_data($course->id);
        $instanceid = $sirhplugin->add_instance($course, (array) $instance);
        $instance = (object) enrol_sirh_external::get_instance_info($instanceid);
        $sirhplugin->enrol_user($instance, $user->id);

        self::assertNull($instance->customint1);

        // Add group to instance sirh.
        $data = new \stdClass();
        $data->customint1 = $group1->id;

        $sirhplugin->update_instance($instance, $data);

        $instance = (object) enrol_sirh_external::get_instance_info($instanceid);
        self::assertEquals($instance->customint1, $group1->id);

        // User add to group1.
        $usergroup1 = groups_get_members($group1->id);
        self::assertCount(1, $usergroup1);
        self::assertArrayHasKey($user->id, $usergroup1);

        self::assertEmpty(groups_get_members($group2->id));

        // Change group to instance sirh.
        $data = new \stdClass();
        $data->customint1 = $group2->id;

        $sirhplugin->update_instance($instance, $data);

        $instance = (object) enrol_sirh_external::get_instance_info($instanceid);
        self::assertEquals($instance->customint1, $group2->id);

        // User remove to group1.
        self::assertEmpty(groups_get_members($group1->id));

        // User add to group2.
        $usergroup2 = groups_get_members($group2->id);
        self::assertCount(1, $usergroup2);
        self::assertArrayHasKey($user->id, $usergroup2);

        self::resetAllData();
    }

    /**
     * Test can hide show instance function
     *
     * @covers  \enrol_sirh_plugin::can_hide_show_instance
     */
    public function test_can_hide_show_instance_ok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $course = self::getDataGenerator()->create_course();

        // Create new self enrol instance.
        $sirhplugin = enrol_get_plugin('sirh');
        $instance = $this->get_instance_data($course->id);
        $instanceid = $sirhplugin->add_instance($course, (array) $instance);
        $instance = (object) enrol_sirh_external::get_instance_info($instanceid);

        self::assertTrue($sirhplugin->can_hide_show_instance($instance));

        self::setGuestUser();

        self::assertFalse($sirhplugin->can_hide_show_instance($instance));

        self::resetAllData();
    }

    /**
     * Test use standard editing ui function
     *
     * @covers  \enrol_sirh_plugin::use_standard_editing_ui
     */
    public function test_use_standard_editing_ui_ok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create new self enrol instance.
        $sirhplugin = enrol_get_plugin('sirh');

        self::assertTrue($sirhplugin->use_standard_editing_ui());

        self::resetAllData();
    }

    /**
     * Test edit instance validation function
     *
     * @covers  \enrol_sirh_plugin::edit_instance_validation
     */
    public function test_edit_instance_validation_ok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create new self enrol instance.
        $sirhplugin = enrol_get_plugin('sirh');

        $falseenroldata = [
            'customchar1' => 'sirh1',
            'customchar2' => 'sirh2',
            'customchar3' => 'sirh3',
            'customint1' => 1,
            'customint2' => 2,
            'customint3' => 3,
            'roleid' => 2,
        ];

        self::assertEmpty($sirhplugin->edit_instance_validation($falseenroldata));

        self::resetAllData();
    }

    /**
     * Test edit instance validation function not ok
     * Missing params
     *
     * @covers  \enrol_sirh_plugin::edit_instance_validation
     */
    public function test_edit_instance_validation_missing_params_nok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create new self enrol instance.
        $sirhplugin = enrol_get_plugin('sirh');

        $falseenroldata = [
            'customchar1' => 'sirh1',
            'customchar2' => 'sirh2',
            'customchar3' => 'sirh3',
        ];

        try {
            $sirhplugin->edit_instance_validation($falseenroldata);
            self::fail();
        } catch (\Exception $e) {
            self::assertInstanceOf('Exception', $e);
            self::assertEquals($e->getMessage(), 'Undefined array key "customint1"');
        }

        self::resetAllData();
    }
}
