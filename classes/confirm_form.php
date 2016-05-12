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

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for confirmation of category purge.
 *
 * @package    local_purgequestioncategory
 * @copyright  2016 Vadim Dvorovenko <Vadimon@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_purgequestioncategory_confirm_form extends moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        global $OUTPUT;

        $mform = $this->_form;

        $category = $this->_customdata['category'];

        $mform->addElement('header', 'header', get_string('confirmpurge', 'local_purgequestioncategory'));

        $data = new stdClass();
        $data->name = $category->name;
        $data->subcategories = $category->subcategories;
        $data->usedquestions = $category->usedquestions;
        $data->unusedquestions = $category->unusedquestions;
        if ($category->usedquestions != 0) {
            $message = get_string('infowithmove', 'local_purgequestioncategory', $data);
        } else {
            $message = get_string('infowithoutmove', 'local_purgequestioncategory', $data);
        }
        $message = $OUTPUT->box($message, 'generalbox boxaligncenter');
        $mform->addElement('html', $message);

        $mform->addElement('hidden', 'purge', $category->id);
        $mform->setType('purge', PARAM_INT);

        if ($category->usedquestions != 0) {
            $options = array();
            $options['contexts'] = array(context::instance_by_id($category->contextid));
            $options['top'] = true;
            $options['nochildrenof'] = "$category->id,$category->contextid";

            $qcategory = $mform->addElement('questioncategory', 'newcategory', get_string('category', 'question'), $options);
        }

        $message = $OUTPUT->box(get_string('confirmmessage', 'local_purgequestioncategory', $data), 'generalbox boxaligncenter');
        $mform->addElement('html', $message);

        $mform->addElement('checkbox', 'confirm', '', get_string('iconfirm', 'local_purgequestioncategory'));
        $mform->setType('confirm', PARAM_INT);

        $this->add_action_buttons(true, get_string('purgethiscategory', 'local_purgequestioncategory'));
    }

    /**
     * Form validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $category = $this->_customdata['category'];
        if ($category->usedquestions != 0) {
            $top = "0,$category->contextid";
            if ($data['newcategory'] == $top) {
                $errors['newcategory'] = get_string('validationcategory', 'local_purgequestioncategory');
            }
        }
        if (!isset($data['confirm']) || $data['confirm'] != 1) {
            $errors['confirm'] = get_string('validationconfirm', 'local_purgequestioncategory');;
        }

        return $errors;
    }
}
