<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_block_bulk_setup\form\blocks_form;
use local_block_bulk_setup\form\categories_form;
use local_block_bulk_setup\form\courses_form;
use local_block_bulk_setup\local\service;

require_login();
$systemcontext = context_system::instance();
require_capability('local/block_bulk_setup:manage', $systemcontext);

$url = new moodle_url('/local/block_bulk_setup/index.php');
$PAGE->set_url($url);
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pluginname', 'local_block_bulk_setup'));
$PAGE->set_heading(get_string('pluginname', 'local_block_bulk_setup'));

if (optional_param('reset', 0, PARAM_BOOL)) {
    unset($SESSION->local_block_bulk_setup_wizard);
    redirect($url);
}

$wizard = $SESSION->local_block_bulk_setup_wizard ?? [];
$step = optional_param('step', 'categories', PARAM_ALPHA);
$allowedsteps = ['categories', 'courses', 'blocks', 'preview'];
if (!in_array($step, $allowedsteps, true)) {
    $step = 'categories';
}

if ($step === 'courses' && empty($wizard['categoryids'])) {
    redirect($url);
}
if ($step === 'blocks' && empty($wizard['courseids'])) {
    redirect($url);
}
if ($step === 'preview' && (empty($wizard['courseids']) || empty($wizard['blocknames']))) {
    redirect($url);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('heading', 'local_block_bulk_setup'));
echo $OUTPUT->box(get_string('intro', 'local_block_bulk_setup'), 'generalbox');
echo html_writer::div(
    html_writer::link(new moodle_url('/local/block_bulk_setup/runs.php'), get_string('runhistory', 'local_block_bulk_setup')) .
    ' | ' . html_writer::link(new moodle_url($url, ['reset' => 1]), get_string('reset', 'moodle')),
    'mb-3'
);

if ($step === 'categories') {
    echo $OUTPUT->heading(get_string('stepcategories', 'local_block_bulk_setup'), 3);
    $form = new categories_form(new moodle_url($url, ['step' => 'categories']), ['categories' => service::category_options()]);
    $form->set_data(['categoryids' => $wizard['categoryids'] ?? []]);
    if ($data = $form->get_data()) {
        $wizard = ['categoryids' => array_values(array_map('intval', $data->categoryids))];
        $SESSION->local_block_bulk_setup_wizard = $wizard;
        redirect(new moodle_url($url, ['step' => 'courses']));
    }
    $form->display();
}

if ($step === 'courses') {
    $candidates = service::courses_for_categories($wizard['categoryids']);
    if (!$candidates) {
        echo $OUTPUT->notification(get_string('nocourses', 'local_block_bulk_setup'), 'notifyproblem');
        echo $OUTPUT->continue_button($url);
    } else {
        $options = [];
        foreach ($candidates as $course) {
            $options[$course->id] = service::course_label($course);
        }
        echo $OUTPUT->heading(get_string('stepcourses', 'local_block_bulk_setup'), 3);
        $form = new courses_form(new moodle_url($url, ['step' => 'courses']), ['courses' => $options]);
        $form->set_data([
            'targetmode' => $wizard['targetmode'] ?? 'all',
            'selectedcourseids' => $wizard['selectedcourseids'] ?? [],
        ]);
        if ($data = $form->get_data()) {
            $selected = array_values(array_intersect(array_map('intval', $data->selectedcourseids ?? []), array_keys($candidates)));
            if ($data->targetmode === 'include') {
                $courseids = $selected;
            } else if ($data->targetmode === 'exclude') {
                $courseids = array_values(array_diff(array_keys($candidates), $selected));
            } else {
                $courseids = array_keys($candidates);
            }
            $wizard['targetmode'] = $data->targetmode;
            $wizard['selectedcourseids'] = $selected;
            $wizard['courseids'] = array_values($courseids);
            $SESSION->local_block_bulk_setup_wizard = $wizard;
            redirect(new moodle_url($url, ['step' => 'blocks']));
        }
        $form->display();
    }
}

if ($step === 'blocks') {
    echo $OUTPUT->heading(get_string('stepblocks', 'local_block_bulk_setup'), 3);
    $form = new blocks_form(new moodle_url($url, ['step' => 'blocks']), ['blocks' => service::block_options()]);
    $form->set_data([
        'blocknames' => $wizard['blocknames'] ?? [],
        'duplicatepolicy' => $wizard['duplicatepolicy'] ?? 'skip',
    ]);
    if ($data = $form->get_data()) {
        $wizard['blocknames'] = array_values(array_map(
            static fn(string $blockname): string => clean_param($blockname, PARAM_PLUGIN),
            $data->blocknames
        ));
        $wizard['duplicatepolicy'] = $data->duplicatepolicy === 'add' ? 'add' : 'skip';
        $wizard['region'] = service::DEFAULT_REGION;
        $SESSION->local_block_bulk_setup_wizard = $wizard;
        redirect(new moodle_url($url, ['step' => 'preview']));
    }
    echo $OUTPUT->single_button(new moodle_url($url, ['step' => 'courses']), get_string('back', 'local_block_bulk_setup'), 'get');
    $form->display();
}

if ($step === 'preview') {
    echo $OUTPUT->heading(get_string('steppreview', 'local_block_bulk_setup'), 3);
    echo $OUTPUT->notification(get_string('previewintro', 'local_block_bulk_setup'), 'notifymessage');
    $rows = service::preview($wizard['courseids'], $wizard['blocknames'], $wizard['region'], $wizard['duplicatepolicy']);
    $counts = [];
    foreach ($rows as $row) {
        $counts[$row->action] = ($counts[$row->action] ?? 0) + 1;
    }
    $summary = [];
    foreach ($counts as $action => $count) {
        $summary[] = get_string('result_' . $action, 'local_block_bulk_setup') . ': ' . $count;
    }
    echo $OUTPUT->box(implode(html_writer::empty_tag('br'), $summary), 'generalbox');
    $table = new html_table();
    $table->head = [get_string('course', 'local_block_bulk_setup'), get_string('block', 'local_block_bulk_setup'), get_string('plannedaction', 'local_block_bulk_setup')];
    foreach ($rows as $row) {
        $table->data[] = [s($row->coursename), s($row->blockname), get_string($row->message, 'local_block_bulk_setup')];
    }
    echo html_writer::div(html_writer::table($table), 'table-responsive');
    echo $OUTPUT->single_button(new moodle_url($url, ['step' => 'blocks']), get_string('back', 'local_block_bulk_setup'), 'get');
    echo $OUTPUT->single_button(new moodle_url('/local/block_bulk_setup/queue.php', ['sesskey' => sesskey()]), get_string('queue', 'local_block_bulk_setup'), 'post');
}

echo $OUTPUT->footer();
