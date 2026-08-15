<?php
// This file is part of Moodle - http://moodle.org/

namespace local_block_bulk_setup\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Applies one bulk block placement run without holding an administrator's web request open.
 *
 * @package    local_block_bulk_setup
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class apply_run extends \core\task\adhoc_task {
    /**
     * Queues a task for a run.
     *
     * @param int $runid
     * @return void
     */
    public static function queue(int $runid): void {
        $task = new self();
        $task->set_custom_data((object)['runid' => $runid]);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Gets the task name displayed in Moodle task administration.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskname', 'local_block_bulk_setup');
    }

    /**
     * Executes the run.
     *
     * @return void
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        \local_block_bulk_setup\local\service::apply_run((int)$data->runid);
    }
}
