<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tool for deleting question category with question and subcategories.
 *
 * @package    local_purgequestioncategory
 * @copyright  2016 Vadim Dvorovenko <Vadimon@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("$CFG->dirroot/question/editlib.php");

require_login();

$categoryid = required_param('purge', PARAM_INT);

$category = $DB->get_record('question_categories', array('id' => $categoryid), '*', MUST_EXIST);
$context = context::instance_by_id($category->contextid);
require_capability('local/purgequestioncategory:purgecategory', $context);

if ($context->contextlevel == CONTEXT_COURSE) {
    $course = get_course($context->instanceid);
    $PAGE->set_course($course);
    $pageparams = array('courseid' => $context->instanceid);
} else if ($context->contextlevel == CONTEXT_MODULE) {
    list($module, $cm) = get_module_from_cmid($context->instanceid);
    $PAGE->set_cm($cm);
    $pageparams = array('cmid' => $context->instanceid);
} else {
    $pageparams = array();
}

$PAGE->set_pagelayout('admin');
$url = new moodle_url('/local/purgequestioncategory/category.php', $pageparams);
$PAGE->set_url($url);
$PAGE->set_title(get_string('confirmpurge', 'local_purgequestioncategory'));
$PAGE->set_heading($COURSE->fullname);

$qcobject = new local_purgequestioncategory_question_category_object(0, $url, array(), 0, $categoryid, 0, array());

$category->subcategories = $qcobject->get_subcategories_count($category->id);
$category->totalquestions = $qcobject->get_questions_count($category->id);
$category->usedquestions = $qcobject->get_used_questions_count($category->id);
$category->unusedquestions = $category->totalquestions - $category->usedquestions;

$url = new moodle_url('/local/purgequestioncategory/confirm.php');
$mform = new local_purgequestioncategory_confirm_form($url, array('category' => $category));

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/purgequestioncategory/category.php', $pageparams));
} else if ($data = $mform->get_data()) {
    require_sesskey();
    if (isset($data->confirm)) {
        if ($category->usedquestions != 0) {
            $categoryparts = explode(',', $data->newcategory);
            $qcobject->move_and_purge_category($category->id, $categoryparts[0]);
        } else {
            $qcobject->purge_category($category->id);
        }
    }
    redirect(new moodle_url('/local/purgequestioncategory/category.php', $pageparams));
}
echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
