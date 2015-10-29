<?php

defined('MOODLE_INTERNAL') || die();

$observers = array(
 
    array(
        'eventname'   => 'core\event\course_module_updated',
        'callback'    => 'local_groupmembersonly_observer::cm_updated',
        'internal'  => false, // This means that we get events only after transaction commit.
        'priority'  => 1000,
    ),
    array(
        'eventname'   => '\core\event\course_module_created',
        'callback'    => 'local_groupmembersonly_observer::cm_updated',
        'internal'  => false, // This means that we get events only after transaction commit.
        'priority'  => 1000,
    ), 
     array(
        'eventname'   => '\core\event\group_member_added',
        'callback'    => 'local_groupmembersonly_observer::group_member_added',
        'internal'  => false, // This means that we get events only after transaction commit.
        'priority'  => 1000,
    ),
    array(
        'eventname'   => '\core\event\group_member_removed',
        'callback'    => 'local_groupmembersonly_observer::group_member_removed',
        'internal'  => false, // This means that we get events only after transaction commit.
        'priority'  => 1000,
    ), 
);

