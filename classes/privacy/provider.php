<?php
// This file is part of Moodle - http://moodle.org/

namespace local_block_bulk_setup\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy metadata provider.
 *
 * @package    local_block_bulk_setup
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider {
    /**
     * Returns the metadata stored by this plugin.
     *
     * @param \core_privacy\local\metadata\collection $collection
     * @return \core_privacy\local\metadata\collection
     */
    public static function get_metadata(\core_privacy\local\metadata\collection $collection): \core_privacy\local\metadata\collection {
        $collection->add_database_table('local_block_bulk_setup_runs', ['userid'], 'privacy:metadata:run');
        return $collection;
    }
}
