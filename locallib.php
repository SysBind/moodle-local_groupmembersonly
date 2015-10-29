<?php

require_once($CFG->dirroot.'/group/lib.php');

/*
  activate_role_override - deny viewhiddenactivities at course_module level
  @param int $contextid
  @param int $roleid
  @param string $cap
  @param int $permission (CAP_PREVENT, CAP_ALLOW, ..)
  @retunr false | new cap id
 */
function activate_role_override($contextid, $roleid, $cap, $permission)
{
    global $USER, $DB;

    if (! $DB->record_exists('role_capabilities',
                             ['contextid' => $contextid,
                              'roleid' => $roleid,
                              'capability' => $cap,
                              'permission' => $permission]) )
    {
        $modifierid = NULL;
        
        if (property_exists($USER, 'id'))
            $modifierid = $USER->id;

        try {
            $rolecapid = $DB->insert_record('role_capabilities', 
                                        ['contextid' => $contextid,
                                         'roleid' => $roleid,
                                         'capability' => $cap,
                                         'permission' => $permission,
                                         'timemodified' => time(),
                                         'modifierid' => $modifierid],
                                        true);
        } catch (Exception $ex) {
            debugging('caught exception', DEBUG_DEVELOPER);
            var_dump($ex);
            debugging('caught exception '.$ex->getMessage(), DEBUG_DEVELOPER);
        }

        return $rolecapid;
    }

    return false;
}

/*
  deactivate_role_override - drop viewhiddenactivities overrides at course_module level
  @param int $contextid
  @param int $roleid
  @param string $cap
  @param int $permission (CAP_PREVENT, CAP_ALLOW, ..)
  @return bool
 */
function deactivate_role_override($contextid, $roleid, $cap, $permission)
{
    global $DB;
    
    if ($DB->record_exists('role_capabilities',
                             ['contextid' => $contextid,
                              'roleid' => $roleid,
                              'capability' => $cap,
                              'permission' => $permission]) )
    {
        $DB->delete_records('role_capabilities', 
                           ['contextid' => $contextid,
                            'roleid' => $roleid,
                            'capability' => $cap,
                            'permission' => $permission]);

        return true;
    }

    return false;
}

/*
  override_roles_hide_cm - deny viewhiddenactivities at course_module level
  @param stdClass $cm course module
  @param int $gmo_availid id of gmo_availability
 */
function override_roles_hide_cm($cm, $gmo_availid)
{
    global $DB, $USER;
    
    $context = context_module::instance($cm->id);

    //$roles = get_overridable_roles($context);

    $roles = $DB->get_records('role');

    foreach ($roles as $role) {
        // operate only on teacher archetype, do not operate on gmo_groupmember
        if ( $role->shortname == "gmo_groupmember" ||
             ! ( ( $role->archetype == "teacher") || ($role->archetype == "editingteacher") ) )
            continue;
        
        if ($rolecapid = activate_role_override($context->id, $role->id, "moodle/course:viewhiddenactivities", CAP_PREVENT))
            $DB->insert_record('gmo_role_override',
                               ['gmo_avail' => $gmo_availid, 'role_cap' => $rolecapid]);
        
    }

    $gmo_roleid = $DB->get_field('role', 'id', ['shortname' => 'gmo_groupmember'], MUST_EXIST);

    if ($rolecapid = activate_role_override($context->id, $gmo_roleid, "moodle/course:viewhiddenactivities", CAP_ALLOW))
        $DB->insert_record('gmo_role_override',
                           ['gmo_avail' => $gmo_availid, 'role_cap' => $rolecapid]);
}

/*
  override_roles_show_cm - drop override caps viewhiddenactivities at course_module level
  @param stdClass $cm course module
  @param int $gmo_availid id of gmo_availability
 */
function override_roles_show_cm($cm, $gmo_availid)
{
    global $DB;

    $context = context_module::instance($cm->id);
    
    $gmo_overrides = $DB->get_records('gmo_role_override', ['gmo_avail' => $gmo_availid]);

    foreach ($gmo_overrides as $override)
    {
        $roleid = $DB->get_field('role_capabilities', 'roleid', ['id' => $override->role_cap]); 
        if (deactivate_role_override($context->id, $roleid, "moodle/course:viewhiddenactivities", CAP_PREVENT))
            $DB->delete_records('gmo_role_override', ["id" => $override->id]);
    }
}

/* Locally assign the role gmo_groupmember to all teacher members of group

   This will grant them the viewhiddenactivities permission, so that they 
   can view this course module even if restricted by other availability condition

   @param stdClass $cm course module
   @param int $groupid
 */
function locally_assign_groupmembers($cm, $groupid)
{
    global $DB;
    
    $context = context_module::instance($cm->id);
    
    $gmo_roleid = $DB->get_field('role', 'id', ['shortname' => 'gmo_groupmember'], MUST_EXIST);

    $group_members = groups_get_members_by_role($groupid, $cm->course, 'u.id');

    if ($group_members)
    {
        $effected_roles = $DB->get_records_select('role', "archetype LIKE '%teacher' AND NOT name='gmo_groupmember'");
        
        foreach ($group_members as $data) {
            if (property_exists($data, 'id') && array_key_exists($data->id, $effected_roles))
                foreach ($data->users as $user)
                    role_assign($gmo_roleid, $user->id, $context->id, 'local_groupmembersonly');
        }
    }
}

/* Locally unassign the role gmo_groupmember from all teacher members of group

   @param stdClass $cm course module
   @param int $groupid
 */
function locally_unassign_groupmembers($cm, $groupid)
{
    global $DB;
    
    $context = context_module::instance($cm->id);
    
    $gmo_roleid = $DB->get_field('role', 'id', ['shortname' => 'gmo_groupmember'], MUST_EXIST);

    $group_members = groups_get_members_by_role($groupid, $cm->course, 'u.id');

    if ($group_members)
    {
        $effected_roles = $DB->get_records_select('role', "archetype LIKE '%teacher' AND NOT name='gmo_groupmember'");
        
        foreach ($group_members as $data) {
            if (array_key_exists($data->id, $effected_roles))
                foreach ($data->users as $user)
                    role_unassign($gmo_roleid, $user->id, $context->id, 'local_groupmembersonly');
        }
    }
}

// workaround to access protected groupid / groupingid in availability\condition classes
function gmo_extract_property($object, $property)
{    
    $str = serialize($object);
    $startpos = strpos($str, $property) + strlen($property);

    $str = substr($str, $startpos);
    $startpos = strpos($str, "i:") + 2;

    $str = substr($str, $startpos);
    $endpos = strpos($str, ";");

    $value = substr($str, 0, $endpos);

    return intval($value);
}


/* Returns all groups involved in this availability field

   @param string availability
   @return array of group ids | false
 */
function get_all_groups_in_availability_condition($availability)
{
    global $DB;

    $ids = [];
    
    $atree = new core_availability\tree( json_decode($availability) );

    list($innernot, $andoperator) = $atree->get_logic_flags(false);

    // Don't handle negative group conditions:
    if ($innernot)
        return false;
    
    // Groups:    
    $group_conds = $atree->get_all_children('availability_group\condition');
    
    foreach ($group_conds as $group_condition) {
        $id = gmo_extract_property($group_condition, 'groupid');

        if ($id != 0)
            $ids[] = $id;
    }


    // Groupings:    
    $grouping_conds = $atree->get_all_children('availability_grouping\condition');
    
    foreach ($grouping_conds as $grouping_condition) {
        $groupingid = gmo_extract_property($grouping_condition, 'groupingid');
        $groups = $DB->get_records('groupings_groups',
                                   ['groupingid' => $groupingid]);
        
        foreach ($groups as $group)
            if ($group->groupid != 0)
                $ids[] = $group->groupid;
    }

    return ( count($ids) > 0 ) ? $ids : false;
}

/* Process local role assignments (gmo_groupmember) for course module

   @param stdClass $cm course module
   @param string $action Assign / Unassign
   @return bool - any groups processed
 */
function process_local_assign_groups_and_groupings($cm, $action, $availability = NULL)
{
    global $DB;
    
    if (! $availability)
        $availability = $cm->availability;

    $groups_ids = get_all_groups_in_availability_condition($availability);

    if (! $groups_ids)
        return false;

    if ($action == "Assign")
        foreach ($groups_ids as $groupid)
            locally_assign_groupmembers($cm, $groupid);
    else if ($action == "Unassign")
        foreach ($groups_ids as $groupid)
            locally_unassign_groupmembers($cm, $groupid);

    return true;
}
    
/*
  refresh_availability - if availability modified then refresh role_overrides
  (core\event\course_module_updated And core\event\course_module_created)  
  @param array $data event data
 */
function refresh_availability($data)
{
    global $DB;

    try {
        $cm = $DB->get_record('course_modules', [ 'id' => $data["objectid"] ], "*", MUST_EXIST);

        if (! strpos($cm->availability, 'group')) {
            if ( $gmo_availability = $DB->get_record('gmo_availability', ['cmid' => $cm->id], "*", IGNORE_MISSING) )
            {
                if ( ($gmo_availability->availability != $cm->availability) &&
                     (strpos($gmo_availability->availability, 'group') ) )
                {
                    process_local_assign_groups_and_groupings($cm, "Unassign", $gmo_availability->availability);
                    
                    $gmo_availability->availability = $cm->availability;
                    $DB->update_record('gmo_availability', $gmo_availability);
                    override_roles_show_cm($cm, $gmo_availability->id); 
                }
            }
        }
        else
        {           
            if (! $gmo_availability = $DB->get_record('gmo_availability', ['cmid' => $cm->id], "*", IGNORE_MISSING) )
            {
                $have_groups = process_local_assign_groups_and_groupings($cm, "Assign");
                
                $gmo_availability = new stdClass();
                
                $gmo_availability->cmid = $cm->id;
                $gmo_availability->availability = $cm->availability;            
                $gmo_availability->id = $DB->insert_record('gmo_availability', $gmo_availability, true);
                if ($have_groups)
                    override_roles_hide_cm($cm, $gmo_availability->id);
            } else {
                if ($cm->availability != $gmo_availability->availability) {

                    // First unassign previous gmo_groupmember local roles assignments:
                    if (strpos($gmo_availability->availability, 'group'))
                        process_local_assign_groups_and_groupings($cm, "Unassign", $gmo_availability->availability);

                    // Now assign new gmo_groupmember local roles assignments:
                    $have_groups = process_local_assign_groups_and_groupings($cm, "Assign");
                    
                    $gmo_availability->availability = $cm->availability;
                    
                    $DB->update_record('gmo_availability', $gmo_availability);

                    if ($have_groups)
                        override_roles_hide_cm($cm, $gmo_availability->id);
                    else
                        override_roles_show_cm($cm, $gmo_availability->id);
                }
            }
        }
    } catch (Exception $ex) { 
        debugging("Exception ".$ex->getMessage(), DEBUG_NORMAL);
    }
}

/* Respond to group changes (\core\event\group_member_removed And \core\event\group_member_added)
 */
function gmo_group_member_changed($data)
{
    global $DB;

    if ( $data['target'] != "group_member" )
        return;

    $groupid = $data['objectid'];
    $userid = $data['relateduserid'];

    $courseid = $DB->get_field('groups', 'courseid', ['id' => $groupid], MUST_EXIST);

    $effected_roles = $DB->get_records_select('role', "archetype LIKE '%teacher' AND NOT name='gmo_groupmember'");
    
    $roles = get_user_roles(context_course::instance($courseid), $userid);

    $effected = false;
    foreach ($roles as $role)
        if (array_key_exists($role->id, $effected_roles)) {
            $effected = true;
            break;
        }
    
    if (! $effected)
        return;

    $gmo_roleid = $DB->get_field('role', 'id', ['shortname' => 'gmo_groupmember'], MUST_EXIST);
    
    $cms_sql = "SELECT cm.id, cm.availability FROM {course_modules} cm
              JOIN {gmo_availability} ga ON ga.cmid = cm.id
              WHERE cm.course = :courseid";

    $cms = $DB->get_records_sql($cms_sql, ['courseid' => $courseid] );

    foreach ($cms as $cmid => $cm) {
        // Check if course module available to this group
        if (in_array($groupid, get_all_groups_in_availability_condition($cm->availability)))
        {
            $context = context_module::instance($cmid);
            
            if ($data['action'] == "removed")
                role_unassign($gmo_roleid, $userid, $context->id, 'local_groupmembersonly');
            else if ($data['action'] == "added")
                role_assign($gmo_roleid, $userid, $context->id, 'local_groupmembersonly');
        }
    }
}
