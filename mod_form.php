<?php

// Load the necessary configuration and library files.
require_once dirname(__FILE__) . '/../../course/moodleform_mod.php';
require_once dirname(__FILE__) . '/lib.php';

class mod_sqlab_mod_form extends moodleform_mod
{
    function definition()
    {
        global $CFG;

        // Initialize form object.
        $mform = $this->_form;

        // 'General' header. 
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // 'Activity name' field.
        $mform->addElement('text', 'name', get_string('name', 'sqlab'), ['size' => '50']);
        $mform->setType('name', PARAM_TEXT); // Parameter type: Text.
        $mform->addRule('name', null, 'required', null, 'client'); // Mandatory.
        $mform->applyFilter('name', 'trim'); // Remove blanks.

        // 'Quiz ID' field.
        $mform->addElement('text', 'quizid', get_string('quizid', 'sqlab'), ['size' => '20']);
        $mform->setType('quizid', PARAM_INT); // Parameter type: Integer.
        $mform->addRule('quizid', null, 'required', null, 'client'); // Mandatory.
        $mform->setDefault('quizid', ''); // Default value.
        $mform->addHelpButton('quizid', 'quizid_help', 'sqlab'); // Help button.

        // 'Security' header.
        $mform->addElement('header', 'securitysettings', get_string('securitysettings', 'sqlab'));

        // 'Activity password' field.
        $mform->addElement('passwordunmask', 'activitypassword', get_string('activitypassword', 'sqlab'));
        $mform->setType('activitypassword', PARAM_TEXT);

        // Standard form settings.
        $this->standard_coursemodule_elements();

        // Standard form buttons.
        $this->add_action_buttons();
    }

}
