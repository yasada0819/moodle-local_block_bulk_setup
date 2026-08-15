<?php
// This file is part of Moodle - http://moodle.org/

namespace local_block_bulk_setup\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Domain services for choosing, previewing and applying bulk block placement.
 *
 * @package    local_block_bulk_setup
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class service {
    /** @var string The Boost block drawer region. */
    public const DEFAULT_REGION = 'side-pre';

    /**
     * Gets categories as options suitable for a multiple select.
     *
     * @return array<int, string>
     */
    public static function category_options(): array {
        global $DB;

        $options = [];
        $categories = $DB->get_records('course_categories', null, 'path ASC', 'id, name, depth');
        foreach ($categories as $category) {
            $options[(int)$category->id] = str_repeat('- ', (int)$category->depth) . format_string($category->name);
        }
        return $options;
    }

    /**
     * Gets all non-site courses within selected categories and descendants.
     *
     * @param int[] $categoryids
     * @return array<int, \stdClass>
     */
    public static function courses_for_categories(array $categoryids): array {
        global $DB;

        $categoryids = array_values(array_unique(array_map('intval', $categoryids)));
        if (!$categoryids) {
            return [];
        }
        $categories = $DB->get_records('course_categories', null, '', 'id, parent');
        $included = array_fill_keys($categoryids, true);
        foreach ($categories as $category) {
            $parentid = (int)$category->id;
            while ($parentid && isset($categories[$parentid])) {
                if (isset($included[$parentid])) {
                    $included[(int)$category->id] = true;
                    break;
                }
                $parentid = (int)$categories[$parentid]->parent;
            }
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($included), SQL_PARAMS_NAMED, 'category');
        $params['siteid'] = SITEID;
        return $DB->get_records_select(
            'course',
            "id <> :siteid AND category $insql",
            $params,
            'sortorder ASC, fullname ASC',
            'id, category, shortname, fullname, format, visible'
        );
    }

    /**
     * Gets installed enabled blocks as options.
     *
     * A particular course can still reject a selected block. That check happens
     * in preview and immediately before writing the block instance.
     *
     * @return array<string, string>
     */
    public static function block_options(): array {
        global $DB;

        $options = [];
        foreach ($DB->get_records('block', ['visible' => 1], 'name ASC') as $record) {
            $instance = block_instance($record->name);
            if (!$instance) {
                continue;
            }
            $options[$record->name] = format_string($instance->get_title()) . ' (' . s($record->name) . ')';
        }
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);
        return $options;
    }

    /**
     * Creates a compact option label for a course.
     *
     * @param \stdClass $course
     * @return string
     */
    public static function course_label(\stdClass $course): string {
        return format_string($course->shortname) . ' — ' . format_string($course->fullname);
    }

    /**
     * Builds a plan for every course/block pair.
     *
     * @param int[] $courseids
     * @param string[] $blocknames
     * @param string $region
     * @param string $duplicatepolicy skip or add
     * @return array<int, \stdClass>
     */
    public static function preview(array $courseids, array $blocknames, string $region, string $duplicatepolicy): array {
        global $DB;

        $rows = [];
        foreach (array_values(array_unique(array_map('intval', $courseids))) as $courseid) {
            if (!$course = $DB->get_record('course', ['id' => $courseid], 'id, shortname, fullname, format', IGNORE_MISSING)) {
                continue;
            }
            foreach (array_values(array_unique($blocknames)) as $blockname) {
                $check = self::check_pair($course, $blockname, $region, $duplicatepolicy);
                $check->courseid = (int)$course->id;
                $check->coursename = self::course_label($course);
                $check->blockname = $blockname;
                $rows[] = $check;
            }
        }
        return $rows;
    }

    /**
     * Validates one block addition and returns a planned action.
     *
     * @param \stdClass $course
     * @param string $blockname
     * @param string $region
     * @param string $duplicatepolicy
     * @return \stdClass with action and message string identifier
     */
    public static function check_pair(\stdClass $course, string $blockname, string $region, string $duplicatepolicy): \stdClass {
        global $DB;

        $result = (object)['action' => 'failed', 'message' => 'result_failed'];
        $blockrecord = $DB->get_record('block', ['name' => $blockname, 'visible' => 1]);
        $block = $blockrecord ? block_instance($blockname) : false;
        if (!$block) {
            return $result;
        }

        $page = self::course_page($course);
        $regions = $page->blocks->get_regions();
        if (!in_array($region, $regions, true)) {
            return (object)['action' => 'skipregion', 'message' => 'result_skipregion'];
        }
        if (in_array($blockname, $page->blocks->get_unaddable_by_theme_block_types(), true) ||
                in_array($blockname, $page->blocks->get_required_by_theme_block_types(), true) ||
                !blocks_name_allowed_in_format($blockname, $page->pagetype) ||
                !$block->can_block_be_added($page)) {
            return (object)['action' => 'skipincompatible', 'message' => 'result_skipincompatible'];
        }

        $context = \context_course::instance($course->id);
        $params = ['contextid' => $context->id, 'blockname' => $blockname, 'pagetype' => 'course-view%'];
        $alreadyexists = $DB->record_exists_select(
            'block_instances',
            'parentcontextid = :contextid AND blockname = :blockname AND pagetypepattern LIKE :pagetype',
            $params
        );
        if (!$alreadyexists) {
            return (object)['action' => 'added', 'message' => 'result_added'];
        }
        if (!$block->instance_allow_multiple() || $duplicatepolicy !== 'add') {
            return (object)['action' => 'skipduplicate', 'message' => 'result_skipduplicate'];
        }
        return (object)['action' => 'addduplicate', 'message' => 'result_addduplicate'];
    }

    /**
     * Adds one planned block and returns the final result status.
     *
     * @param \stdClass $course
     * @param string $blockname
     * @param string $region
     * @param string $duplicatepolicy
     * @return \stdClass status, blockinstanceid and message
     */
    public static function add_to_course(\stdClass $course, string $blockname, string $region, string $duplicatepolicy): \stdClass {
        $check = self::check_pair($course, $blockname, $region, $duplicatepolicy);
        if (!in_array($check->action, ['added', 'addduplicate'], true)) {
            return (object)[
                'status' => 'skipped_' . str_replace('skip', '', $check->action),
                'blockinstanceid' => 0,
                'message' => $check->message,
            ];
        }

        try {
            $page = self::course_page($course);
            $weight = self::next_weight($course->id, $region);
            $block = $page->blocks->add_block($blockname, $region, $weight, false, 'course-view-*');
            return (object)[
                'status' => 'added',
                'blockinstanceid' => (int)$block->instance->id,
                'message' => 'result_added_done',
            ];
        } catch (\Throwable $exception) {
            return (object)[
                'status' => 'failed',
                'blockinstanceid' => 0,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Inserts a run and queues its one-off task.
     *
     * @param int $userid
     * @param int[] $categoryids
     * @param int[] $courseids
     * @param string[] $blocknames
     * @param string $region
     * @param string $duplicatepolicy
     * @return int run id
     */
    public static function queue_run(
        int $userid,
        array $categoryids,
        array $courseids,
        array $blocknames,
        string $region,
        string $duplicatepolicy
    ): int {
        global $DB;

        $now = time();
        $run = (object)[
            'userid' => $userid,
            'status' => 'queued',
            'categoriesjson' => json_encode(array_values(array_map('intval', $categoryids))),
            'courseidsjson' => json_encode(array_values(array_unique(array_map('intval', $courseids)))),
            'blocknamesjson' => json_encode(array_values(array_unique($blocknames))),
            'region' => $region,
            'duplicatepolicy' => $duplicatepolicy,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $runid = $DB->insert_record('local_block_bulk_setup_runs', $run);
        \local_block_bulk_setup\task\apply_run::queue($runid);
        return (int)$runid;
    }

    /**
     * Applies a queued run. Called only from the ad-hoc task.
     *
     * @param int $runid
     * @return void
     */
    public static function apply_run(int $runid): void {
        global $DB;

        $run = $DB->get_record('local_block_bulk_setup_runs', ['id' => $runid], '*', MUST_EXIST);
        if (!in_array($run->status, ['queued', 'running'], true)) {
            return;
        }
        $DB->update_record('local_block_bulk_setup_runs', (object)[
            'id' => $runid,
            'status' => 'running',
            'timemodified' => time(),
        ]);

        $courseids = json_decode($run->courseidsjson, true) ?: [];
        $blocknames = json_decode($run->blocknamesjson, true) ?: [];
        foreach ($courseids as $courseid) {
            $course = $DB->get_record('course', ['id' => (int)$courseid], 'id, shortname, fullname, format', IGNORE_MISSING);
            if (!$course) {
                foreach ($blocknames as $blockname) {
                    if (!$DB->record_exists('local_block_bulk_setup_results', [
                        'runid' => $runid,
                        'courseid' => (int)$courseid,
                        'blockname' => $blockname,
                    ])) {
                        $DB->insert_record('local_block_bulk_setup_results', (object)[
                            'runid' => $runid,
                            'courseid' => (int)$courseid,
                            'blockname' => $blockname,
                            'status' => 'failed',
                            'blockinstanceid' => 0,
                            'message' => 'Course no longer exists.',
                            'timecreated' => time(),
                        ]);
                    }
                }
                continue;
            }
            foreach ($blocknames as $blockname) {
                // A restarted task resumes without repeating completed pairs.
                if ($DB->record_exists('local_block_bulk_setup_results', [
                    'runid' => $runid,
                    'courseid' => $course->id,
                    'blockname' => $blockname,
                ])) {
                    continue;
                }
                $result = self::add_to_course($course, $blockname, $run->region, $run->duplicatepolicy);
                $DB->insert_record('local_block_bulk_setup_results', (object)[
                    'runid' => $runid,
                    'courseid' => $course->id,
                    'blockname' => $blockname,
                    'status' => $result->status,
                    'blockinstanceid' => $result->blockinstanceid,
                    'message' => $result->message,
                    'timecreated' => time(),
                ]);
            }
        }
        $DB->update_record('local_block_bulk_setup_runs', (object)[
            'id' => $runid,
            'status' => 'complete',
            'timemodified' => time(),
        ]);
    }

    /**
     * Makes the Moodle page object used for course block APIs.
     *
     * @param \stdClass $course
     * @return \moodle_page
     */
    private static function course_page(\stdClass $course): \moodle_page {
        $page = new \moodle_page();
        $page->set_context(\context_course::instance($course->id));
        $page->set_course($course);
        $page->set_pagetype('course-view-' . $course->format);
        $page->set_pagelayout('course');
        $page->set_url(new \moodle_url('/course/view.php', ['id' => $course->id]));
        $page->initialise_theme_and_output();
        return $page;
    }

    /**
     * Gets the next weight in a region for the course's direct block instances.
     *
     * @param int $courseid
     * @param string $region
     * @return int
     */
    private static function next_weight(int $courseid, string $region): int {
        global $DB;

        $context = \context_course::instance($courseid);
        $sql = "SELECT MAX(defaultweight)
                  FROM {block_instances}
                 WHERE parentcontextid = :contextid
                   AND defaultregion = :region
                   AND pagetypepattern LIKE :pagetype";
        $weight = $DB->get_field_sql($sql, [
            'contextid' => $context->id,
            'region' => $region,
            'pagetype' => 'course-view%',
        ]);
        return $weight === null ? 0 : ((int)$weight + 1);
    }
}
