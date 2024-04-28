<?php

// Load the necessary configuration, library files and namespaces.
require_once dirname(__FILE__) . '/../../config.php';
require_once dirname(__FILE__) . '/lib.php';
use mod_sqlab\grader;
use mod_sqlab\attempt_manager;

try {

    // Fetch necessary parameters from the request.
    $attemptid = optional_param('attempt', null, PARAM_INT);
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

    // Check for the existence of the attempt ID.
    if ($attemptid === null) {
        throw new moodle_exception('noattemptid', 'sqlab');
    }

} catch (moodle_exception $e) {

    // Define the redirect URL based on the exception and available data.
    if ($e->errorcode === 'invalidcoursemodule' && $cmid !== null) {
        $redirectUrl = new moodle_url('/my/');
    } else {
        if (!empty($cmid)) {
            $redirectUrl = new moodle_url('/mod/sqlab/view.php', ['id' => $cmid]);  // Redirect to the module's view page if cmid is available and valid.
        } else {
            $redirectUrl = (!empty($course->id)) ? new moodle_url('/course/view.php', ['id' => $course->id]) : new moodle_url('/my/');
        }
    }

    \core\notification::error(get_string($e->errorcode, $e->module));
    redirect($redirectUrl);
    exit;

}

// Enforce user login, course module context and check capabilities.
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/sqlab:attempt', $context);

try {

    // Attempt to grade the user's attempt and finalize it.
    grader::gradeAttempt($attemptid);
    attempt_manager::finalize_attempt($attemptid);

    // Redirect the user to the review page upon successful completion.
    redirect(new moodle_url('/mod/sqlab/review.php', ['attempt' => $attemptid, 'cmid' => $cmid]));

} catch (Exception $e) {
    error_log("error procesar intento: " . $e->getMessage());
    // Notify the user that an error occurred while the attempt was being processed.
    \core\notification::error(get_string('errorprocessattempt', 'sqlab'));

    // Define a redirection URL in case of error to view.
    $redirectUrl = new moodle_url('/mod/sqlab/view.php', ['id' => $cmid]);

    // Redirect user to view where they can get support or try again.
    redirect($redirectUrl);

}
