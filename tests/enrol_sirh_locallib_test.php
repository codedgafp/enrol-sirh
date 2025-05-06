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

require_once($CFG->dirroot . '/enrol/sirh/locallib.php');

class enrol_sirh_locallib_testcase extends advanced_testcase {

    /**
     * Test enrol sirh get sirh list function
     *
     * @covers ::enrol_sirh_get_sirh_list
     */
    public function test_enrol_sirh_get_sirh_list_ok() {
        $this->resetAfterTest(true);
        self::setAdminUser();

        $sirhlist = [
            'AES' => 'Musée Air  Espace',
            'AGR' => 'MAA',
            'CCO' => 'Cour des Comptes',
            'CET' => 'Conseil d Etat',
            'CNI' => 'CNIL',
            'CNM' => 'CNMSS',
            'CSA' => 'Conseil sup Audio',
            'DDD' => 'Défenseur des Droi',
            'EDA' => 'Ecole Air',
            'EIF' => 'Univ. Gust. Eiffel',
            'ENV' => 'Ministère écologie',
            'GET' => 'ANCT (ex cget)',
            'INI' => 'INI',
            'MCC' => 'Ministère MCC',
            'MDA' => 'Musée de l\'armée',
            'MEN' => 'Min Educ Nat Jeun.',
            'MMA' => 'Musée de la Marine',
            'MQB' => 'Musée MQB',
            'MSO' => 'Ministères sociaux',
            'MTO' => 'Météo France',
            'NAH' => 'ANAH',
            'NAO' => 'Inst Nat Orig Qual',
            'NAV' => 'École navale',
            'OFB' => 'OFB',
            'ONA' => 'ONAC-VG',
            'PAD' => 'ECPAD',
            'SAE' => 'ISAE Supaéro',
            'SHO' => 'SHOM',
            'SPM' => 'Services du PM',
            'STA' => 'ENSTA Bretagne',
            'VNF' => 'VNF',
        ];

        $sirhlistresult = enrol_sirh_get_sirh_list();

        foreach ($sirhlist as $key => $sirh) {
            self::assertArrayHasKey('RENOIRH_' . $key, $sirhlistresult);
            self::assertEquals($sirhlistresult['RENOIRH_' . $key], 'RENOIRH_' . $key);
        }

        set_config('sirhlist', '', 'enrol_sirh');

        self::assertEmpty(enrol_sirh_get_sirh_list());

        self::resetAllData();
    }

    public function get_false_instance_data() {

        $course = self::getDataGenerator()->create_course();

        $instance = new \stdClass();
        $instance->courseid = $course->id;
        $instance->customchar1 = 'SIRH';
        $instance->customchar2 = 'SIRH_TRAINING';
        $instance->customchar3 = 'SIRH_SESSION';
        return $instance;
    }

    public function get_default_users_data($nbuser = 3) {

        $users = [];

        for ($i = 1; $i <= $nbuser; $i++) {
            $user = new \stdClass();
            $user->lastname = 'lastname' . $i;
            $user->firstname = 'firstname' . $i;
            $user->email = 'email' . $i . '@mail.fr';
            $user->username = 'email' . $i . '@mail.fr';
            $user->mnethostid = 1;
            $user->confirmed = 1;

            $users[] = $user;
        }

        return $users;
    }

    /**
     * Test enrol sirh plugin is enabled function
     *
     * @covers ::enrol_sirh_plugin_is_enabled
     */
    public function test_enrol_sirh_plugin_is_enabled_ok() {
        $this->resetAfterTest(true);
        self::setAdminUser();

        set_config('enrol_plugins_enabled', 'manual,guest,self,meta');

        self::assertFalse(enrol_sirh_plugin_is_enabled());

        set_config('enrol_plugins_enabled', 'manual,guest,self,meta,sirh');

        self::assertTrue(enrol_sirh_plugin_is_enabled());

        self::resetAllData();
    }

    /**
     * Test enrol_sirh_validate_users function
     *
     * @covers ::enrol_sirh_validate_users
     */
    public function test_enrol_sirh_validate_users_ok() {
        $this->resetAfterTest(true);
        self::setAdminUser();

        $instance = $this->get_false_instance_data();
        $users = $this->get_default_users_data();

        $preview = [];
        $preview['list'] = [];
        $preview['validforcreation'] = 0;
        $preview['validlines'] = 0;

        enrol_sirh_validate_users($users, $instance, SIRH_NOTIFICATION_TYPE_MESSAGE, $preview, $errors, $warnings);

        self::assertCount(3, $preview);
        self::assertArrayHasKey('list', $preview);
        self::assertCount(3, $preview['list']);
        self::assertEquals($preview['list'], $users);
        self::assertNull($errors);
        self::assertNull($warnings);
        self::assertArrayHasKey('validforcreation', $preview);
        self::assertEquals($preview['validforcreation'], 3);
        self::assertArrayHasKey('validlines', $preview);
        self::assertEquals($preview['validlines'], 3);

        self::resetAllData();
    }

    /**
     * Test enrol_sirh_validate_users function
     * email special character error
     *
     * @covers ::enrol_sirh_validate_users
     */
    public function test_enrol_sirh_validate_users_nok_email_special_caracter() {
        $this->resetAfterTest(true);
        self::setAdminUser();

        $instance = $this->get_false_instance_data();
        $users = $this->get_default_users_data();

        $specialuser = new \stdClass();
        $specialuser->lastname = 'lastname4';
        $specialuser->firstname = 'firstname4';
        $specialuser->email = 'email#@mail.fr';
        $specialuser->username = 'email#@mail.fr';
        $specialuser->mnethostid = 1;
        $specialuser->confirmed = 1;

        $users[] = $specialuser;

        enrol_sirh_validate_users($users, $instance, SIRH_NOTIFICATION_TYPE_MESSAGE, $preview, $errors, $warnings);

        self::assertCount(1, $preview);
        self::assertArrayHasKey('list', $preview);
        self::assertCount(4, $preview['list']);
        self::assertEquals($preview['list'], array_slice($users, 0, 4));

        self::assertNull($errors);

        self::assertNull($warnings);

        unset($preview);
        unset($errors);
        unset($warnings);

        // Function uses mtrace, turn on buffering to silence output.
        ob_start();
        enrol_sirh_validate_users($users, $instance, SIRH_NOTIFICATION_TYPE_MTRACE, $preview, $errors, $warnings);

        self::assertCount(1, $preview);
        self::assertArrayHasKey('list', $preview);
        self::assertCount(4, $preview['list']);
        self::assertEquals($preview['list'], array_slice($users, 0, 4));

        self::assertNull($errors);
        self::assertNull($warnings);

        // Get mtrace output buffering.
        $output = ob_get_contents();
        self::assertEmpty($output);

        // Turn off output buffering.
        ob_end_clean();

        self::resetAllData();
    }

    /**
     * Test enrol_sirh_validate_users function
     * name special character error
     *
     * @covers ::enrol_sirh_validate_users
     */
    public function test_enrol_sirh_validate_users_nok_name_special_caracter() {
        $this->resetAfterTest(true);
        self::setAdminUser();

        $instance = $this->get_false_instance_data();
        $users = $this->get_default_users_data();

        $specialuser = new \stdClass();
        $specialuser->lastname = 'lastname#';
        $specialuser->firstname = 'firstname#';
        $specialuser->email = 'email4@mail.fr';
        $specialuser->username = 'email4@mail.fr';
        $specialuser->mnethostid = 1;
        $specialuser->confirmed = 1;

        $users[] = $specialuser;

        enrol_sirh_validate_users($users, $instance, SIRH_NOTIFICATION_TYPE_MESSAGE, $preview, $errors, $warnings);

        self::assertCount(1, $preview);
        self::assertArrayHasKey('list', $preview);
        self::assertCount(3, $preview['list']);
        self::assertEquals($preview['list'], array_slice($users, 0, 3));

        self::assertCount(1, $errors);
        self::assertArrayHasKey('list', $errors);
        self::assertCount(1, $errors['list']);
        self::assertEquals(get_string('error_specials_chars', 'enrol_sirh', 'email4@mail.fr'), $errors['list'][0][0]);

        self::assertNull($warnings);

        unset($preview);
        unset($errors);
        unset($warnings);

        // Function uses mtrace, turn on buffering to silence output.
        ob_start();
        enrol_sirh_validate_users($users, $instance, SIRH_NOTIFICATION_TYPE_MTRACE, $preview, $errors, $warnings);

        self::assertCount(1, $preview);
        self::assertArrayHasKey('list', $preview);
        self::assertCount(3, $preview['list']);
        self::assertEquals($preview['list'], array_slice($users, 0, 3));

        self::assertNull($errors);
        self::assertNull($warnings);

        // Get mtrace output buffering.
        $output = ob_get_contents();
        self::assertNotEmpty($output);
        self::assertEquals(get_string(
                               'error_task_specials_chars',
                               'enrol_sirh',
                               [
                                   'sirh' => 'SIRH',
                                   'trainingsirh' => 'SIRH_TRAINING',
                                   'sessionsirh' => 'SIRH_SESSION',
                                   'useremail' => 'email4@mail.fr',
                               ]
                           ) . "\n", $output);

        // Turn off output buffering.
        ob_end_clean();

        self::resetAllData();
    }

    /**
     * Test enrol_sirh_validate_users function
     * email not valid error
     *
     * @covers ::enrol_sirh_validate_users
     */
    public function test_enrol_sirh_validate_users_nok_email_not_valid() {
        $this->resetAfterTest(true);
        self::setAdminUser();

        $instance = $this->get_false_instance_data();
        $users = $this->get_default_users_data();

        $specialuser = new \stdClass();
        $specialuser->lastname = 'lastname4';
        $specialuser->firstname = 'firstname4';
        $specialuser->email = 'emailmail.fr';
        $specialuser->username = 'emailmail.fr';
        $specialuser->mnethostid = 1;
        $specialuser->confirmed = 1;

        $users[] = $specialuser;

        enrol_sirh_validate_users($users, $instance, SIRH_NOTIFICATION_TYPE_MESSAGE, $preview, $errors, $warnings);

        self::assertCount(1, $preview);
        self::assertArrayHasKey('list', $preview);
        self::assertCount(3, $preview['list']);
        self::assertEquals($preview['list'], array_slice($users, 0, 3));

        self::assertCount(1, $errors);
        self::assertArrayHasKey('list', $errors);
        self::assertCount(1, $errors['list']);
        self::assertEquals(get_string('error_email_not_valid', 'enrol_sirh', 'emailmail.fr'), $errors['list'][0][0]);

        self::assertNull($warnings);

        unset($preview);
        unset($errors);
        unset($warnings);

        // Function uses mtrace, turn on buffering to silence output.
        ob_start();
        enrol_sirh_validate_users($users, $instance, SIRH_NOTIFICATION_TYPE_MTRACE, $preview, $errors, $warnings);

        self::assertCount(1, $preview);
        self::assertArrayHasKey('list', $preview);
        self::assertCount(3, $preview['list']);
        self::assertEquals($preview['list'], array_slice($users, 0, 3));

        self::assertNull($errors);
        self::assertNull($warnings);

        // Get mtrace output buffering.
        $output = ob_get_contents();
        self::assertNotEmpty($output);
        self::assertEquals(get_string(
                               'error_task_email_not_valid',
                               'enrol_sirh',
                               [
                                   'sirh' => 'SIRH',
                                   'trainingsirh' => 'SIRH_TRAINING',
                                   'sessionsirh' => 'SIRH_SESSION',
                                   'useremail' => 'emailmail.fr',
                               ]
                           ) . "\n", $output);

        // Turn off output buffering.
        ob_end_clean();

        self::resetAllData();
    }

    /**
     * Test enrol_sirh_validate_users function
     * user suspended
     *
     * @covers ::enrol_sirh_validate_users
     */
    public function test_enrol_sirh_validate_users_nok_user_suspended() {
        $this->resetAfterTest(true);
        self::setAdminUser();
        testhelper::create_default_entity($this);

        $instance = $this->get_false_instance_data();
        $users = $this->get_default_users_data();

        // Create suspended user.
        $suspendeduser = self::getDataGenerator()->create_user([
            'lastname' => 'lastname4',
            'firstname' => 'firstname4',
            'email' => 'email4@mail.fr',
            'username' => 'email4@mail.fr',
            'mnethostid' => 1,
            'confirmed' => 1,
            'suspended' => 1,
        ]);

        $specialuser = new \stdClass();
        $specialuser->lastname = 'lastname4';
        $specialuser->firstname = 'firstname4';
        $specialuser->email = 'email4@mail.fr';
        $specialuser->username = 'email4@mail.fr';
        $specialuser->mnethostid = 1;
        $specialuser->confirmed = 1;

        $users[] = $specialuser;

        enrol_sirh_validate_users($users, $instance, SIRH_NOTIFICATION_TYPE_MESSAGE, $preview, $errors, $warnings);

        self::assertCount(2, $preview);
        self::assertArrayHasKey('list', $preview);
        self::assertCount(4, $preview['list']);
        self::assertEquals($preview['list'], $users);
        self::assertArrayHasKey('validforreactivation', $preview);
        self::assertArrayHasKey('email4@mail.fr', $preview['validforreactivation']);
        self::assertEquals($preview['validforreactivation']['email4@mail.fr']->id, $suspendeduser->id);
        self::assertEquals($preview['validforreactivation']['email4@mail.fr']->suspended, '1');
        self::assertEquals($preview['validforreactivation']['email4@mail.fr']->email, 'email4@mail.fr');

        self::assertNull($errors);

        self::assertCount(1, $warnings);
        self::assertArrayHasKey('list', $warnings);
        self::assertCount(1, $warnings['list']);
        self::assertEquals(get_string('warning_unsuspend_user', 'enrol_sirh', 'email4@mail.fr'), $warnings['list'][0][0]);

        unset($preview);
        unset($errors);
        unset($warnings);

        // Function uses mtrace, turn on buffering to silence output.
        ob_start();
        enrol_sirh_validate_users($users, $instance, SIRH_NOTIFICATION_TYPE_MTRACE, $preview, $errors, $warnings);

        self::assertCount(2, $preview);
        self::assertArrayHasKey('list', $preview);
        self::assertCount(4, $preview['list']);
        self::assertEquals($preview['list'], $users);
        self::assertArrayHasKey('validforreactivation', $preview);
        self::assertArrayHasKey('email4@mail.fr', $preview['validforreactivation']);
        self::assertEquals($preview['validforreactivation']['email4@mail.fr']->id, $suspendeduser->id);
        self::assertEquals($preview['validforreactivation']['email4@mail.fr']->suspended, '1');
        self::assertEquals($preview['validforreactivation']['email4@mail.fr']->email, 'email4@mail.fr');

        self::assertNull($errors);
        self::assertNull($warnings);

        // Get mtrace output buffering.
        $output = ob_get_contents();
        self::assertEmpty($output);
        // Turn off output buffering.
        ob_end_clean();

        self::resetAllData();
    }

    /**
     * Test enrol_sirh_validate_users function
     * higher role
     *
     * @covers ::enrol_sirh_validate_users
     */
    public function test_enrol_sirh_validate_users_nok_higher_role() {
        $this->resetAfterTest(true);
        self::setAdminUser();
        testhelper::create_default_entity($this);

        $instance = $this->get_false_instance_data();
        $users = $this->get_default_users_data();

        // Create suspended user.
        $user = self::getDataGenerator()->create_user([
            'lastname' => 'lastname4',
            'firstname' => 'firstname4',
            'email' => 'email4@mail.fr',
            'username' => 'email4@mail.fr',
            'mnethostid' => 1,
            'confirmed' => 1,
        ]);

        self::getDataGenerator()->enrol_user($user->id, $instance->courseid, 'concepteur');

        $specialuser = new \stdClass();
        $specialuser->lastname = 'lastname4';
        $specialuser->firstname = 'firstname4';
        $specialuser->email = 'email4@mail.fr';
        $specialuser->username = 'email4@mail.fr';
        $specialuser->mnethostid = 1;
        $specialuser->confirmed = 1;

        $users[] = $specialuser;

        enrol_sirh_validate_users($users, $instance, SIRH_NOTIFICATION_TYPE_MESSAGE, $preview, $errors, $warnings);

        self::assertCount(1, $preview);
        self::assertArrayHasKey('list', $preview);
        self::assertCount(3, $preview['list']);
        self::assertEquals($preview['list'], array_slice($users, 0, 3));

        self::assertCount(1, $errors);
        self::assertArrayHasKey('list', $errors);
        self::assertCount(1, $errors['list']);
        self::assertEquals(
            get_string('error_user_role', 'enrol_sirh', ['mail' => 'email4@mail.fr', 'role' => 'Concepteur']),
            $errors['list'][0][0]
        );

        self::assertNull($warnings);

        unset($preview);
        unset($errors);
        unset($warnings);

        // Function uses mtrace, turn on buffering to silence output.
        ob_start();
        enrol_sirh_validate_users($users, $instance, SIRH_NOTIFICATION_TYPE_MTRACE, $preview, $errors, $warnings);

        self::assertCount(1, $preview);
        self::assertArrayHasKey('list', $preview);
        self::assertCount(3, $preview['list']);
        self::assertEquals($preview['list'], array_slice($users, 0, 3));

        self::assertNull($errors);
        self::assertNull($warnings);

        // Get mtrace output buffering.
        $output = ob_get_contents();
        self::assertEquals(
            $output,
            get_string('error_task_user_role', 'enrol_sirh', ['mail' => 'email4@mail.fr', 'role' => 'Concepteur']) . "\n"
        );
        // Turn off output buffering.
        ob_end_clean();

        self::resetAllData();
    }

    /**
     * Test enrol_sirh_validate_users function
     * new role
     *
     * @covers ::enrol_sirh_validate_users
     */
    public function test_enrol_sirh_validate_users_nok_new_role() {
        $this->resetAfterTest(true);
        self::setAdminUser();
        testhelper::create_default_entity($this);

        $instance = $this->get_false_instance_data();
        $users = $this->get_default_users_data();

        // Create suspended user.
        $user = self::getDataGenerator()->create_user([
            'lastname' => 'lastname4',
            'firstname' => 'firstname4',
            'email' => 'email4@mail.fr',
            'username' => 'email4@mail.fr',
            'mnethostid' => 1,
            'confirmed' => 1,
        ]);

        self::getDataGenerator()->enrol_user($user->id, $instance->courseid, 'participantnonediteur');

        $specialuser = new \stdClass();
        $specialuser->lastname = 'lastname4';
        $specialuser->firstname = 'firstname4';
        $specialuser->email = 'email4@mail.fr';
        $specialuser->username = 'email4@mail.fr';
        $specialuser->mnethostid = 1;
        $specialuser->confirmed = 1;

        $users[] = $specialuser;

        enrol_sirh_validate_users($users, $instance, SIRH_NOTIFICATION_TYPE_MESSAGE, $preview, $errors, $warnings);

        self::assertCount(1, $preview);
        self::assertArrayHasKey('list', $preview);
        self::assertCount(4, $preview['list']);
        self::assertEquals($preview['list'], $users);

        self::assertNull($errors);

        self::assertCount(1, $warnings);
        self::assertArrayHasKey('list', $warnings);
        self::assertCount(1, $warnings['list']);
        self::assertEquals(
            get_string(
                'warning_user_role',
                'enrol_sirh',
                ['mail' => 'email4@mail.fr', 'oldrole' => 'Participant non contributeur', 'newrole' => 'Participant']
            ),
            $warnings['list'][0][0]
        );

        unset($preview);
        unset($errors);
        unset($warnings);

        // Function uses mtrace, turn on buffering to silence output.
        ob_start();
        enrol_sirh_validate_users($users, $instance, SIRH_NOTIFICATION_TYPE_MTRACE, $preview, $errors, $warnings);

        self::assertCount(1, $preview);
        self::assertArrayHasKey('list', $preview);
        self::assertCount(4, $preview['list']);
        self::assertEquals($preview['list'], $users);

        self::assertNull($errors);
        self::assertNull($warnings);

        // Get mtrace output buffering.
        $output = ob_get_contents();
        self::assertEmpty($output);
        // Turn off output buffering.
        ob_end_clean();

        self::resetAllData();
    }

    /**
     * Test enrol_sirh_html_table_renderer_users_session function
     * new role
     *
     * @covers ::enrol_sirh_html_table_renderer_users_session
     */
    public function test_enrol_sirh_html_table_renderer_users_session() {
        $this->resetAfterTest(true);
        self::setAdminUser();

        $users = $this->get_default_users_data(6);

        $htmltable = enrol_sirh_html_table_renderer_users_session($users);

        // Check table created.
        self::assertStringContainsString(
            '<table class="generaltable user-hidden" id="user-session-table">',
            $htmltable
        );

        // Check header table.
        self::assertStringContainsString(
            '<th class="header c0" style="" scope="col">Nom</th>',
            $htmltable
        );
        self::assertStringContainsString(
            '<th class="header c1" style="" scope="col">Prénom</th>',
            $htmltable
        );
        self::assertStringContainsString(
            '<th class="header c2 lastcol" style="" scope="col">Adresse de couriel</th>',
            $htmltable
        );

        foreach ($users as $user) {
            self::assertStringContainsString(
                '<td class="cell c0" style="">' . $user->lastname . '</td>',
                $htmltable
            );
            self::assertStringContainsString(
                '<td class="cell c1" style="">' . $user->firstname . '</td>',
                $htmltable
            );
            self::assertStringContainsString(
                '<td class="cell c2 lastcol" style="">' . $user->email . '</td>',
                $htmltable
            );
        }

        // Show more button exist.
        self::assertStringContainsString(
            '<button id="table-read-more" class="btn btn-link">Lire la suite</button>',
            $htmltable
        );

        self::resetAllData();
    }
}
