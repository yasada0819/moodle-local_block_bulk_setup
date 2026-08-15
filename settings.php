<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('courses', new admin_externalpage(
        'local_block_bulk_setup',
        get_string('pluginname', 'local_block_bulk_setup'),
        new moodle_url('/local/block_bulk_setup/index.php'),
        'local/block_bulk_setup:manage'
    ));
}
