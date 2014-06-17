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
 * Exports an Excel spreadsheet of the component grades in a Marking Guide-graded assignment.
 *
 * @package    report_componentgrades
 * @copyright  2014 Paul Nicholls
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/lib/excellib.class.php');
require_once($CFG->dirroot.'/report/componentgrades/locallib.php');

$id          = required_param('id', PARAM_INT);// Course ID
$modid       = required_param('modid', PARAM_INT);// CM ID

$params['id'] = $id;
$params['modid'] = $id;

$PAGE->set_url('/report/componentgrades/index.php', $params);

$course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);
require_login($course);

$modinfo = get_fast_modinfo($course->id);
$cm = $modinfo->get_cm($modid);
$modcontext = context_module::instance($cm->id);
require_capability('mod/assign:grade', $modcontext);

add_to_log($course->id, "course", "report componentgrades", "report/componentgrades/guide.php?id=$course->id&modid=$cm->id", $cm->id);

$filename = $course->shortname . ' - ' . $cm->name . '.xls';

$data = $DB->get_records_sql("SELECT    ggf.id AS ggfid, crs.shortname AS course, asg.name AS assignment, gd.name AS guide,
                                        ggc.shortname, ggf.score, ggf.remark, ggf.criterionid, rubm.username AS grader,
                                        stu.id AS userid, stu.idnumber AS idnumber, stu.firstname, stu.lastname, stu.username AS student,
                                        FROM_UNIXTIME(gin.timemodified) AS modified
                                FROM {course} AS crs
                                JOIN {course_modules} AS cm ON crs.id = cm.course
                                JOIN {assign} AS asg ON asg.id = cm.instance
                                JOIN {context} AS c ON cm.id = c.instanceid
                                JOIN {grading_areas} AS ga ON c.id=ga.contextid
                                JOIN {grading_definitions} AS gd ON ga.id = gd.areaid
                                JOIN {gradingform_guide_criteria} AS ggc ON (ggc.definitionid = gd.id)
                                JOIN {grading_instances} AS gin ON gin.definitionid = gd.id
                                JOIN {assign_grades} AS ag ON ag.id = gin.itemid
                                JOIN {user} AS stu ON stu.id = ag.userid
                                JOIN {user} AS rubm ON rubm.id = gin.raterid
                                JOIN {gradingform_guide_fillings} AS ggf ON (ggf.instanceid = gin.id)
                                AND (ggf.criterionid = ggc.id)
                                WHERE cm.id = ? AND gin.status = 1
                                ORDER BY lastname ASC, firstname ASC, userid ASC, ggc.sortorder ASC, ggc.shortname ASC", array($cm->id));

$students = report_componentgrades_get_students($course->id);

$first = reset($data);
if ($first === false) {
    $url = $CFG->wwwroot.'/mod/assign/view.php?id='.$cm->id;
    $message = "No grades have been entered into this assignment's marking guide.";
    redirect($url, $message, 5);
    exit;
}

$workbook = new MoodleExcelWorkbook("-");
$workbook->send($filename);
$sheet = $workbook->add_worksheet($cm->name);

report_componentgrades_add_header($workbook, $sheet, $course->fullname, $cm->name, 'guide', $first->guide);

$pos = 4;
foreach($data as $line) {
    if ($line->userid !== $first->userid) {
        break;
    }
    $sheet->write_string(4, $pos, $line->shortname);
    $sheet->merge_cells(4, $pos, 4, $pos+1);
    $sheet->write_string(5, $pos, 'Score');
    $sheet->set_column($pos, $pos++, 6); // Set column width to 6.
    $sheet->write_string(5, $pos, 'Feedback');
    $sheet->set_column($pos, $pos++, 10); // Set column widths to 10.
}

$gradinginfopos = $pos;
report_componentgrades_finish_colheaders($workbook, $sheet, $pos);

report_componentgrades_add_data($sheet, $data, $students, $gradinginfopos, 'guide');

$workbook->close();

exit;