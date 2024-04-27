<?php

// Load the necessary configuration and library files.
require_once dirname(__FILE__) . '/../../config.php';
require_once dirname(__FILE__) . '/lib.php';

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

// Enforce course module context and user login.
$context = context_module::instance($cm->id);
require_login($course, true, $cm);

// Check if there is a password for the activity or not.
$passwordRequired = !empty($sqlab->activitypassword);

// Check if password is required and the request method is POST.
if ($passwordRequired && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get password from POST data.
    $password = $_POST['password'] ?? '';

    // Get correct password from SQLab activity settings.
    $correctPassword = $sqlab->activitypassword;

    // Check if password is empty or incorrect.
    if (empty($password) || $password !== $correctPassword) {

        // Output JSON response for failure with error message.
        echo json_encode(['status' => 'fail', 'message' => get_string('passwordincorrect', 'sqlab')]);

    } else {

        // Output JSON response for success.
        echo json_encode(['status' => 'success']);
    }

} else {
    // Output JSON response for failure with invalid request message.
    echo json_encode(['status' => 'fail', 'message' => 'Invalid request']);
}
