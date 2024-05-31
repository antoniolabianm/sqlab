<?php

// Load the necessary configuration and library files.
require_once dirname(__FILE__) . '/../../config.php';
require_once dirname(__FILE__) . '/lib.php';

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

    // Get and check the existence of the SQLab instance.
    $sqlab = $DB->get_record('sqlab', array('id' => $cm->instance), '*', IGNORE_MISSING);
    if (!$sqlab) {
        throw new moodle_exception('invalidsqlabid', 'sqlab');
    }

    // Check the existence of attempt and obtain it.
    $attempt = $DB->get_record('sqlab_attempts', array('id' => $attemptid, 'sqlabid' => $sqlab->id, 'userid' => $USER->id), '*', IGNORE_MISSING);
    if (!$attempt) {
        throw new moodle_exception('invalidattemptid', 'sqlab');
    }

} catch (moodle_exception $e) {

    // Define the redirect URL based on the exception and available data.
    if ($e->errorcode === 'invalidcoursemodule' && $cmid !== null) {
        $redirectUrl = new moodle_url('/my/');
    } else if ($e->errorcode === 'invalidsqlabid') {
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
require_capability('mod/sqlab:submit', $context);

// Configure the page settings.
$PAGE->set_url('/mod/sqlab/summary.php', array('attempt' => $attemptid, 'cmid' => $cmid));
$PAGE->set_title(format_string($sqlab->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Linking SQLab specific stylesheet.
$PAGE->requires->css(new moodle_url('/mod/sqlab/styles/style.css'));

// Output the standard Moodle page header.
echo $OUTPUT->header();

// Display the name of the activity and a descriptive title of the page.
echo $OUTPUT->heading(format_string($sqlab->name), 2);
echo $OUTPUT->heading(get_string('attemptsummary', 'sqlab'), 3);

// Get the related quiz questions.
$quiz_questions = sqlab_get_quiz_questions($sqlab->quizid);

// Redirection and error notification if no questions are found.
if (empty($quiz_questions)) {
    \core\notification::error(get_string('noquestionsfound', 'sqlab'));
    redirect(new moodle_url('/mod/sqlab/view.php', ['id' => $cmId]));
    exit;
}

// Start of div containing the table with the summary of the questions.
echo ' <div style="margin-top: 20px;">';

// Table to show the summary of the questions of the attempt.
$table = new html_table();
$table->head = array(get_string('question', 'sqlab'), get_string('state', 'sqlab'));

foreach ($quiz_questions as $index => $question) {

    // Check if the question has been answered.
    $question_state = sqlab_is_question_answered($attemptid, $question['questionid'], $USER->id) ? get_string('saved', 'sqlab') : get_string('notsaved', 'sqlab');

    $row = new html_table_row(
        array(
            $index + 1,
            $question_state
        )
    );

    $table->data[] = $row;
}

echo html_writer::table($table);

echo ' </div>'; // Close div with summary.

// Add spacing and centring of buttons.
echo ' <div style="margin-top: 10px; text-align: start;">';

// Page number of the last question.
$last_question_index = count($quiz_questions) - 1;

// Button to return to the attempt.
echo ' <a href="' . new moodle_url('/mod/sqlab/attempt.php', array('attempt' => $attemptid, 'cmid' => $cmid, 'page' => $last_question_index)) . '" class="mod_quiz-prev-nav btn btn-secondary" style="margin-right: 15px;">' . get_string('returntoattempt', 'sqlab') . '</a>';

// Fetch the latest 'evaluate' action record for the given attempt and user.
$latest_eval = $DB->get_record_sql(
    "SELECT *
     FROM {sqlab_code_executions}
     WHERE attemptid = :attemptid AND userid = :userid AND action = 'evaluate'
     ORDER BY execution_timestamp DESC
     LIMIT 1",
    array('attemptid' => $attemptid, 'userid' => $USER->id)
);

// Check if the latest evaluation contains SQL syntax errors.
if (!empty($latest_eval) && strpos($latest_eval->received_reply, 'ERROR:') !== false) {
    echo '<div style="color: red; margin-top: 20px;">' . get_string('sqlsyntaxerror', 'sqlab') . '</div>';
    echo $OUTPUT->footer();
    exit;
}

// Button to send and end the attempt.
echo ' <a href="' . new moodle_url('/mod/sqlab/processattempt.php', array('attempt' => $attemptid, 'cmid' => $cmid)) . '" class="mod_quiz-next-nav btn btn-primary">' . get_string('submitandfinish', 'sqlab') . '</a>';

echo ' </div>'; // Close button div.

// Output the standard Moodle page footer.
echo $OUTPUT->footer();
