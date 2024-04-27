<?php

namespace mod_sqlab;

defined('MOODLE_INTERNAL') || die();

class observer
{
    /**
     * Handles role assignments by delegating to the database manager.
     *
     * @param \core\event\base $event Contains data about the role assignment.
     */
    public static function handle_role_assigned($event)
    {
        database_manager::handle_role_assignment($event->get_data());
    }
}
