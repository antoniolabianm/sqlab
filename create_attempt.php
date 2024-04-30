<?php

// Load the necessary configuration, library files and namespaces.
require_once dirname(__FILE__) . '/../../config.php';
require_once dirname(__FILE__) . '/lib.php';
use mod_sqlab\schema_manager;
use mod_sqlab\internal_sql_executor;
use mod_sqlab\attempt_manager;

try {

    // Fetch and validate the necessary parameter from the request.
    $cmid = optional_param('cmid', null, PARAM_INT);

    // Validate course module and additional data.
    if ($cmid === null) {
        throw new moodle_exception('nocmid', 'sqlab');
    }

    $cm = get_coursemodule_from_id('sqlab', $cmid);
    if (!$cm) {
        throw new moodle_exception('invalidcoursemodule', 'sqlab');
    }

    $course = get_course($cm->course);
    if (!$course) {
        throw new moodle_exception('invalidcourseid', 'sqlab');
    }

    // Get and check the existence of the SQLab instance.
    $sqlab = $DB->get_record('sqlab', array('id' => $cm->instance), '*', IGNORE_MISSING);
    if (!$sqlab) {
        throw new moodle_exception('invalidsqlabid', 'sqlab');
    }

} catch (moodle_exception $e) {

    // Define the redirect URL.
    $redirectUrl = (!empty($course->id)) ? new moodle_url('/course/view.php', ['id' => $course->id]) : new moodle_url('/my/');

    \core\notification::error(get_string($e->errorcode, $e->module));
    redirect($redirectUrl);
    exit;

}

// Enforce user login, course module context and check capabilities.
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability('mod/sqlab:view', $context);
require_capability('mod/sqlab:attempt', $context);

// Check if the 'action' POST parameter is set and if the action is to 'create_new_attempt'.
if (isset($_POST['action']) && $_POST['action'] === 'create_new_attempt') {

    try {

        // Create a new attempt using the attempt manager class with the necessary IDs.
        $newattemptid = attempt_manager::create_new_attempt($sqlab->id, $USER->id);

        // Handle the setup for the first attempt.
        schema_manager::handle_first_attempt($newattemptid, $USER->id);

        // Retrieve quiz questions.
        $quiz_questions = sqlab_get_quiz_questions($sqlab->quizid);

        // Check if there are no quiz questions returned.
        if (empty($quiz_questions)) {
            echo json_encode(['status' => 'error', 'message' => get_string('noquestionsfound', 'sqlab')]);
            exit;
        }

        // Format the schema name based on the SQLab name.
        $schemaName = schema_manager::format_activity_name($sqlab->name);

        // Create the relational schema for each question.
        foreach ($quiz_questions as $question) {
            internal_sql_executor::execute($USER->id, $question['relationalschema'], $schemaName, false, null, false);
        }

        // Prepare the context for each question, inserting data into the relational schema.
        foreach ($quiz_questions as $question) {
            internal_sql_executor::execute($USER->id, $question['data'], $schemaName);
        }

        // Respond with a success status and include the new attempt ID.
        echo json_encode(['status' => 'success', 'newattemptid' => $newattemptid]);

    } catch (Exception $e) {

        // Catch any exceptions that occur during the attempt creation and respond with an error.
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;

    }

} else {

    // Respond with an error if the 'action' parameter is not set or not equal to 'create_new_attempt'.
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;

}
