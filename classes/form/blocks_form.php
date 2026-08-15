<?php
// This file is part of Moodle - http://moodle.org/

namespace local_block_bulk_setup\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/** @package local_block_bulk_setup */
class blocks_form extends \moodleform {
    /** @inheritDoc */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('static', 'regiondisplay', get_string('region', 'local_block_bulk_setup'),
            get_string('regionvalue', 'local_block_bulk_setup'));
        $mform->addElement('select', 'blocknames', get_string('blocks', 'local_block_bulk_setup'),
            $this->_customdata['blocks'], ['multiple' => 'multiple', 'size' => 18]);
        $mform->addHelpButton('blocknames', 'blocks', 'local_block_bulk_setup');
        $mform->addRule('blocknames', get_string('error:blocksrequired', 'local_block_bulk_setup'), 'required', null, 'client');
        $mform->addElement('select', 'duplicatepolicy', get_string('duplicatepolicy', 'local_block_bulk_setup'), [
            'skip' => get_string('duplicateskip', 'local_block_bulk_setup'),
            'add' => get_string('duplicateadd', 'local_block_bulk_setup'),
        ]);
        $mform->setDefault('duplicatepolicy', 'skip');
        $this->add_action_buttons(false, get_string('next', 'local_block_bulk_setup'));
    }
}
