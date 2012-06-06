<?php

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

/// get url variables
class grouping_form {

    private $context;
    private $members = array();
    private $select_roles = array();
    private $select_groups = array();
    
    private $grouping;
    private $groups = array();
    private $group_members = array();
    private $group_admins = array();
    private $isupdate = false;
    
    function grouping_form($context, $members, $select_groups = NULL, $select_roles = NULL) {
        global $DB;
        $this->context = $context;
        $this->members = $members;
        
        if ($select_groups != NULL) {
            $this->select_groups = $select_groups;
        }
        
        if ($select_roles != NULL) {
            $this->select_roles = $select_roles;
        } else {
            $roleids = groups_get_possible_roles($context);
            $this->roles = $DB->get_records_list('role', 'id', $roleids);
        }
        $this->grouping = new stdClass();
    }

    function validation($data) {
        global $COURSE, $DB;
        $errors = array();
        if (empty($data->grouping->name)) {
            $errors['groupingname'] = get_string('grouping_name_empty_error', 'block_vgroupings');
        }
        return $errors;
    }

    function set_data($grouping, $groups) {
        global $DB;
        $this->grouping = $grouping;
        $this->groups = $groups;
        foreach($this->groups as $groupid=>$group) {
            $this->group_members[$groupid] = $DB->get_records_sql('SELECT * FROM {user} WHERE id IN '.
                                        '(SELECT userid FROM {groups_members} WHERE groupid=?)', array($group->id));
            $this->group_admins[$groupid] = $DB->get_record('user',
                    array('id'=>$DB->get_record('block_vgroupings_groups', array('groupid'=>$groupid))->userid));
        }
        $this->isupdate = true;
    }
    
    function is_submitted() {
        return (!empty($_REQUEST['grouping_form']) ? true : false);
    }

    function get_data() {
        global $USER;
        $result = array();
        if ($this->is_submitted()) { 
            $result = new stdClass();
            
            $groupingname = trim($_REQUEST['groupingname']);
            $inherit = isset($_REQUEST['inherit']) && $_REQUEST['inherit']==1 ? true : false;
            $addme = isset($_REQUEST['addme']) && $_REQUEST['addme']==1 ? true : false;
            $commoncircle = isset($_REQUEST['commoncircle']) ? $_REQUEST['commoncircle'] : array();
            $isupdate = $_REQUEST['action'] != 'update' ? false : true;
            
            // update isupdate var
            $result->isupdate = $isupdate;
            
            // update grouping
            $result->grouping->id = $isupdate ? $_REQUEST['grouping'] : 0;
            $result->grouping->name = $groupingname;
            
            $count = 0;
            if (isset($_REQUEST['circles'])) {
                foreach ($_REQUEST['circles'] as $circle) {
                    // update groups
                    $group = new stdClass();
                    $group->id = $circle['groupid'];
                    $group->name = $inherit ? $groupingname.' '.$circle['name'] : $circle['name'];
                    $result->groups[$count] = $group;
                    // update group_members
                    $result->group_members[$count] = array();
                    if (!empty($circle['members'])) {
                        foreach ($circle['members'] as $userid) {
                            $result->group_members[$count][] = $userid;
                        }
                    }
                    if ($addme) {
                        $result->group_members[$count][] = $USER->id;
                    }
                    if (!empty($_REQUEST['commoncircle'])) {
                        foreach ($_REQUEST['commoncircle'] as $userid) {
                            $result->group_members[$count][] = $userid;
                        }
                    }
                    $count++;
                }
            }
            return $result;
        }
        return NULL;
    }
   
    private function get_roleids($userid) {
        global $DB;
        $listofcontexts = get_related_contexts_string($this->context);
        $user_roleids = $DB->get_fieldset_select('role_assignments', 'roleid', 'userid='.$userid.' AND contextid '.$listofcontexts);
        $result = 'no_roles';
        if (!empty($user_roleids)) { $result = implode(',', $user_roleids); }
        return $result;
    }

    private function get_groupids($userid){
        global $DB;
        $user_groupids = $DB->get_fieldset_sql('SELECT groupid FROM {groups_members} WHERE groupid IN '.
                            '(SELECT id FROM {groups} WHERE courseid=?) AND userid=?', array($this->context->instanceid, $userid));
        $result = 'no_groups';
        if (!empty($user_groupids)) { $result = implode(',', $user_groupids); }
        return $result;
    }

    private function get_common_members() {
        global $DB;
        $result = array();
        if (!empty($this->groups)) { $result = $this->members; }
        if (!empty($this->group_members)) {
           foreach($this->group_members as $groupid=>$members) {
                $result = array_intersect_key($result, $members);
            }
        }
        return $result;
    }
 
    private function isinherit() {
        $result = true;
        if (!empty($this->groups)) {
            foreach ($this->groups as $groupid=>$group) {
                $pos = strpos($group->name, $this->grouping->name);
                $result = ($pos!==false ? ($pos==0 ? true : false) : false);
            }
        }
        return $result;
    }

    function display() {
        global $OUTPUT, $DB;

        // get role select
        $htmlgroups = '<select id="groupselect">';
        $htmlgroups .= '<option value="0"> -- '.get_string('all_groups', 'block_vgroupings').' -- </option>';
        if (!empty($this->select_groups)) {
            foreach ($this->select_groups as $group) {
                $htmlgroups .= '<option value="'.$group->id.'">'.$group->name.'</option>';
            }
        }     
        if (has_capability('moodle/course:managegroups', $this->context)) {
            $htmlgroups .= '<option value="no_groups">'.get_string('no_groups', 'block_vgroupings').'</option>';
        }

        $htmlgroups .= '</select>';

        // get role select
        $htmlroles  = '<select id="roleselect">';
        $htmlroles .= '<option value="0"> -- '.get_string('all_roles','block_vgroupings').' -- </option>';
        foreach ($this->roles as $role) {
            $htmlroles .= '<option value="'.$role->id.'">'.$role->name.'</option>';
        }
        $htmlroles .= '<option value="no_roles">'.get_string('no_roles','block_vgroupings').'</option>';
        $htmlroles .= '</select>';

        $userids = array();
        $count = 0;
        $groupdivs = '';
        if (!empty($this->groups)) {
            foreach ($this->groups as $group) {
                $groupname = ($this->isinherit() ? substr($group->name, strlen($this->grouping->name)) : $group->name);
                $groupid = (isset($group->id) ? $group->id : 0);
                $admin = $this->group_admins[$groupid];
                $groupdivs .= '<div class="circle circle_full" id="circle-'.$count.'" groupid="'.$groupid.'">';
                $groupdivs .= '<div class="circle__disk"></div>';
                $groupdivs .= '<div class="circle__inner">';
                $groupdivs .= '<div class="circle__name">'.$groupname.'</div>';
                $groupdivs .= '<div class="circle__admin" adminId="'.$admin->id.'"
                                style="display: none;">'.$admin->firstname.' '.$admin->lastname.'</div>';
                $groupdivs .= '<div class="circle__number">'.count($this->group_members[$groupid]).'</div>';
                $groupdivs .= '</div>';
                if (!empty($this->group_members[$groupid])) {
                    foreach ($this->group_members[$groupid] as $userid=>$user) {
                        $groupdivs .= '<img class="circleUser canRemove ui-draggable" width="48" height="48" userid="'.$userid.'" circleid="circle-'.$count.'"/>';
                    }
                }
                $groupdivs .= '</div>';
                $count++;
            }
        }
        
        // get user divs
        $userdivs = '';
        foreach($this->members as $userid => $user) {
            $user = $DB->get_record('user', array('id'=>$userid));
            if (!in_array($userid, $userids)) {
                $userdivs .= '<li id="user-'.$userid.'" class="userList__cell user" groups="'.$this->get_groupids($userid).'," ';
                $userdivs .= ' roles="'.$this->get_roleids($userid).',">';
                $userdivs .= $OUTPUT->user_picture($user, array('size'=>48, 'link'=>false, 'popup'=>false,
                                                                'courseid'=>$this->context->instanceid,
                                                                'class'=>'userList__person', 'alttext'=>false));
                $userdivs .= '<div class="userList__name">'.$user->firstname.'&nbsp;'.$user->lastname.'</div>';
                $userdivs .= '</li>';
            }
        }
        
        // get inherit name and addme
        $inherit_check = ($this->isinherit() ? 'checked="checked"' : '');
        $addme_check = (has_capability('moodle/site:accessallgroups', $this->context) ? '': 'checked="checked" disabled');
        
        // get all label messages
        $number_groups_label = get_string('number_groups', 'block_vgroupings');
        $reset_groups_label = get_string('reset_group_members', 'block_vgroupings');
        $participant_label = get_string('participants', 'block_vgroupings');
        $randomly_label = get_string('randomly_select_members', 'block_vgroupings');
        $show_roles_label = get_string('show_roles', 'block_vgroupings');
        $show_groups_label = get_string('show_groups', 'block_vgroupings');

        // print forms of groupings
        if ($this->isupdate && has_capability('block/vgroupings:managegroupings', $this->context)) {
            echo '<form action="?course='.$_REQUEST['course'].'&grouping='.$this->grouping->id.'&action=delete" method="post"  >';
            echo '<input type="hidden" id="isupdate" name="isupdate" value="'.$this->grouping->id.'" />';
            echo '<input type="submit" value="'.get_string('delete_grouping','block_vgroupings').'" />';
            echo '</form>';
        }

        $disabled = '';
        if (!has_capability('block/vgroupings:managegroupings', $this->context)) {
            $disabled = 'disabled';
        }
        $groupingForm  = '<div style="display:block" id="groupingForm">';
        $groupingForm .= '<table style="margin:auto; display: block;">';
        $groupingForm .= '<tr>';
        $groupingForm .= '<th scope="row"><label for="inheritgroupingname">'.get_string('grouping_name_prefix', 'block_vgroupings').'</label></th>';
        $groupingForm .= '<td style="text-align:left;"><input type="checkbox" id="inherit" value="1" '.$inherit_check.' '.$disabled.' /></td>';
        $groupingForm .= '</tr>';
        $groupingForm .= '<tr>';
        $groupingForm .= '<th scope="row"><label for="addme">'.get_string('addme_groups', 'block_vgroupings').'</label></th>';
        $groupingForm .= '<td style="text-align:left;"><input type="checkbox" id="addme" value="1" '.$addme_check.' '.$disabled.' /></td>';
        $groupingForm .= '</tr>';
        $groupingForm .= '<tr>';
        $groupingForm .= '<th colspan="row"><label for="groupingname">'.get_string('grouping_name', 'block_vgroupings').'</label></th>';
        $groupingForm .= '<td><input type="text" id="groupingname" value="'.(isset($this->grouping->name) ? $this->grouping->name : '').'" size="40" '.$disabled.' /></td>';
        $groupingForm .= '</tr>';
        $groupingForm .= '</table>';
        $groupingForm .= '<button type="button" onclick="saveGrouping();">Save</button>&nbsp;';
        $groupingForm .= '</div>';
        
        echo <<<HTML
        <div>
            <div style="text-align:center;">$show_roles_label : $htmlroles<br/>$show_groups_label : $htmlgroups</div>
            <div style="text-align:center;">$number_groups_label : <span id="stepper" class="stepper">$count</span> <a id='addgroup' title='add group'>[+]</a></div>
            <div id="participants">
                <h2>$participant_label</h2>
                <button type="button" onclick="assignRandomly();">$randomly_label</button>
                <button type="button" onclick="resetGroupMembers();">$reset_groups_label</button>
                <div class="userList"><ul style="top: 0px;">$userdivs</ul></div>
            </div>
            <div id="circles" style="min-height: 200px; width: 100%; display: table; margin-bottom: 25px;">
                $groupdivs
            </div>
        </div>
        <div>$groupingForm</div>
HTML;
    }
}

