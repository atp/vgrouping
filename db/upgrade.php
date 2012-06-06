<?php

/**
 *
 * @param int $oldversion
 * @param object $block
 */
function xmldb_block_vgroupings_upgrade($oldversion) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();
    $result = true;
    
    if ($result && $oldversion < 2012031900) {
        $table = new xmldb_table('block_vgroupings_groups');
        $table->add_field('id', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL, NULL, NULL, 'id');
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL, NULL, NULL, 'userid');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL, NULL, NULL, 'groupid');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'), NULL, NULL);
        
        if (!$dbman->table_exists($table)) {
            if ($result = $dbman->create_table($table)) {
                // save point
                upgrade_block_savepoint(true, 2012031900, 'vgroupings');
            }
        }
    }
    if ($result && $oldversion < 2012041600) {
        $table = new xmldb_table('block_vgroupings_groups');
        $field_groupingid = new xmldb_field('groupingid', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL, NULL, 0, 'courseid');
        $dbman->add_field($table, $field_groupingid);
    }
    
    return $result;
}
