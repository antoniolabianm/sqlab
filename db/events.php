<?php

// Prevent unauthorized script access.
defined('MOODLE_INTERNAL') || die();

// Observers array for handling specific Moodle events.
$observers = array(
    array(
        'eventname' => '\core\event\role_assigned', // Listen for role assignment events.
        'callback' => '\mod_sqlab\observer::handle_role_assigned', // Callback function for event.
    ),
);
