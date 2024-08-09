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
 * Local stuff for sirh enrolment plugin.
 *
 * @package    enrol_sirh
 * @copyright  2010 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/enrol/locallib.php');

define('SIRH_NOTIFICATION_TYPE_MESSAGE', 0);
define('SIRH_NOTIFICATION_TYPE_MTRACE', 1);

/**
 * Get the list of sirh
 *
 * @return array code => name
 * @throws dml_exception
 */
function enrol_sirh_get_sirh_list() {
    $sirhlist = get_config('enrol_sirh', 'sirhlist');

    if (empty($sirhlist)) {
        return [];
    }

    $sirhlist = explode("\n", $sirhlist);

    $finallist = [];

    foreach ($sirhlist as $sirh) {
        $split = explode('|', $sirh);

        if (count($split) == 2) {
            $finallist[$split[0]] = $split[0];
        }
    }

    return $finallist;
}

/**
 * Check if enrol_sirh plugin is enabled
 *
 * @return bool
 * @throws dml_exception
 */
function enrol_sirh_plugin_is_enabled() {
    $enrolplugins = get_config('moodle', 'enrol_plugins_enabled');
    return strpos($enrolplugins, 'sirh') !== false;
}

/**
 * Validate the users session SIRH.
 * Builds the preview and errors tables, if provided.
 *
 * @param array $content User session SIRH
 * @param stdClass $instance
 * @param int $notificationtype
 * @param array $preview
 * @param array $errors
 * @param array $warnings
 * @return void
 * @throws coding_exception
 * @throws dml_exception
 */
function enrol_sirh_validate_users($content, $instance, $notificationtype = SIRH_NOTIFICATION_TYPE_MESSAGE, &$preview = [],
    &$errors = [], &$warnings = []) {
    global $DB;

    $courseid = $instance->courseid;

    if (!isset($preview['list'])) {
        $preview['list'] = [];
    }

    $allowedroles = \local_mentor_core\session_api::get_all_roles($courseid);
    $defaultrole = $allowedroles['participant']->localname;
    $definedrole = $allowedroles['participant'];

    // Fields pattern.
    $pattern = '/[\/~`\!@#\$%\^&\*\(\)_\+=\{\}\[\]\|;:"\<\>,\.\?\\\]/';
    $emailpattern = '/[\(\)<>";:\\,\[\]]/';

    // Check entries.
    foreach ($content as $user) {
        $rolename = $defaultrole;
        $ignoreline = false;

        if (1 === preg_match($pattern, $user->firstname . $user->lastname) ||
            1 === preg_match($emailpattern, $user->email)) {

            if ($notificationtype === SIRH_NOTIFICATION_TYPE_MESSAGE) {
                $errors['list'][] = [
                    get_string('error_specials_chars', 'enrol_sirh', $user->email),
                ];
            }

            if ($notificationtype === SIRH_NOTIFICATION_TYPE_MTRACE) {
                mtrace(get_string(
                    'error_task_specials_chars',
                    'enrol_sirh',
                    [
                        'sirh' => $instance->customchar1,
                        'trainingsirh' => $instance->customchar2,
                        'sessionsirh' => $instance->customchar3,
                        'useremail' => $user->email,
                    ]
                ));
            }

            $ignoreline = true;
        }

        // Check if email is valid.
        if (false === filter_var($user->email, FILTER_VALIDATE_EMAIL)) {

            if ($notificationtype === SIRH_NOTIFICATION_TYPE_MESSAGE) {
                $errors['list'][] = [
                    get_string('error_email_not_valid', 'enrol_sirh', $user->email),
                ];
            }

            if ($notificationtype === SIRH_NOTIFICATION_TYPE_MTRACE) {
                mtrace(get_string(
                    'error_task_email_not_valid',
                    'enrol_sirh',
                    [
                        'sirh' => $instance->customchar1,
                        'trainingsirh' => $instance->customchar2,
                        'sessionsirh' => $instance->customchar3,
                        'useremail' => $user->email,
                    ]
                ));
            }

            $ignoreline = true;
        }

        $users = $DB->get_records_sql("
                    SELECT id, email, username, suspended FROM {user} WHERE email = :email OR email = :emailstrtolower
                ", ['email' => $user->email, 'emailstrtolower' => strtolower($user->email)]);

        // If the user exists, check if an other user as an email equals to the username.
        if (count($users) == 1) {

            $u = array_shift($users);

            $users = $DB->get_records_sql("
                    SELECT id, suspended, email FROM {user} WHERE email = :email OR email = :emailstrtolower
                ", ['email' => $user->email, 'emailstrtolower' => strtolower($user->email)]);

            $u = current($users);

            if ($u->suspended == 1) {
                $preview['validforreactivation'][$user->email] = $u;

                if ($notificationtype === SIRH_NOTIFICATION_TYPE_MESSAGE) {
                    $warnings['list'][] = [
                        get_string('warning_unsuspend_user', 'enrol_sirh', $user->email),
                    ];
                }
            }

            $oldroles = \local_mentor_core\profile_api::get_course_roles($u->id, $courseid);

            // User is enrolled.
            if (!empty($oldroles) && !isset($oldroles[$definedrole->id])) {

                $strparams = new stdClass();
                $strparams->newrole = $rolename;
                $strparams->oldroles = '';
                foreach ($oldroles as $oldrole) {
                    $strparams->oldroles .= $allowedroles[$oldrole->shortname]->localname . ',';
                }
                $strparams->oldroles = substr($strparams->oldroles, 0, -1);

                // If the local user is a trainer.
                // he/she cannot lower his/her privileges as a participant.
                // Else, the role can be changed for him and the other users.
                if ($strparams->oldroles === $allowedroles['concepteur']->localname ||
                    $strparams->oldroles === $allowedroles['tuteur']->localname ||
                    $strparams->oldroles === $allowedroles['formateur']->localname) {

                    if ($notificationtype === SIRH_NOTIFICATION_TYPE_MESSAGE) {
                        $errors['list'][] = [
                            get_string(
                                'error_user_role',
                                'enrol_sirh',
                                ['mail' => $u->email, 'role' => $strparams->oldroles],
                            ),
                        ];
                    }

                    if ($notificationtype === SIRH_NOTIFICATION_TYPE_MTRACE) {
                        mtrace(get_string(
                            'error_task_user_role',
                            'enrol_sirh',
                            ['mail' => $u->email, 'role' => $strparams->oldroles]
                        ));
                    }

                    $ignoreline = true;
                } else {
                    if ($notificationtype === SIRH_NOTIFICATION_TYPE_MESSAGE) {
                        $warnings['list'][] = [
                            get_string(
                                'warning_user_role',
                                'enrol_sirh',
                                [
                                    'mail' => $u->email, 'oldrole' => $strparams->oldroles,
                                    'newrole' => $strparams->newrole,
                                ]
                            ),
                        ];
                    }
                }
            }
        }

        if (
            isset($preview['validforcreation']) &&
            false === $ignoreline && count($users) === 0 &&
            !isset($preview['validforreactivation'][$user->email])
        ) {
            $preview['validforcreation']++;
        }

        // Add the valid lines to the preview list.
        if (false === $ignoreline) {
            if (isset($preview['validlines'])) {
                $preview['validlines']++;
            }

            $user->role = $defaultrole;

            $preview['list'][] = $user;
        }
    }
}

/**
 * Get table renderer for users session.
 *
 * @param array $userssession
 * @return string
 * @throws coding_exception
 */
function enrol_sirh_html_table_renderer_users_session($userssession) {
    $out = html_writer::start_tag('div', ['id' => 'table-div']);

    // Set data table.
    $usersessiontable = [];
    foreach ($userssession as $usersession) {
        $userdata = new \stdClass();
        $userdata->lastname = $usersession->lastname;
        $userdata->firstname = $usersession->firstname;
        $userdata->email = $usersession->email;
        $usersessiontable[] = $userdata;
    }

    // Create table with data and setting.
    $userssessiontable = new html_table();
    $userssessiontable->head = ['Nom', 'Prénom', 'Adresse de couriel'];
    $userssessiontable->data = $usersessiontable;
    $userssessiontable->id = 'user-session-table';
    $userssessiontable->attributes['class'] = 'generaltable user-hidden';
    $out .= html_writer::table($userssessiontable);

    if (count($userssession) > 5) {
        $out .= html_writer::tag('div', '', ['id' => 'gradientmask']);
        // Add show/less view button.
        $out .= html_writer::tag('button', get_string('showmore', 'enrol_sirh'),
            ['id' => 'table-read-more', 'class' => 'btn btn-link']);
    }

    $out .= html_writer::end_tag('div');

    return $out;
}
