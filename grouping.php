<?php

require_once (dirname(__FILE__).'/../../config.php');

require_once('grouping_form.php');
require_once($CFG->dirroot.'/lib/grouplib.php');
require_once($CFG->dirroot.'/group/lib.php');
require_once('lib.php');

// get parameters
$courseid   = required_param('course', PARAM_INT);
$action     = optional_param('action', 'new', PARAM_TEXT);
$groupingid = optional_param('grouping', NULL, PARAM_INT);

// get url and parameters
$url = new moodle_url('/blocks/vgrouping/grouping.php');
$url_params = $_GET;
foreach ($url_params as $var => $val) {
    if (empty($val)) unset($url_params[$var]);
}

// require login and set context
require_login($courseid, false);
$context = get_context_instance(CONTEXT_COURSE, $courseid);
require_capability('block/vgroupings:view', $context);

// build the head part of page
$PAGE->set_context($context);
$PAGE->set_url($url, $url_params);

// set title and navbar
$PAGE->set_title(get_string('msg_view_management', 'block_vgroupings'));
$PAGE->navbar->add(get_string('msg_view_management', 'block_vgroupings'));

// upload with core info
if (has_capability('moodle/course:managegroups', $context)) {
    syncronized_with_moodle_core($courseid);
}

// remove grouping
if (isset($action) && $action == 'delete') {
    require_capability('block/vgroupings:managegroupings', $context);
    
    $groupingid = required_param('grouping', PARAM_INT);
    if (optional_param('confirm', false, PARAM_BOOL)) {
        $groups = groups_get_all_groups($courseid, 0, $groupingid);
        if (!empty($groups)) { // remove groups in grouping
            foreach ($groups as $groupid => $group) {
                if (!groups_delete_group($groupid)) {
                    print_error('errordeletegroup', 'group');
                }
            }
        }
        groups_delete_grouping($groupingid);
        $DB->delete_records('block_vgroupings', array('groupingid'=>$groupingid, 'courseid'=>$courseid));
        header('Location: grouping.php?course='.$courseid);
    } else {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('msg_view_management', 'block_vgroupings'));
        echo $OUTPUT->confirm(get_string('delete_confirm_label', 'block_vgroupings'),
                    'grouping.php?course='.$courseid.'&grouping='.$groupingid.'&action=delete&confirm=true',
                    'grouping.php?course='.$courseid.'&grouping='.$groupingid);
        echo $OUTPUT->footer();
        die; 
    }
}

// ------------------------------------------------------------------------>
$members = get_potential_members($courseid, $context);
$select_groups = get_view_groups($courseid, $context);

$grouping_form = new grouping_form($context, $members, $select_groups);

// save or update groupings
if ($data = $grouping_form->get_data()) {
    $errors = $grouping_form->validation($data);
    if (!empty($errors)) {
        foreach ($errors as $errorname=>$error) {
            print_error($errorname, $error);
        }
        die;
    }
    // add or update groupings (groupings, grouping_info)
    $grouping = $data->grouping;
    $grouping->description = $grouping->name . ' (created used block_vgroupings)';
    $grouping->courseid = $courseid;
    
    if (has_capability('block/vgroupings:managegroupings', $context)) {
        // add or update in block_vgroupings
        if ($data->isupdate) {
            groups_update_grouping($grouping);
        } else {
            $grouping->id = create_vgrouping($grouping, $USER);
        }
    }
    // save groups and members
    if (has_capability('block/vgroupings:managegroups', $context)) {
        if (!isset($data->groups)) { $data->groups=array(); }
        if (!isset($data->group_members)) { $data->group_members=array(); }
        save_update_vgroups($data->groups, $data->group_members, $courseid, $context, $grouping->id, $USER);
    }
    redirect(new moodle_url('/blocks/vgroupings/grouping.php', array('course'=>$courseid, 'grouping'=>$grouping->id)));
}

$PAGE->requires->js('/blocks/vgroupings/js/jquery.js');
$PAGE->requires->js('/blocks/vgroupings/js/jquery.ui.js');
$PAGE->requires->js('/blocks/vgroupings/js/easyTooltip.js');
$PAGE->requires->js('/blocks/vgroupings/js/grouping.js');

// display header
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('msg_view_management', 'block_vgroupings'));

// get groupings for user
$groupings = get_groupings($courseid);

// tab menu display
$tabs = array();
if (!empty($groupings)) {
    foreach ($groupings as $grouping) {
        $tabs[0][] = new tabobject('grouping'.$grouping->id,
            new moodle_url('/blocks/vgroupings/grouping.php', array('course'=>$courseid, 'grouping'=>$grouping->id)),
            $grouping->name);
    }
}
$tabs[0][] = new tabobject('new',
    new moodle_url('/blocks/vgroupings/grouping.php', array('course'=>$courseid, 'action'=>'new')),
    get_string('new_grouping_label','block_vgroupings'));

print_tabs($tabs, (isset($groupingid) ? 'grouping'.$groupingid : 'new'));

// set data for form grouping_form and groupids    
if (!empty($groupingid) && $grouping = $groupings[$groupingid]) {
    $groups = get_groups($groupingid, $courseid, $context);
    $grouping_form->set_data($grouping, $groups);
}
$grouping_form->display();

// print foot
echo $OUTPUT->footer();

