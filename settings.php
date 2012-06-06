<?php

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    // general settings 
    $settings->add(new admin_setting_heading('block_vgroupings_settings', '', get_string('pluginname_desc', 'block_vgroupings')));
    $settings->add(new admin_setting_heading('block_vgroupings_settingsheader', get_string('settingsheader', 'block_vgroupings'), ''));

    $options = array("true", "false");
    $options = array_combine($options, $options);
    $settings->add(new admin_setting_configselect('block_vgroupings/showall', get_string('showall', 'block_vgroupings'),
                                                  get_string('showall_desc', 'block_vgroupings'), 'true', $options));
    
}

