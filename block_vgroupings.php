<?php

class block_vgroupings extends block_list {

	function init() {
		$this->title	= get_string('pluginname', 'block_vgroupings');
	}

	function get_content() {
		global $CFG, $USER, $COURSE, $SITE, $DB, $OUTPUT;

		if ($this->content !== NULL) {
			return $this->content;
		}

        // get context
        if (!empty($this->instance->pageid)) {
            $context = get_context_instance(CONTEXT_COURSE,
                                            $this->instance->pageid);
            if ($COURSE->id == $this->instance->pageid) {
                $course = $COURSE;
            } else {
                $course = get_record('course', 'id', $this->instance->pageid);
            }
        } else {
            $context = get_context_instance(CONTEXT_SYSTEM);
            $course = $SITE;
        }


		$this->content = new stdClass();
		$this->content->items = array();
		$this->content->icons = array();

        // print groups associate
        $footer = '<center><strong>'.(isset($this->config->title)?' '.$this->config->title:'').'</strong></center><br/>';
        $footer .= '<strong>'.get_string('group').':</strong> ';
		$this->content-> footer = $footer;
        
        if (has_capability('block/vgroupings:view', $this->context)) {
            $this->content->items[] = '<a href="'.new moodle_url('/blocks/vgroupings/grouping.php', array('course'=>$COURSE->id)).'">gerenciar agrupamentos</a>';
		    $this->content->icons[] = '<img src="'.$OUTPUT->pix_url('i/group').'" />';
        }
		return $this->content;
	}
    
	function instance_allow_config() {
		return true;
	}
    
	function instance_allow_multiple() {
		return false;
	}
    
	function has_config() {
		return true;
	}
    
}

