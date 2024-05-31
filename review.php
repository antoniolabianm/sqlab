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
require_capability('mod/sqlab:view', $context);

// Configure the page settings.
$PAGE->set_url(new moodle_url('/mod/sqlab/review.php', ['cmid' => $cmid, 'attempt' => $attemptid]));
$PAGE->set_title(format_string($sqlab->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$formatoptions = new stdClass;
$formatoptions->noclean = true; // This allows the HTML not to be cleaned up.
$formatoptions->overflowdiv = true; // This ensures that long content is handled correctly.
$formatoptions->context = $context; // The current context for applying appropriate permissions.
$formatoptions->filter = false; // Disables filter processing for this call.

// Including JavaScript files for syntax highlighting and custom JavaScript.
$PAGE->requires->js(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/prism/9000.0.1/prism.min.js'), true);
$PAGE->requires->js(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/prism/9000.0.1/components/prism-sql.min.js'), true);
$PAGE->requires->js(new moodle_url('/mod/sqlab/js/accordion_display.js'), true);

// Including CSS files for styling.
$PAGE->requires->css(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/prism/9000.0.1/themes/prism.min.css'));
$PAGE->requires->css(new moodle_url('/mod/sqlab/styles/style.css'));

// Get the related quiz questions.
$questions = sqlab_get_quiz_questions($sqlab->quizid);

// Obtain the total score of the activity.
$totalPossibleGrade = 0;
foreach ($questions as $question) {
    $totalPossibleGrade += $question['questiongrade'];
}

// Obtain the score obtained by the student in the activity.
$totalObtainedGrade = $attempt->sumgrades;

// Calculate the percentage of points obtained with respect to the total points obtained.
$percentageGrade = $totalPossibleGrade > 0 ? ($totalObtainedGrade / $totalPossibleGrade) * 100 : 0;

// Formatting total, obtained, and percentage grades.
$formattedTotalGrade = format_float($totalPossibleGrade, 2);
$formattedObtainedGrade = format_float($totalObtainedGrade, 2);
$formattedPercentageGrade = format_float($percentageGrade, 0);

// Generating a grade summary string.
$gradeSummary = get_string('gradesummary', 'sqlab', [
    'obtained' => $formattedObtainedGrade,
    'total' => $formattedTotalGrade,
    'percentage' => $formattedPercentageGrade
]);

// Output the standard Moodle page header.
echo $OUTPUT->header();

// Using a table to display attempt details with styled columns.
echo ' <div style="margin-bottom: 10px;">';
$table = new html_table();
$table->attributes['class'] = 'generaltable generalbox quizreviewsummary';
$table->attributes['style'] = 'width: 100%;';

// Define styles for the table headers.
$labelStyle = 'background-color: #f0f0f0; font-weight: bold; white-space: nowrap; text-align: right; width: 150px;';
$valueStyle = 'background-color: #fafafa; white-space: nowrap;';

// Add row definitions.
$table->data = array(
    new html_table_row(
        array(
            new html_table_cell(get_string('startedon', 'sqlab')),
            new html_table_cell(userdate($attempt->timestart))
        )
    ),
    new html_table_row(
        array(
            new html_table_cell(get_string('state', 'sqlab')),
            new html_table_cell($attempt->timefinish ? get_string('finished', 'sqlab') : get_string('inprogress', 'sqlab'))
        )
    ),
    new html_table_row(
        array(
            new html_table_cell(get_string('completedon', 'sqlab')),
            new html_table_cell(userdate($attempt->timefinish))
        )
    ),
    new html_table_row(
        array(
            new html_table_cell(get_string('timetaken', 'sqlab')),
            new html_table_cell(format_time($attempt->timefinish - $attempt->timestart))
        )
    ),
    new html_table_row(
        array(
            new html_table_cell(get_string('grade', 'sqlab')),
            new html_table_cell($gradeSummary)
        )
    ),
);

// Apply styles to cells.
foreach ($table->data as $row) {
    $row->cells[0]->style = $labelStyle;
    $row->cells[1]->style = $valueStyle;
}

echo html_writer::table($table);
echo ' </div>';

// Enhanced display of each question.
$questionNumber = 1; // Initialize question number counter.
foreach ($questions as $question) {

    // Retrieve response for the question.
    $response = $DB->get_record('sqlab_responses', ['attemptid' => $attemptid, 'questionid' => $question['questionid']]);

    // Get the feedback given by the SQL function.
    $userFeedback = $response ? $response->feedback : get_string('no_response_feedback', 'sqlab');

    // Calculate user's obtained grade for the question.
    $userGradeObtained = $response ? round($response->gradeobtained, 2) : 0.00;

    // Obtain total grade for the question.
    $questionTotalGrade = round($question['questiongrade'], 2);

    // Retrieve user's response or indicate if no response is provided.
    $userResponse = (isset($response) && trim($response->response) !== '') ? htmlspecialchars_decode($response->response, ENT_QUOTES) : get_string('noresponseprovided', 'sqlab');

    // Retrieve solution for the question.
    $solution = htmlspecialchars_decode($question['solution'], ENT_QUOTES);

    // Output HTML for question display.
    echo '  <div class="que shortanswer deferredfeedback notyetanswered">';
    echo '      <div class="info">';
    echo '          <h3 class="no">' . get_string('question', 'sqlab') . ' <span class="qno">' . $questionNumber++ . '</span></h3>';
    echo '          <div class="grade">' . get_string('gradereview', 'sqlab', (object) ['usergrade' => $userGradeObtained, 'totalgrade' => $questionTotalGrade]) . '</div>';
    echo '      </div>';
    echo '      <div class="content">';
    echo '          <div class="formulation clearfix">';
    echo '              <div>' . format_text($question['statement'], FORMAT_MOODLE, $formatoptions) . '</div>';
    echo '              <div class="accordion-container">';
    echo '                  <h2 class="accordion-title">' . get_string('userresponsereview', 'sqlab') . '</h2>';
    echo '                  <div class="accordion-content">';
    echo '                      <pre><code class="language-sql">' . $userResponse . '</code></pre>';
    echo '                  </div>';
    echo '              </div>';
    echo '              <div class="accordion-container">';
    echo '                  <h2 class="accordion-title">' . get_string('solutionreview', 'sqlab') . '</h2>';
    echo '                  <div class="accordion-content">';
    echo '                      <pre><code class="language-sql">' . $solution . '</code></pre>';
    echo '                  </div>';
    echo '              </div>';
    echo '              <div class="accordion-container">';
    echo '                  <h2 class="accordion-title">' . get_string('feedbackreview', 'sqlab') . '</h2>';
    echo '                  <div class="accordion-content">';
    echo '                      <div>' . $userFeedback . '</div>';
    echo '                  </div>';
    echo '              </div>';
    echo '          </div>';
    echo '      </div>';
    echo '  </div>';

}

// Link to view when you finish the review.
$reviewUrl = new moodle_url('/mod/sqlab/view.php', array('id' => $cmid));
echo ' <div class="submitbtns" style="text-align: right;"><a class="mod_quiz-next-nav" href="' . $reviewUrl->out() . '">' . get_string('finishreview', 'sqlab') . '</a></div>';

// Delete the answers stored in localStorage for this attempt.
echo "<script>
document.addEventListener('DOMContentLoaded', function() {
    var questionIds = " . json_encode(array_column($questions, 'questionid')) . ";
    questionIds.forEach(function(questionId) {
        localStorage.removeItem('response_' + questionId);
    });
});
</script>";

// Output the standard Moodle page footer.
echo $OUTPUT->footer();
