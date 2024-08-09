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
 * Index page for enrol_sirh plugin.
 *
 * @package    enrol_sirh
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     remi <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include config.php.
require_once('../../../config.php');
require_once($CFG->dirroot . '/enrol/sirh/locallib.php');
require_once($CFG->dirroot . '/local/mentor_core/api/session.php');
require_once($CFG->dirroot . '/enrol/sirh/classes/api/sirh.php');

// Require login.
require_login();

// Require session id.
$sessionid = required_param('sessionid', PARAM_INT);

// Check if plugin is enabled.
if (!enrol_sirh_plugin_is_enabled()) {
    throw new \moodle_exception('Plugin enrol_sirh is disabled');
}

$session = \local_mentor_core\session_api::get_session($sessionid);
$context = $session->get_context();
$entity = $session->get_entity()->get_main_entity();
$sirhlist = $entity->get_sirh_list();

// Check if entity has configured SIRH.
if (count($sirhlist) == 0) {
    throw new \moodle_exception('No SIRH defined for entity ' . $entity->name);
}

// Check session management capabilities.
if (!$entity->is_sessions_manager()) {
    throw new \moodle_exception('Permission denied');
}

// Check enrolment capabitities.
\enrol_sirh\sirh_api::check_enrol_sirh_capability($context->id);

$title = $session->fullname . ' - Sessions SIRH';

// Settings first element page.
$PAGE->set_url('/enrol/sirh/pages/index.php', ['sessionid' => $sessionid]);
$PAGE->set_context($context);
$PAGE->set_title($title);
$PAGE->set_heading($title);

$managesessionsurl = $entity->get_edadmin_courses('session')['link'];

// Set navbar.
$PAGE->navbar->add($entity->get_name() . ' - Gestion des sessions', $managesessionsurl);
$PAGE->navbar->add($title);

$jsparams = [
    'sessionid' => $session->id,
    'managesessionsurl' => $managesessionsurl,
];

// Require JS.
$PAGE->requires->strings_for_js(
    [
        'langfile',
        'submit',
        'cancel',
        'enrolpopuptitle',
        'confirmmessage',
        'previewusers',
        'instancepluginname',
        'reload',
        'viewenrol',
    ], 'enrol_sirh'
);
$PAGE->requires->js_call_amd('enrol_sirh/enrol_sirh', 'init', $jsparams);

echo $OUTPUT->header();

// Prepare data for templates.
$allsirh = enrol_sirh_get_sirh_list();

// Prepare template data.
$templatedata = new stdClass();
$templatedata->sirhlist = [];
$templatedata->sirhlistmultiple = count($sirhlist) > 1;
$templatedata->defaultstartdate = date('Y-m-d', strtotime('first day of january this year'));
$templatedata->defaultenddate = date('Y-m-d', strtotime('last day of december this year'));

// Format SIRH list.
if ($templatedata->sirhlistmultiple) {
    foreach ($sirhlist as $sirhcode) {
        $templatedata->sirhlist[] = [
            'code' => $sirhcode,
            'name' => $allsirh[$sirhcode],
        ];
    }
} else {
    $templatedata->sirh = $sirhlist[0];
}

// Render templates html.
echo $OUTPUT->render_from_template('enrol_sirh/filters', $templatedata);
echo $OUTPUT->render_from_template('enrol_sirh/sessions', []);

echo $OUTPUT->footer();
