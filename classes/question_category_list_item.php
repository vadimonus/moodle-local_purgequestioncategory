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
require_once("$CFG->dirroot/question/editlib.php");

/**
 * Class representing custom category list item
 *
 * @package    local_purgequestioncategory
 * @copyright  2016 Vadim Dvorovenko <Vadimon@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_purgequestioncategory_question_category_list_item extends question_category_list_item {

    /**
     * Creating list without icons.
     *
     * @param bool $first
     * @param bool $last
     * @param stdClass $lastitem
     */
    public function set_icon_html($first, $last, $lastitem) {
    }

    /**
     * Output the html just for this item. Called by to_html which adds html for children.
     *
     * @param array $extraargs
     * @return string
     */
    public function item_html($extraargs = array()) {
        global $CFG, $OUTPUT;
        $category = $this->item;

        $categoryname = format_string($category->name, true, array('context' => $this->parentlist->context));
        $categoryname = html_writer::tag('b', $categoryname);
        $item = "$categoryname ($category->questioncount)";
        $item .= format_text($category->info, $category->infoformat,
                array('context' => $this->parentlist->context, 'noclean' => true));

        // Don't allow delete if this is the last category in this context.
        if (!question_is_only_toplevel_category_in_context($category->id)) {
            $params = array('purge' => $this->id);
            $purgeurl = new moodle_url('/local/purgequestioncategory/confirm.php', $params);
            $text = get_string('purgethiscategory', 'local_purgequestioncategory');
            $icon = new pix_icon('purge', $text, 'local_purgequestioncategory');
            $item .= $OUTPUT->action_link($purgeurl, '', null, array(), $icon);
        }

        return $item;
    }
}
