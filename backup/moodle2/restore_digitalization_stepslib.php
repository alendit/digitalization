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
 * @package    moodlecore
 * @subpackage backup-moodle2
 * @copyright  2011 Patrick Meyer, Tobias Niedl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Structure step to restore one choice activity
 */


class restore_digitalization_activity_structure_step extends restore_activity_structure_step {
 
    protected function define_structure() {
 
        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');
 
        $paths[] = new restore_path_element('digitalization', '/activity/digitalization');
        
 
        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }
 
    protected function process_digitalization($data) {
        global $DB, $CFG;
 
        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // new activity is a copy of an existing one. Store this information in the data...
        if (isset($this->copy_of) && is_numeric($data->copy_of) && $data->copy_of > 0) {
            // parent of new activity is already a data of an other activity -> don't modify the copy_of field
        } else {
            // parent activity is not a data -> fill in the parent-id into the copied activity
            $data->copy_of = $oldid;
        }

        // insert the choice record
        $newitemid = $DB->insert_record('digitalization', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    
    // If old instance is already delivered, copy also the file attached
    if($data->status === 'delivered')
        $this->clone_files($data, $newitemid);

    }
 
    protected function after_execute() {
        // Add choice related files, no need to match by itemname (just internally handled context)
        // (not used here)
    }

    protected function clone_files($data, $newitemid) {
        // Copy file for the new instance to use, see lib.php for more information
    global $DB, $CFG, $USER;

    $fs = get_file_storage();
    $filearea = $CFG->digitalization_filearea;
    $filearea_slashed = ($filearea) ? $filearea . '/' : '';
    $module = $DB->get_record('modules', array('name' => 'digitalization'))->id;

    $cm = $DB->get_record('course_modules', array('module' => $module,
                          'instance' => $data->id));

    if (!$cm) {
        debugging("Module not found");
        return;
    }
 
    $context = context_module::instance($cm->id);

    $files = $fs->get_area_files($context->id, 'mod_digitalization', $filearea, $data->id, 'sortorder DESC, id ASC', false);
    $stored_file = reset($files);

    $cm = $DB->get_record('course_modules', array('module' => $module,
                      'course' => $data->course,
                      'instance' => $newitemid));

    if (!$cm) {
        debugging("New module context not found");
        return;
    }

    $context = context_module::instance($cm->id);

    $file_record = array('contextid'=>$context->id, 'component'=>'mod_digitalization', 
             'filearea'=>$filearea, 'itemid'=>$newitemid, 'filepath'=>'/'.$filearea_slashed, 
             'filename'=>$data->name . substr($stored_file->get_filename(), -4),
             'timecreated'=>time(), 'timemodified'=>time(), 'userid' => $USER->id);

    // Now add the file to the course files
    $fs->create_file_from_storedfile($file_record, $stored_file); 
    }
}