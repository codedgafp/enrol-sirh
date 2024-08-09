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
 * Create/update enrol SIRH instance list of users for the given session
 *
 * @package    enrol_sirh
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     Remi Colet <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include config.php.
require_once('../../../config.php');

// Includes.
require_once($CFG->dirroot . '/enrol/sirh/lib.php');
require_once($CFG->dirroot . '/enrol/sirh/locallib.php');
require_once($CFG->dirroot . '/enrol/sirh/classes/api/sirh.php');
require_once($CFG->dirroot . '/enrol/sirh/forms/syncsirh_form.php');
require_once($CFG->dirroot . '/enrol/sirh/forms/syncusers_form.php');
require_once($CFG->dirroot . '/local/mentor_core/api/session.php');

// Require login.
require_login();

// Require param.
$sessionid = required_param('sessionid', PARAM_INT);

// Optinal params.
$instanceid = optional_param('instanceid', null, PARAM_INT);

$data = [
    'instanceid' => $instanceid,
    'users' => [],
    'userssync' => [],
    'addtogroup' => \syncsirh_form::ADD_TO_NO_GROUP,
];

if (is_null($instanceid)) {
    // Other require param.
    $data['sirh'] = required_param('sirh', PARAM_RAW);
    $data['sirhtraining'] = required_param('sirhtraining', PARAM_RAW);
    $data['sirhsession'] = required_param('sirhsession', PARAM_RAW);
} else {

    $instance = enrol_sirh_external::get_instance_info($instanceid);

    // Other require param.
    $data['sirh'] = $instance['customchar1'];
    $data['sirhtraining'] = $instance['customchar2'];
    $data['sirhsession'] = $instance['customchar3'];

    if (!is_null($instance['customint1'])) {
        $data['groupid'] = intval($instance['customint1']) === 0 ?
            \syncsirh_form::ADD_TO_NO_GROUP :
            $instance['customint1'];
    }
}

// Check if plugin is enabled.
if (!enrol_sirh_plugin_is_enabled()) {
    throw new \moodle_exception('Plugin enrol_sirh is disabled');
}

// Set session data.
$session = \local_mentor_core\session_api::get_session($sessionid);
$sessioncourse = $session->get_course();
$coursecontext = $session->get_context();
$sessiongroup = $session->get_all_group();
$sessiondgroupdata = [];
foreach ($sessiongroup as $group) {
    $sessiondgroupdata[$group->id] = $group->name;
}
$data['sessiongroup'] = $sessiondgroupdata;

// Check session management capabilities.
if (!$session->get_entity()->is_sessions_manager()) {
    throw new \moodle_exception('Permission denied');
}

// Check enrolment capabitities.
\enrol_sirh\sirh_api::check_enrol_sirh_capability($coursecontext->id);

$url = new moodle_url('/enrol/sirh/pages/sync.php', [
    'sessionid' => $sessionid,
]);

$title = $session->fullname . ' - ' . get_string('syncsirh', 'enrol_sirh');

// Settings first element page.
$PAGE->set_url($url);
$PAGE->set_context($coursecontext);
$PAGE->set_title($session->fullname . ' - ' . get_string('syncsirh', 'enrol_sirh'));
$PAGE->set_heading($session->fullname . ' - ' . get_string('syncsirh', 'enrol_sirh'));

$managesessionsurl = $session->get_entity()->get_main_entity()->get_edadmin_courses('session')['link'];

$PAGE->navbar->add($session->get_entity()->get_name() . ' - Gestion des sessions', $managesessionsurl);
$PAGE->navbar->add($session->fullname . ' - Sessions SIRH', new moodle_url('/enrol/sirh/pages/index.php', [
    'sessionid' => $sessionid,
]));
$PAGE->navbar->add($title);

$params = [
    'modaltitle' => get_string('sirh_modal_title', 'enrol_sirh'),
    'modalcontent' => get_string('sirh_modal_content', 'enrol_sirh'),
];

// Require JS.
$PAGE->requires->js_call_amd('enrol_sirh/sync_sirh', 'init', $params);
$PAGE->requires->strings_for_js(['showmore', 'showless'], 'enrol_sirh');

// Output content.
$out = '';

// Anchor directly to the sync user report.
$anchorurl = new moodle_url('/enrol/sirh/pages/sync.php', [
    'sessionid' => $sessionid,
], 'sirh-sync-user');

// Sync form.
$syncform = new syncsirh_form($data, $anchorurl->out(false), []);
$syncformdata = $syncform->get_data();

// Sync users form.
$syncusersform = new syncusers_form($data,
    new moodle_url('/enrol/sirh/pages/sync.php', ['sessionid' => $sessionid]));
$syncusersformdata = $syncusersform->get_data();

// Sync users with validated data.
if (null !== $syncusersformdata) {

    $userssync = json_decode($syncusersformdata->userssync);

    $instance = \enrol_sirh\sirh_api::get_or_create_enrol_sirh_instance(
        $sessioncourse->id,
        $data['sirh'],
        $data['sirhtraining'],
        $data['sirhsession']
    );

    if (\enrol_sirh\sirh_api::synchronize_users($instance, $userssync)) {
        if ($syncusersformdata->addtogroup === \syncsirh_form::ADD_TO_NO_GROUP) {
            // No group for this SIRH instance.
            \enrol_sirh\sirh_api::set_group_sirh($instance->id, \syncsirh_form::ADD_TO_NO_GROUP);
        } else {
            $groupid = $syncusersformdata->addtogroup;

            if ($syncusersformdata->addtogroup === \syncsirh_form::ADD_TO_NEW_GROUP) {
                // Create groupe with SIRH instance name.
                $groupid = \enrol_sirh\sirh_api::create_group_sirh($instance);
            }

            // Set new groupe SIRH.
            \enrol_sirh\sirh_api::set_group_sirh($instance->id, $groupid);
        }

        \enrol_sirh\sirh_api::update_sirh_instance_sync_data($instance);
    }

    redirect(
        $managesessionsurl,
        get_string('notification_sirh_success', 'enrol_sirh'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('syncsirhtitle', 'enrol_sirh', [
    'sirh' => $data['sirh'],
    'sirhtraining' => $data['sirhtraining'],
    'sirhsession' => $data['sirhsession'],
]));

if (null !== $syncformdata) {

    $usersession = json_decode($syncformdata->users);
    $out .= enrol_sirh_html_table_renderer_users_session($usersession);

    $out .= $syncform->render();

    echo html_writer::div($out, 'enrol_sirh_sync', ['id' => 'sirh-session-user']);

    $out = '';

    // Errors array.
    $errors = [
        'list' => [], // Errors list.
    ];

    // Errors array.
    $warnings = [
        'list' => [], // Warning list.
        'groupsnotfound' => [], // List of not found group name.
        'rolenotfound' => [], // List of not found role name.
        'loseprivilege' => [], // List of users who could have lost their privilege.
    ];

    // Preview array.
    $preview = [
        'list' => [], // Cleaned list of accounts.
        'validlines' => 0, // Number of lines without error.
        'validforcreation' => 0, // Number of lines that will create an account.
        'validforreactivation' => [], // Valid accounts for reactivation.
    ];

    $usersessionform = json_decode($syncformdata->users);

    $datainstance = new stdClass();
    $datainstance->courseid = $sessioncourse->id;

    // Build preview and errors array.
    enrol_sirh_validate_users($usersessionform, $datainstance, SIRH_NOTIFICATION_TYPE_MESSAGE, $preview, $errors,
        $warnings);

    // Preview report.
    $out .= html_writer::tag('h5', get_string('preview_report', 'enrol_sirh'), ['class' => 'report-title']);

    if ($syncformdata->addtogroup === \syncsirh_form::ADD_TO_NEW_GROUP) {
        array_unshift($warnings['list'], [
            get_string('warning_create_group', 'enrol_sirh', [
                'sirh' => $data['sirh'],
                'sirhtraining' => $data['sirhtraining'],
                'sirhsession' => $data['sirhsession'],
            ]),
        ]);
    }

    // Display the report.
    $out .= html_writer::alist([
        get_string('identified_users', 'enrol_sirh', $preview['validlines']),
        get_string('account_creation_number', 'enrol_sirh', $preview['validforcreation']),
        get_string('account_reactivation_number', 'enrol_sirh', count($preview['validforreactivation'])),
        get_string('errors_number', 'enrol_sirh', count($errors['list'])),
        get_string('warnings_number', 'enrol_sirh', count($warnings['list'])),
    ],
        [
            'id' => 'report-preview',
            'data-creation-number' => $preview['validforcreation'],
            'data-reactivation-number' => count($preview['validforreactivation']),
        ]
    );

    // Display errors bloc.
    if (count($errors['list']) !== 0) {
        // Errors detected notification.
        \core\notification::warning(get_string('errors_detected', 'enrol_sirh'));

        // Building errors report table.
        $errorstable = new html_table();
        $errorstable->head = [get_string('error')];
        $errorstable->data = $errors['list'];
        $out .= html_writer::table($errorstable);
    }

    // Display warnings bloc.
    if (count($warnings['list']) !== 0) {
        // Errors detected notification.
        \core\notification::warning(get_string('warnings_detected', 'enrol_sirh'));

        // Building errors report table.
        $warningstable = new html_table();
        $warningstable->head = [get_string('warning', 'enrol_sirh')];
        $warningstable->data = $warnings['list'];
        $out .= html_writer::table($warningstable);
    }

    // Check available places.
    $availableplaces = $session->get_available_places();

    if (is_int($availableplaces)) {

        // Get the potential available places after the import.
        $nextavailableplaces = $session->count_potential_available_places($preview['list']);

        // If the next available places is negative, then display a warning message.
        if ($nextavailableplaces < 0) {
            // Display a warning.
            $out .= html_writer::tag('div', $OUTPUT->pix_icon('i/warning', get_string('warning')) .
                                            get_string(
                                                'warning_nbusers',
                                                'enrol_sirh',
                                                $preview['validlines'] - $session->maxparticipants),
                ['id' => 'errors-nbusers']
            );
        }
    }

    $data['userssync'] = $preview['list'];
    $data['addtogroup'] = $syncformdata->addtogroup;

    // Sync users form.
    $syncusersform = new syncusers_form($data,
        new moodle_url('/enrol/sirh/pages/sync.php', ['sessionid' => $sessionid]));

    // Display sync users form.
    $out .= $syncusersform->render();

    echo html_writer::div($out, 'enrol_sirh_sync', ['id' => 'sirh-sync-user']);

} else {
    // Get all user session.
    $userssession = \enrol_sirh\sirh_api::get_session_users($sessionid, $data['sirh'], $data['sirhtraining'], $data['sirhsession']);

    // Get user session table renderer.
    $out .= enrol_sirh_html_table_renderer_users_session($userssession['users']);

    $data['users'] = $userssession['users'];

    $syncform = new syncsirh_form($data, $anchorurl->out(false), []);
    $out .= $syncform->render();

    echo html_writer::div($out, 'enrol_sirh_sync', ['id' => 'sirh-session-user']);
}

echo $OUTPUT->footer();
