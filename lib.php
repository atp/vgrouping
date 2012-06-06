<?php

/**
 * Function syncronized db core of moodle with block usp
 * mantain cohesion of data in blocks
 */
function syncronized_with_moodle_core($courseid) {
    global $DB, $USER;
    //-- remove unecessaries groupinfo
    $groupinfo = $DB->get_records('block_vgroupings_groups');
    foreach ($groupinfo as $id=>$groupinfo) {
        if ($DB->record_exists('groups', array('id'=>$groupinfo->groupid))) {
            if (!$DB->record_exists('groupings_groups', array('groupingid'=>$groupinfo->groupingid,
                                                             'groupid'=>$groupinfo->groupid))) {
                $DB->delete_records('block_vgroupings_groups', array('id'=>$id));
            }
        } else {
            $DB->delete_records('block_vgroupings_groups', array('groupid'=>$groupinfo->groupid));
        }
    }
    //-- remove unecessaries groupinginfo
    $groupinginfo = $DB->get_records('block_vgroupings', array());
    foreach ($groupinginfo as $id=>$groupinginfo) {
        if (!$DB->record_exists('groupings', array('id'=>$groupinginfo->groupingid))) {
            $DB->delete_records('block_vgroupings_groups', array('groupingid'=>$groupinginfo->groupingid));
            $DB->delete_records('block_vgroupings', array('id'=>$id));
        }
    }
    //-- update relation between groupinfo and groupinginfo
    //-- usage current user how management
    $groupingids = $DB->get_records_menu('block_vgroupings', array(), '', 'id, groupingid');
    //$groupids = $DB->get_records_menu('block_vgroupings_groups', array(), '', 'id, groupid');
    if (!empty($groupingids)) {
        $groupings_groups = $DB->get_records_select('groupings_groups', 'groupingid IN ('.
                                            implode(',', $groupingids).')'); //' and groupid IN ('. implode(',', $groupids).')');
        foreach ($groupings_groups as $record) {
            if (!$DB->record_exists('block_vgroupings_groups', array('groupingid'=>$record->groupingid,
                                                                     'groupid'=>$record->groupid))) {
                $groupinfo = new stdClass();
                $groupinfo->userid = $USER->id;
                $groupinfo->groupid = $record->groupid;
                $groupinfo->courseid = $courseid;
                $groupinfo->groupingid = $record->groupingid;
                $DB->insert_record('block_vgroupings_groups', $groupinfo);
            }
        }
    }
}

function get_view_groups($courseid, $context, $user=NULL) {
    global $USER, $DB;
    if (!isset($user)) { $user = $USER; }
    $groups = $DB->get_records('groups', array('courseid'=>$courseid));
    //-- return only groups that view according to capability
    if (!has_capability('moodle/course:managegroups', $context, $user) ||
        !has_capability('moodle/site:accessallgroups', $context, $user)) {
        $groups = array();
        $groupids = $DB->get_records_menu('groups_members', array('userid'=>$user->id), '', 'id, groupid');
        if (!empty($groupids)) {
            $groups = $DB->get_records_select('groups', 'courseid='.$courseid.' AND id IN ('.implode(',', $groupids).')');
        }
    } 
    //-- remove groups that defined by block
    foreach ($DB->get_records('block_vgroupings_groups', array('courseid'=>$courseid)) as $record) {
        unset($groups[$record->groupid]);
    }
    return $groups;
} 

function get_potential_members($courseid, $context, $user=NULL) {
    global $DB;
    $members = groups_get_potential_members($courseid);
    if (!has_capability('moodle/course:managegroups', $context, $user) ||
        !has_capability('moodle/site:accessallgroups', $context, $user)) {
        $groups = get_view_groups($courseid, $context, $user);
        //-- remove participants from list members
        if (!empty($groups)) {
            foreach ($members as $member) {
                if (!$DB->record_exists_select('groups_members', 'userid='.$member->id.' AND groupid IN('.implode(',', array_keys($groups)).')')) {
                    unset($members[$member->id]);
                }
            }
        } else {
            $members = array();
        }
    }
    return $members;
}

function create_vgrouping($grouping, $user) {
    global $DB;
    $groupingid = groups_create_grouping($grouping);
    $vgrouping = new stdClass();
    $vgrouping->userid = $user->id;
    $vgrouping->groupingid = $grouping->id;
    $vgrouping->courseid = $grouping->courseid;
    $DB->insert_record('block_vgroupings', $vgrouping);
    return $groupingid;
}

function save_update_vgroups($groups, $group_members, $courseid, $context, $groupingid, $user=NULL) {
    global $DB;
    $remove_groups = $DB->get_records_menu('block_vgroupings_groups',
                            array('courseid'=>$courseid, 'groupingid'=>$groupingid), '', 'groupid, id');
    if (has_capability('moodle/course:managegroups', $context, $user)) {
        $DB->delete_records('groupings_groups', array('groupingid'=>$groupingid));
    } else {
        $remove_groups = $DB->get_records_menu('block_vgroupings_groups', array('userid'=>$user->id,
                                                                                'groupingid'=>$groupingid,
                                                                                'courseid'=>$courseid), '', 'groupid, id');
        foreach ($remove_groups as $groupid=>$id) {
            $DB->delete_records('groupings_groups', array('groupid'=>$groupid, 'groupingid'=>$groupingid));
        }
    }
    
    $count = 0;
    foreach ($groups as $group) {
        $group->courseid = $courseid;
        if (!empty($group->id) && $group->id != 0) { // group->update
            groups_update_group($group);
            unset($remove_groups[$group->id]);
        } else {
            $group->id = groups_create_group($group);
            $vgroup = new stdClass();
            $vgroup->userid = $user->id;
            $vgroup->groupid = $group->id;
            $vgroup->courseid = $courseid;
            $vgroup->groupingid = $groupingid;
            $DB->insert_record('block_vgroupings_groups', $vgroup);
        }
        
        // change members of groups
        $DB->delete_records('groups_members', array('groupid'=>$group->id));
        foreach($group_members[$count] as $userid) {
            groups_add_member($group->id, $userid);
        }
        // add group in groupings
        groups_assign_grouping($groupingid, $group->id);
        $count++;
    }
     
    // remove groups
    foreach ($remove_groups as $groupid=>$id) {
        $DB->delete_records('block_vgroupings_groups', array('id'=>$id));
        $DB->delete_records('groups_members', array('groupid'=>$groupid));
        $DB->delete_records('groups', array('id'=>$groupid));
    }
}

function get_groupings($courseid) {
    global $DB;
    $groupings = array();
    $groupingids = $DB->get_records_menu('block_vgroupings', array('courseid'=>$courseid), '', 'id, groupingid');
    if (!empty($groupingids)) {
        $groupings = $DB->get_records_select('groupings', 'id IN ('.implode(', ', $groupingids).')');
    }
    return $groupings;
}

function get_groups($groupingid, $courseid, $context, $user=NULL) {
    global $USER, $DB;
    if (!isset($user)) { $user = $USER; }
    $groups = array();
    $groupids = $DB->get_records_menu('block_vgroupings_groups',
                            array('courseid'=>$courseid,
                                  'groupingid'=>$groupingid), '', 'id, groupid');
    if (!has_capability('moodle/course:managegroups', $context, $user)) {
        $groupids = $DB->get_records_menu('block_vgroupings_groups',
                            array('userid'=>$user->id,
                                  'courseid'=>$courseid,
                                  'groupingid'=>$groupingid), '', 'id, groupid');
    }
    if (!empty($groupids)) {
        $groups = $DB->get_records_sql('SELECT * FROM {groups} WHERE id IN ('.implode(",", $groupids).')');
    }
    return $groups;
}

?>
