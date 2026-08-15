<?php
// This file is part of Moodle - http://moodle.org/

namespace local_block_bulk_setup\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/** @package local_block_bulk_setup */
class courses_form extends \moodleform {
    /** @inheritDoc */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('select', 'targetmode', get_string('targetmode', 'local_block_bulk_setup'), [
            'all' => get_string('targetall', 'local_block_bulk_setup'),
            'include' => get_string('targetinclude', 'local_block_bulk_setup'),
            'exclude' => get_string('targetexclude', 'local_block_bulk_setup'),
        ]);
        $mform->setDefault('targetmode', 'all');
        $mform->addElement('select', 'selectedcourseids', get_string('courses', 'local_block_bulk_setup'),
            $this->_customdata['courses'], ['multiple' => 'multiple', 'size' => 20]);
        $mform->addHelpButton('selectedcourseids', 'courses', 'local_block_bulk_setup');
        $mform->hideIf('selectedcourseids', 'targetmode', 'eq', 'all');
        $this->add_action_buttons(false, get_string('next', 'local_block_bulk_setup'));
    }
}
