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

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/question/category_class.php");
require_once("$CFG->dirroot/lib/questionlib.php");

/**
 * Class representing custom question category
 *
 * @package    local_purgequestioncategory
 * @copyright  2016 Vadim Dvorovenko <Vadimon@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_purgequestioncategory_question_category_object extends question_category_object {

    /**
     * Initializes this classes general category-related variables
     *
     * @param int $page
     * @param array $contexts
     * @param int $currentcat
     * @param int $defaultcategory
     * @param int $todelete
     * @param array $addcontexts
     */
    public function initialize($page, $contexts, $currentcat, $defaultcategory, $todelete, $addcontexts) {
        $lastlist = null;
        foreach ($contexts as $context) {
            $this->editlists[$context->id] = new local_purgequestioncategory_question_category_list('ul', '', true,
                    $this->pageurl, $page, 'cpage', QUESTION_PAGE_LENGTH, $context);
            $this->editlists[$context->id]->lastlist = & $lastlist;
            if ($lastlist !== null) {
                $lastlist->nextlist = & $this->editlists[$context->id];
            }
            $lastlist = & $this->editlists[$context->id];
        }

        $count = 1;
        $paged = false;
        foreach ($this->editlists as $key => $list) {
            list($paged, $count) = $this->editlists[$key]->list_from_records($paged, $count);
        }
    }

    /**
     * Outputs a list to allow editing/rearranging of existing categories
     * $this->initialize() must have already been called
     */
    public function output_edit_lists() {
        global $OUTPUT;

        echo $OUTPUT->heading(get_string('purgecategories', 'local_purgequestioncategory'));

        foreach ($this->editlists as $context => $list) {
            $listhtml = $list->to_html(0, array('str' => $this->str));
            if ($listhtml) {
                $classes = 'boxwidthwide boxaligncenter generalbox questioncategories';
                $classes .= ' contextlevel' . $list->context->contextlevel;
                echo $OUTPUT->box_start($classes);
                $fullcontext = context::instance_by_id($context);
                echo $OUTPUT->heading(get_string('questioncatsfor', 'question', $fullcontext->get_context_name()), 3);
                echo $listhtml;
                echo $OUTPUT->box_end();
            }
        }

        if (!empty($list)) {
            echo $list->display_page_numbers();
        }
    }

    /**
     * Returns overall subcategories count in category and all subcategories.
     *
     * @param int $categoryid id of category
     * @return int questions count
     */
    public function get_subcategories_count($categoryid) {
        global $DB;

        $subcategories = $DB->get_records('question_categories', array('parent' => $categoryid), 'id');
        $count = count($subcategories);
        foreach ($subcategories as $subcategory) {
            $count += $this->get_subcategories_count($subcategory->id);
        }
        return $count;
    }

    /**
     * Returns overall question count in category and all subcategories.
     *
     * @param int $categoryid id of category
     * @return int questions count
     */
    public function get_questions_count($categoryid) {
        global $DB;

        $count = $DB->count_records('question', array('category' => $categoryid));
        $subcategories = $DB->get_records('question_categories', array('parent' => $categoryid), 'id');
        foreach ($subcategories as $subcategory) {
            $count += $this->get_questions_count($subcategory->id);
        }
        return $count;
    }

    /**
     * Returns used question count in category and all subcategories.
     *
     * @param int $categoryid id of category
     * @return int questions count
     */
    public function get_used_questions_count($categoryid) {
        global $DB;

        $count = 0;
        $questions = $DB->get_records('question', array('category' => $categoryid), '', 'id');
        foreach ($questions as $question) {
            if (questions_in_use(array($question->id))) {
                $count++;
            }
        }
        $subcategories = $DB->get_records('question_categories', array('parent' => $categoryid), 'id');
        foreach ($subcategories as $subcategory) {
            $count += $this->get_used_questions_count($subcategory->id);
        }
        return $count;
    }

    /**
     * Moves used questions to new category. Removes category and all subcategories and all unused questions.
     *
     * @param int $oldcat id of category to delete
     * @param int $newcat id of category to move unused question.
     */
    public function move_and_purge_category($oldcat, $newcat) {
        global $DB;
        $subcategories = $DB->get_records('question_categories', array('parent' => $oldcat), 'id');
        foreach ($subcategories as $subcategory) {
            $this->move_and_purge_category($subcategory->id, $newcat);
        }
        // Trying to remove all unused question.
        $questions = $DB->get_records('question', array('category' => $oldcat), 'id');
        foreach ($questions as $question) {
            if (questions_in_use(array($question->id))) {
                $DB->set_field('question', 'hidden', 1, array('id' => $question->id));
            } else {
                question_delete_question($question->id);
            }
        }
        // Move used questions to new category and delete category.
        if ($DB->record_exists('question', array('category' => $oldcat), 'id')) {
            $this->move_questions_and_delete_category($oldcat, $newcat);
        } else {
            $this->delete_category($oldcat);
        }
    }

    /**
     * Removes category and all questions and subcategories
     *
     * @param int $oldcat id of category to delete
     */
    public function purge_category($oldcat) {
        global $DB;
        $subcategories = $DB->get_records('question_categories', array('parent' => $oldcat), 'id');
        foreach ($subcategories as $subcategory) {
            $this->purge_category($subcategory->id);
        }
        // Trying to remove all unused question.
        $questions = $DB->get_records('question', array('category' => $oldcat), 'id');
        foreach ($questions as $question) {
            if (questions_in_use(array($question->id))) {
                $DB->set_field('question', 'hidden', 1, array('id' => $question->id));
            } else {
                question_delete_question($question->id);
            }
        }
        // Delete category, if no questions.
        if (!$DB->record_exists('question', array('category' => $oldcat), 'id')) {
            $this->delete_category($oldcat);
        }
    }

}