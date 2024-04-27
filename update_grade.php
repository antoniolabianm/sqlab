<?php

// Load the necessary configuration and library files.
require_once dirname(__FILE__) . '/../../config.php';
require_once dirname(__FILE__) . '/lib.php';

try {

    // Fetch necessary parameters from the request.
    $attemptId = optional_param('attempt', null, PARAM_INT);
    $questionId = optional_param('question', null, PARAM_INT);
    $cmId = optional_param('cmid', null, PARAM_INT);
    $sqlCode = optional_param('sql_code', null, PARAM_TEXT);
    $evaluate = optional_param('evaluate', null, PARAM_BOOL);

    // Validate that necessary parameters are provided.
    if ($attemptId === null) {
        throw new moodle_exception('noattemptid', 'sqlab');
    }

    if ($questionId === null) {
        throw new moodle_exception('noquestionid', 'sqlab');
    }

    if ($cmId === null) {
        throw new moodle_exception('nocmid', 'sqlab');
    }

    if ($sqlCode === null) {
        throw new moodle_exception('nosqlcode', 'sqlab');
    }

    if ($evaluate === null) {
        throw new moodle_exception('noevaluate', 'sqlab');
    }

    $cm = get_coursemodule_from_id('sqlab', $cmId);
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

    // Check the existence of attempt and obtain it.
    $attempt = $DB->get_record('sqlab_attempts', ['id' => $attemptId, 'sqlabid' => $sqlab->id, 'userid' => $USER->id], '*', IGNORE_MISSING);
    if (!$attempt) {
        throw new moodle_exception('invalidattemptid', 'sqlab');
    }

} catch (moodle_exception $e) {

    // Define the redirect URL based on the exception and available data.
    if ($e->errorcode === 'invalidcoursemodule' && $cmId !== null) {
        $redirectUrl = new moodle_url('/my/');
    } else if ($e->errorcode === 'invalidsqlabid') {
        $redirectUrl = new moodle_url('/my/');
    } else {
        if (!empty($cmId)) {
            $redirectUrl = new moodle_url('/mod/sqlab/view.php', ['id' => $cmId]);  // Redirect to the module's view page if cmid is available and valid.
        } else {
            $redirectUrl = (!empty($course->id)) ? new moodle_url('/course/view.php', ['id' => $course->id]) : new moodle_url('/my/');
        }
    }

    \core\notification::error(get_string($e->errorcode, $e->module));
    redirect($redirectUrl);
    exit;

}

// Enforce course module context and check capabilities.
$context = context_module::instance($cm->id);
require_capability('mod/sqlab:attempt', $context);

// Get the related quiz questions.
$quiz_questions = sqlab_get_quiz_questions($sqlab->quizid);

// Redirection and error notification if no questions are found.
if (empty($quiz_questions)) {
    \core\notification::error(get_string('noquestionsfound', 'sqlab'));
    redirect(new moodle_url('/mod/sqlab/view.php', ['id' => $cmId]));
    exit;
}

// Find the current question based on the ID.
$current_question = null;
foreach ($quiz_questions as $question) {
    if ($question['questionid'] == $questionId) {
        $current_question = $question;
        break;
    }
}

// Redirection and error notification if the question is not found.
if (!$current_question) {
    \core\notification::error(get_string('questionnotfound', 'sqlab'));
    redirect(new moodle_url('/mod/sqlab/view.php', ['id' => $cmId]));
    exit;
}

// Check if the current request method is POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Proceed if the request is for evaluation, not just code execution (the ‘Evaluate code’ button has been clicked).
    if ($evaluate) {

        // Check if response exists for that question in that particular attempt for that particular user.
        $response = $DB->get_record('sqlab_responses', ['attemptid' => $attemptId, 'questionid' => $questionId, 'userid' => $USER->id]);

        // Create a new response if it doesn't exist.
        if (!$response) {

            $response = new stdClass();
            $response->attemptid = $attemptId;
            $response->questionid = $questionId;
            $response->userid = $USER->id;
            $response->response = $sqlCode;
            $response->currentmaxgrade = $current_question['questiongrade'];
            $response->execution_count = 0;
            $response->timecreated = time();
            $response->timemodified = $response->timecreated;
            $DB->insert_record('sqlab_responses', $response);

        } else { // Update the existing response.

            $response->response = $sqlCode;
            $response->execution_count++;
            $response->timemodified = time();
            $DB->update_record('sqlab_responses', $response);

        }

    }

    // If the condition evaluates to true, indicating success.
    echo json_encode(['success' => true]);

} else {

    // If the condition evaluates to false, indicating failure.
    echo json_encode(['success' => false]);

}
