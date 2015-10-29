<?php

function xmldb_local_groupmembersonly_install() {
    global $CFG, $DB;

    require_once($CFG->dirroot.'/local/groupmembersonly/locallib.php');
    require_once($CFG->libdir.'/accesslib.php');
    
    $cms = $DB->get_records('course_modules');
    
    foreach ($cms as $cm) {
        $data = [];
        
        $data['objectid'] = $cm->id;

        debugging('GroupMembersOnly : Processing '.$cm->id, DEBUG_DEVELOPER);
        refresh_availability($data);
    }

    $roleid = create_role("","gmo_groupmember", "Group Members Only plguin : group member role" ,"teacher");

    $DB->insert_record('role_context_levels',
                       ['roleid' => $roleid,
                       'contextlevel' => CONTEXT_MODULE]);
}
