<?php


function xmldb_local_groupmembersonly_upgrade($oldversion=0) {
    global $CFG, $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    $result = true;

    if ($oldversion < 2015102104) {   
        
        $table = new xmldb_table('gmo_role_override');
        
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('gmo_avail', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('role_cap', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('avail_rolecap', XMLDB_KEY_UNIQUE, ['gmo_avail', 'role_cap']);
        $table->add_key('avail_fk', XMLDB_KEY_FOREIGN, ['gmo_avail'], 'gmo_availability', ['id'], "cascade");
        $table->add_key('rolecap_fk', XMLDB_KEY_FOREIGN, ['role_cap'], 'role_capabilities', ['id'], "cascade");

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        
    }


    if ($oldversion < 2015102107) {

        require_once($CFG->dirroot.'/local/groupmembersonly/locallib.php');
        
        $cms = $DB->get_records('course_modules');

        foreach ($cms as $cm) {
            $data = [];

            $data['objectid'] = $cm->id;

            debugging('GroupMembersOnly : Processing '.$cm->id);
            refresh_availability($data);
        }

    }

    if ($oldversion < 2015102109) {
        require_once($CFG->libdir.'/accesslib.php');
        
        $roleid = create_role("","gmo_groupmember", "Group Members Only plguin : group member role" ,"teacher");
        
        $DB->insert_record('role_context_levels',
                           ['roleid' => $roleid,
                           'contextlevel' => CONTEXT_MODULE]);
    }

    if ($oldversion < 2015102700) {        
        require_once($CFG->dirroot.'/local/groupmembersonly/locallib.php');
        require_once($CFG->libdir.'/accesslib.php');

        $gmo_roleid = $DB->get_field('role', 'id', ['shortname' => 'gmo_groupmember'], MUST_EXIST);
        
        $cms_sql = "SELECT cm.id, cm.availability, cm.course FROM {course_modules} cm
              JOIN {gmo_availability} ga ON ga.cmid = cm.id";
        

        $cms = $DB->get_records_sql($cms_sql, [] );
        
        foreach ($cms as $cmid => $cm)
            process_local_assign_groups_and_groupings($cm, "Assign");
    }

    return $result;
}