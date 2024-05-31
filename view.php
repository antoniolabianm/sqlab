<?php

// Load the necessary configuration, library files and namespaces.
require_once dirname(__FILE__) . '/../../config.php';
require_once dirname(__FILE__) . '/lib.php';
use mod_sqlab\attempt_manager;

try {

    // Fetch and validate the necessary parameter from the request.
    $cmid = optional_param('id', null, PARAM_INT);

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

// Check if there is a password for the activity or not.
$passwordRequired = !empty($sqlab->activitypassword);

// Configure the page settings.
$PAGE->set_url('/mod/sqlab/view.php', array('id' => $cmid));
$PAGE->set_title(format_string($sqlab->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Linking SQLab specific stylesheet.
$PAGE->requires->css(new moodle_url('/mod/sqlab/styles/style.css'));

// Retrieve all attempt records for a specific SQLab instance and user.
$attempts = $DB->get_records('sqlab_attempts', ['sqlabid' => $sqlab->id, 'userid' => $USER->id], 'attempt DESC');

// Count the number of finished attempts.
$finishedAttemptsCount = count(array_filter($attempts, function ($a) {
    return $a->state === attempt_manager::FINISHED;
}));

// Determine the current in-progress attempt if any.
$currentAttempt = current(array_filter($attempts, function ($a) {
    return $a->state === attempt_manager::IN_PROGRESS;
}));

// Retrieve the maximum number of allowed attempts for the quiz.
$maxAttemptCount = sqlab_get_quiz_attempts_count($sqlab->quizid);

// See if the number of attempts is ‘unlimited’.
$isUnlimited = ($maxAttemptCount == 0);

// Check if a new attempt is allowed.
$allowNewAttempt = $isUnlimited || ($finishedAttemptsCount < $maxAttemptCount) && !$currentAttempt;

// Generate the continue attempt URL if an in-progress attempt exists.
$continueAttemptUrl = $currentAttempt ? (new moodle_url('/mod/sqlab/attempt.php', ['attempt' => $currentAttempt->id, 'cmid' => $cmid, 'page' => 0]))->out(false) : '';

// Output the continue attempt URL as a JavaScript variable.
echo "<script type='text/javascript'>var continueAttemptUrl = '{$continueAttemptUrl}';</script>";

// Output a hidden field indicating if a password is required.
echo ' <input type="hidden" id="passwordRequired" value="' . ($passwordRequired ? '1' : '0') . '">';

// Handle the password submission for a new attempt.
if ($passwordRequired && $_SERVER['REQUEST_METHOD'] === 'POST' && !$currentAttempt && $allowNewAttempt) {
    if (empty($_POST['password']) || $_POST['password'] !== $sqlab->activitypassword) {
        echo json_encode(['status' => 'fail', 'message' => get_string('passwordincorrect', 'sqlab')]);
        exit;
    } else {
        echo json_encode(['status' => 'success']);
        exit;
    }
}

// Load JavaScript language strings required for the module.
$PAGE->requires->strings_for_js(array('passwordempty', 'unexpectederror', 'ajaxerror'), 'sqlab');

// Output the standard Moodle page header.
echo $OUTPUT->header();

// Display previous attempts and their details if they exist.
if ($attempts) {

    // Sort attempts in order of creation.
    usort($attempts, function ($a, $b) {
        return $a->attempt - $b->attempt;
    });

    // Begin the display of attempts summary.
    echo html_writer::start_tag('div', array('class' => 'sqlab-attempts-summary'));
    echo html_writer::tag('h3', get_string('previousattempts', 'sqlab'));

    // Create a table to display attempt details.
    $table = new html_table();
    $table->head = array(get_string('attempt', 'sqlab'), get_string('state', 'sqlab'), get_string('grade', 'sqlab'), get_string('review', 'sqlab'));

    foreach ($attempts as $attempt) {
        $state = attempt_manager::check_attempt_state($attempt->id);

        $reviewCellContent = $state === attempt_manager::FINISHED ?
            html_writer::link(
                new moodle_url('/mod/sqlab/review.php', array('attempt' => $attempt->id, 'cmid' => $cmid)),
                get_string('reviewlinktext', 'sqlab')
            ) : '-';

        $gradeCellContent = $state === attempt_manager::FINISHED ?
            format_float($attempt->sumgrades, 2) : '-';

        $row = new html_table_row(
            array(
                $attempt->attempt,
                get_string($state, 'sqlab'),
                $gradeCellContent,
                $reviewCellContent
            )
        );

        $table->data[] = $row;
    }

    // End the display of attempts summary.
    echo html_writer::table($table);
    echo html_writer::end_tag('div');

}

// Provide additional hidden information about the course module ID.
echo ' <div id="cmidContainer" style="display:none;">' . json_encode($cmid) . '</div>';

// Check and provide options for starting or continuing attempts.
if ($allowNewAttempt || $currentAttempt) {

    // Start the display of the attempt button.
    $buttonHtml = html_writer::start_tag('div', ['class' => 'sqlab-attempt-button']);

    $buttonAction = $currentAttempt ? 'continue' : 'start';
    $buttonLabel = $currentAttempt ? get_string('continueattempt', 'sqlab') : get_string('startnewattempt', 'sqlab');

    $buttonHtml .= html_writer::tag('button', $buttonLabel, ['id' => 'attemptButton', 'class' => 'btn btn-primary', 'data-action' => $buttonAction]);

    // Display the attempt button.
    echo $buttonHtml;

    // Display a message if a password is required for the attempt.
    if ($passwordRequired) {
        echo html_writer::tag('p', get_string('sqlabpasswordrequired', 'sqlab'), ['style' => 'margin-top: 15px; margin-bottom: 0px;']);
    }

    // Display information about the permitted attempts.
    $attemptsMessage = $isUnlimited ? get_string('unlimitedattempts', 'sqlab') : get_string('permittedattempts', 'sqlab') . $maxAttemptCount;
    echo html_writer::tag('p', $attemptsMessage, ['style' => 'margin-top: 15px; margin-bottom: 0px;']);

    // End the display of the attempt button section.
    echo html_writer::end_tag('div');

} else {

    // Display a message if no more attempts are allowed.
    echo html_writer::tag('p', get_string('nomoreattempts', 'sqlab'), ['style' => 'margin-top: 15px; margin-bottom: 0px;']);
    echo html_writer::tag('p', get_string('permittedattempts', 'sqlab') . $maxAttemptCount, ['style' => 'margin-top: 15px; margin-bottom: 0px;']);

}

// Handle the display of the modal for password entry if a new attempt is allowed and no current attempt exists.
if (!$currentAttempt && $allowNewAttempt) {
    echo '
    <div id="modalDialog" class="modal">
        <div class="modal-content animate-top">
            <div class="modal-header">
                <h5 class="modal-title">' . get_string('passwordmodaltitle', 'sqlab') . '</h5>
                <button type="button" class="close" onclick="modal.style.display = \'none\'">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <form id="passwordForm" method="post">
                <div class="modal-body">
                    <p>' . get_string('enterpassword', 'sqlab') . '</p>
                    <div class="input-group" style="max-width: 300px;">
                        <input type="password" name="password" id="modalPassword" class="form-control" placeholder="' . get_string('passwordmodaltitle', 'sqlab') . '">
                        <input type="hidden" name="id" value="' . $cmid . '" />
                        <div class="input-group-append">
                            <button id="togglePassword" class="btn btn-outline-secondary" type="button">
                                <i class="fa fa-eye-slash" aria-hidden="true"></i>
                            </button>
                        </div>
                        <p id="passwordError" style="color: red; font-size: small; display: none;"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn" onclick="modal.style.display = \'none\'">' . get_string('closemodalpassword', 'sqlab') . '</button>
                    <button type="submit" class="btn btn-primary">' . get_string('sendpassword', 'sqlab') . '</button>
                </div>
            </form>
        </div>
    </div>';
}

// Include required JavaScript libraries.
echo ' <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>';

// Handle JavaScript initialization.
echo "<script type='text/javascript'>M.util.js_pending('password_modal');</script>";
echo "<script type='text/javascript'>M.util.js_pending('attempt_actions');</script>";

// Include JavaScript files necessary for password handling and attempt actions.
if ($passwordRequired) {
    echo ' <script src="' . new moodle_url('/mod/sqlab/js/password_modal.js') . '"></script>';
}

echo ' <script src="' . new moodle_url('/mod/sqlab/js/attempt_actions.js') . '"></script>';

// Complete JavaScript initialization.
echo "<script type='text/javascript'>M.util.js_complete('password_modal');</script>";
echo "<script type='text/javascript'>M.util.js_complete('attempt_actions');</script>";

// Output the standard Moodle page footer.
echo $OUTPUT->footer();
