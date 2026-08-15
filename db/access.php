<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/block_bulk_setup:manage' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
        'riskbitmask' => RISK_CONFIG | RISK_DATALOSS,
    ],
];
