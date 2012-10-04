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

require_once '../../../config.php';
require_once $CFG->dirroot.'/grade/export/lib.php';
require_once 'grade_export_fusion.php';
require_once $CFG->dirroot.'/grade/export/fusion/fusionlib.php';

$id                = required_param('id', PARAM_INT); // course id
$groupid           = optional_param('groupid', 0, PARAM_INT);
$itemids           = required_param('itemids', PARAM_RAW);
$export_feedback   = optional_param('export_feedback', 0, PARAM_BOOL);
$separator         = optional_param('separator', 'comma', PARAM_ALPHA);
$updatedgradesonly = optional_param('updatedgradesonly', false, PARAM_BOOL);
$displaytype       = optional_param('displaytype', $CFG->grade_export_displaytype, PARAM_INT);
$decimalpoints     = optional_param('decimalpoints', $CFG->grade_export_decimalpoints, PARAM_INT);
$tablename         = required_param('tablename', PARAM_RAW); // proposed table name

if (!$course = $DB->get_record('course', array('id'=>$id))) {
    print_error('nocourseid');
}

$PAGE->set_url('/grade/export/fusion/index.php', array('id'=>$id));
$PAGE->set_pagelayout('incourse');

require_login($course);
$context = get_context_instance(CONTEXT_COURSE, $id);

require_capability('moodle/grade:export', $context);
require_capability('gradeexport/fusion:view', $context);

if (groups_get_course_groupmode($COURSE) == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
    if (!groups_is_member($groupid, $USER->id)) {
        print_error('cannotaccessgroup', 'grades');
    }
}

// parameters to preserve
$preserve = array(
                'id' => $id,
                'groupid' => $groupid,
                'itemids' => $itemids,
                'export_feedback' => $export_feedback,
                'separator' => $separator,
                'updatedgradesonly' => $updatedgradesonly,
                'displaytype' => $displaytype,
                'decimalpoints' => $decimalpoints,
                'tablename' => $tablename,
);

// check OAuth
//$oauth = new fusion_grade_export_fusion();
$returnurl = new moodle_url('/grade/export/fusion/export.php');
foreach ($preserve as $k => $v) {
    $returnurl->param($k, $v);
}
$returnurl->param('sesskey', sesskey());

// check the config
$clientid = false;
$secret = false;
if (!empty($CFG->fusion_clientid)) {
    $clientid = $CFG->fusion_clientid;
    $secret = $CFG->fusion_secret;
}
// fallback to the googledrive config
else {
    $clientid = get_config('googledrive', 'clientid');
    $secret = get_config('googledrive', 'secret');
}

if (empty($clientid) || empty($secret)) {
    print_error('noconfig', 'gradeexport_fusion');
}

$fusion_realm = 'https://www.googleapis.com/auth/fusiontables';
$googleoauth = new google_oauth($clientid, $secret, $returnurl, $fusion_realm);
if (!$googleoauth->is_logged_in()) {
    $url = $googleoauth->get_login_url();
    redirect($url, get_string('login', 'gradeexport_fusion'), 2);
//     echo '<a target="_blank" href="'.$url->out(false).'">'.get_string('login', 'repository').'</a>';
}
$oauth = new fusion_grade_export_oauth_fusion($googleoauth);
$oauth->show_tables();

// print all the exported data here
$export = new grade_export_fusion($course, $groupid, $itemids, $export_feedback, $updatedgradesonly, $displaytype, $decimalpoints, $separator, $tablename);
$export->set_table($tablename);
$export->export_grades($oauth);
