<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

require_login();
$systemcontext = context_system::instance();
require_capability('local/block_bulk_setup:manage', $systemcontext);

$url = new moodle_url('/local/block_bulk_setup/runs.php');
$PAGE->set_url($url);
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('runhistory', 'local_block_bulk_setup'));
$PAGE->set_heading(get_string('pluginname', 'local_block_bulk_setup'));

$runid = optional_param('runid', 0, PARAM_INT);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('runhistory', 'local_block_bulk_setup'));
echo $OUTPUT->single_button(new moodle_url('/local/block_bulk_setup/index.php'), get_string('heading', 'local_block_bulk_setup'), 'get');

if ($runid) {
    $run = $DB->get_record('local_block_bulk_setup_runs', ['id' => $runid], '*', MUST_EXIST);
    $table = new html_table();
    $table->head = [
        get_string('course', 'local_block_bulk_setup'),
        get_string('block', 'local_block_bulk_setup'),
        get_string('results', 'local_block_bulk_setup'),
        get_string('detail', 'local_block_bulk_setup'),
    ];
    $results = $DB->get_records('local_block_bulk_setup_results', ['runid' => $run->id], 'id ASC');
    foreach ($results as $result) {
        $course = $DB->get_record('course', ['id' => $result->courseid], 'id, fullname', IGNORE_MISSING);
        $message = get_string('result_' . $result->status . '_done', 'local_block_bulk_setup', null, false);
        if ($message === '[[' . 'result_' . $result->status . '_done' . ']]') {
            $message = $result->message;
        }
        $coursename = $course ? format_string($course->fullname) : 'ID ' . $result->courseid;
        $detail = $result->status === 'failed' ? s($result->message) : '';
        $table->data[] = [$coursename, s($result->blockname), s($message), $detail];
    }
    echo $OUTPUT->box(get_string('status_' . $run->status, 'local_block_bulk_setup'), 'generalbox');
    if ($results) {
        echo html_writer::div(html_writer::table($table), 'table-responsive');
    } else {
        echo $OUTPUT->notification(get_string('noresults', 'local_block_bulk_setup'), 'notifymessage');
    }
}

$table = new html_table();
$table->head = ['ID', get_string('timecreated', 'local_block_bulk_setup'), get_string('startedby', 'local_block_bulk_setup'), get_string('results', 'local_block_bulk_setup')];
foreach ($DB->get_records('local_block_bulk_setup_runs', null, 'timecreated DESC') as $run) {
    $user = core_user::get_user($run->userid, 'id, firstname, lastname');
    $table->data[] = [
        html_writer::link(new moodle_url($url, ['runid' => $run->id]), (string)$run->id),
        userdate($run->timecreated),
        fullname($user),
        get_string('status_' . $run->status, 'local_block_bulk_setup'),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
