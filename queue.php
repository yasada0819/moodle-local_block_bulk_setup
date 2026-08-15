<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_block_bulk_setup\local\service;

require_login();
$systemcontext = context_system::instance();
require_capability('local/block_bulk_setup:manage', $systemcontext);
require_sesskey();

$wizard = $SESSION->local_block_bulk_setup_wizard ?? [];
if (empty($wizard['categoryids']) || empty($wizard['courseids']) || empty($wizard['blocknames'])) {
    throw new moodle_exception('error:runnotqueueable', 'local_block_bulk_setup');
}

$runid = service::queue_run(
    $USER->id,
    $wizard['categoryids'],
    $wizard['courseids'],
    $wizard['blocknames'],
    $wizard['region'] ?? service::DEFAULT_REGION,
    $wizard['duplicatepolicy'] ?? 'skip'
);
unset($SESSION->local_block_bulk_setup_wizard);
redirect(new moodle_url('/local/block_bulk_setup/runs.php', ['runid' => $runid]), get_string('queued', 'local_block_bulk_setup'));
