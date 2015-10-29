<?php

/**
 * Event observer.
 *
 * @package    local_groupmembersonly_observer
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/groupmembersonly/locallib.php');

/**
 * Event observer.
 * Stores all actions about modules create/update/delete in plugin own's table.
 * This allows the block to avoid expensive queries to the log table.
 *
 * @package    local_groupmembersonly_observer
 */
class local_groupmembersonly_observer {
    
    public static function cm_updated($event)
    {
        global $CFG;
        
        require_once($CFG->libdir . '/accesslib.php');

        $data = $event->get_data();
        // $other = $data["other"];

        if ($data["target"] != "course_module")
            return;
        
        refresh_availability($data);

        reload_all_capabilities();
        
        //mtrace('local_groupmembersonly_event::cm_updated ', $event->get_name());
        //mtrace('local_groupmembersonly_event::cm_updated ', $event->get_description());
    }

    public static function group_member_added($event)
    {
        //mtrace('local_groupmembersonly_event::group_member_added');
        gmo_group_member_changed($event->get_data());
    }

    public static function group_member_removed($event)
    {
        //mtrace('local_groupmembersonly_event::group_member_removed');
        gmo_group_member_changed($event->get_data());
    }
}
