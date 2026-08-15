<?php
// This file is part of Moodle - http://moodle.org/

namespace local_block_bulk_setup\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/** @package local_block_bulk_setup */
class categories_form extends \moodleform {
    /** @inheritDoc */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('select', 'categoryids', get_string('categories', 'local_block_bulk_setup'),
            $this->_customdata['categories'], ['multiple' => 'multiple', 'size' => 16]);
        $mform->addHelpButton('categoryids', 'categories', 'local_block_bulk_setup');
        $mform->addRule('categoryids', get_string('error:categoriesrequired', 'local_block_bulk_setup'), 'required', null, 'client');
        $this->add_action_buttons(false, get_string('next', 'local_block_bulk_setup'));
    }
}
