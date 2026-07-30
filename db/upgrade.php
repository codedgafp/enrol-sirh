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
 * Database upgrades for the enrol_sirh.
 *
 * @package   enrol_sirh
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author    Nabil HAMDI <nabil.hamdi@edunao.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

/**
 * Upgrade the enrol_sirh database.
 *
 * @param int $oldversion The version number of the plugin that was installed.
 * @return boolean
 * @throws ddl_exception
 * @throws ddl_table_missing_exception
 * @throws dml_exception
 */
function xmldb_enrol_sirh_upgrade($oldversion) {
    global $CFG, $DB;

    require_once($CFG->libdir . '/db/upgradelib.php'); // Core Upgrade-related functions.

    $dbman = $DB->get_manager();

    if ($oldversion < 2023103100) {
        $sessiontable = new xmldb_table('session');
        if ($dbman->table_exists($sessiontable)) {
            $sessionfield = new xmldb_field('lastsyncsirh', XMLDB_TYPE_INTEGER, '10');
            if (!$dbman->field_exists($sessiontable, $sessionfield)) {
                $dbman->add_field($sessiontable, $sessionfield);
            }
        }

        // Send session followup information with archived session.
        $task = new \enrol_sirh\task\send_session_followup_information([
            \local_mentor_core\session::STATUS_IN_PROGRESS,
            \local_mentor_core\session::STATUS_COMPLETED,
            \local_mentor_core\session::STATUS_ARCHIVED,
        ], false);
        $task->execute();
    }

    return true;
}
