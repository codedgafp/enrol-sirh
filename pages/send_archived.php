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
 * Send session followup information with archived session.
 *
 * @package    enrol_sirh
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     remi <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once("$CFG->libdir/clilib.php");
require_once("$CFG->libdir/cronlib.php");
require_once("$CFG->dirroot/enrol/sirh/classes/task/send_session_followup_information.php");

// Send session followup information with archived session.
$task = new \enrol_sirh\task\send_session_followup_information([
    \local_mentor_core\session::STATUS_IN_PROGRESS,
    \local_mentor_core\session::STATUS_COMPLETED,
    \local_mentor_core\session::STATUS_ARCHIVED,
]);

if (moodle_needs_upgrading()) {
    mtrace("Moodle upgrade pending, cannot execute tasks.");
    exit(1);
}

\core\task\manager::scheduled_task_starting($task);

// Increase memory limit.
raise_memory_limit(MEMORY_EXTRA);

// Emulate normal session - we use admin account by default.
cron_setup_user();

// Execute the task.
\core\local\cli\shutdown::script_supports_graceful_exit();
$cronlockfactory = \core\lock\lock_config::get_lock_factory('cron');
if (!$cronlock = $cronlockfactory->get_lock('core_cron', 10)) {
    mtrace('Cannot obtain cron lock');
    exit(129);
}
if (!$lock = $cronlockfactory->get_lock('\\' . get_class($task), 10)) {
    $cronlock->release();
    mtrace('Cannot obtain task lock');
    exit(130);
}

$task->set_lock($lock);
if (!$task->is_blocking()) {
    $cronlock->release();
} else {
    $task->set_cron_lock($cronlock);
}

cron_run_inner_scheduled_task($task);
