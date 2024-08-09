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
 * SIRH enrolment plugin settings and presets.
 *
 * @package    enrol_sirh
 * @copyright  2010 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    // General settings.
    $settings->add(new admin_setting_heading('enrol_sirh_settings', '', get_string('pluginname_desc', 'enrol_sirh')));

    // Enrol instance defaults.
    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_sirh/roleid',
            get_string('defaultrole', 'role'), '', $student->id ?? null, $options));

        $options = [
            ENROL_EXT_REMOVED_UNENROL => get_string('extremovedunenrol', 'enrol'),
            ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        ];
        $settings->add(new admin_setting_configselect('enrol_sirh/unenrolaction', get_string('extremovedaction', 'enrol'),
            get_string('extremovedaction_help', 'enrol'), ENROL_EXT_REMOVED_UNENROL, $options));

        // Default SIRH list.
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

        $default = '';
        foreach ($sirhlist as $sirhcode => $sirhname) {
            $default .= 'RENOIRH_' . $sirhcode . '|' . $sirhname . "\n";
        }

        $settings->add(new admin_setting_configtextarea(
            'enrol_sirh/sirhlist',
            'Liste des SIRH',
            'Liste des SIRH (1 par ligne)',
            $default,
            PARAM_TEXT
        ));
    }
}
