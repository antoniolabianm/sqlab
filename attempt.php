<?php

// Load the necessary configuration, library files and namespaces.
require_once dirname(__FILE__) . '/../../config.php';
require_once dirname(__FILE__) . '/lib.php';
use mod_sqlab\schema_manager;

try {

    // Fetch and validate necessary parameters from the request.
    $cmid = optional_param('cmid', null, PARAM_INT);
    $attemptid = optional_param('attempt', null, PARAM_INT);
    $page = optional_param('page', 0, PARAM_INT);

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

    // Get attempt from database.
    $attempt = $DB->get_record('sqlab_attempts', array('id' => $attemptid), '*', IGNORE_MISSING);

    // Check attempt ownership and existence.
    if (!$attempt) {
        throw new moodle_exception('invalidattemptid', 'sqlab');
    }

    if ($attempt->userid != $USER->id) {
        throw new moodle_exception('notyourattempt', 'sqlab');
    }

    // Get and check the existence of the SQLab instance.
    $sqlab = $DB->get_record('sqlab', array('id' => $cm->instance), '*', IGNORE_MISSING);
    if (!$sqlab) {
        throw new moodle_exception('invalidsqlabid', 'sqlab');
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
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability('mod/sqlab:attempt', $context);

// Configure the page settings.
$PAGE->set_url('/mod/sqlab/attempt.php', array('attempt' => $attemptid, 'cmid' => $cmid, 'page' => $page));
$PAGE->set_title(format_string($sqlab->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$formatoptions = new stdClass;
$formatoptions->noclean = true; // This allows the HTML not to be cleaned up.
$formatoptions->overflowdiv = true; // This ensures that long content is handled correctly.
$formatoptions->context = $context; // The current context for applying appropriate permissions.
$formatoptions->filter = false; // Disables filter processing for this call.

// Linking SQLab specific stylesheet.
$PAGE->requires->css(new moodle_url('/mod/sqlab/styles/style.css'));

// Loading core CodeMirror JS from CDN.
$PAGE->requires->js(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/codemirror.min.js'), true);

// Linking core CodeMirror CSS from CDN.
$PAGE->requires->css(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/codemirror.min.css'));

// Loading PostgreSQL mode for CodeMirror from CDN.
$PAGE->requires->js(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/mode/sql/sql.min.js'), true);

// List of CodeMirror themes to be included.
$themes = [
    '3024-day',
    '3024-night',
    'abbott',
    'abcdef',
    'ambiance',
    'ayu-dark',
    'ayu-mirage',
    'base16-dark',
    'base16-light',
    'bespin',
    'blackboard',
    'cobalt',
    'colorforth',
    'darcula',
    'dracula',
    'duotone-dark',
    'duotone-light',
    'eclipse',
    'elegant',
    'erlang-dark',
    'gruvbox-dark',
    'hopscotch',
    'icecoder',
    'idea',
    'isotope',
    'lesser-dark',
    'liquibyte',
    'lucario',
    'material',
    'material-darker',
    'material-palenight',
    'material-ocean',
    'mbo',
    'mdn-like',
    'midnight',
    'monokai',
    'moxer',
    'neat',
    'neo',
    'night',
    'nord',
    'oceanic-next',
    'panda-syntax',
    'paraiso-dark',
    'paraiso-light',
    'pastel-on-dark',
    'railscasts',
    'rubyblue',
    'seti',
    'shadowfox',
    'solarized',
    'ssms',
    'the-matrix',
    'tomorrow-night-bright',
    'tomorrow-night-eighties',
    'ttcn',
    'twilight',
    'vibrant-ink',
    'xq-dark',
    'xq-light',
    'yeti',
    'yonce',
    'zenburn'
];

// Loop through themes and link each CSS file from CDN.
foreach ($themes as $theme) {
    $PAGE->requires->css(new moodle_url("https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/theme/$theme.min.css"));
}

// Include CodeMirror CSS and JS for fullscreen mode.
$PAGE->requires->css(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/addon/display/fullscreen.min.css'));
$PAGE->requires->js(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/addon/display/fullscreen.min.js'), true);

// Include CodeMirror CSS and JS for hints.
$PAGE->requires->js(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/addon/hint/anyword-hint.min.js'), true);
$PAGE->requires->css(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/addon/hint/show-hint.min.css'));
$PAGE->requires->js(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/addon/hint/show-hint.min.js'), true);
$PAGE->requires->js(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/addon/hint/sql-hint.min.js'), true);

// Include CodeMirror JS for bracket matching features.
$PAGE->requires->js(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/addon/edit/matchbrackets.min.js'), true);

// Include CodeMirror JS for automatic bracket closing.
$PAGE->requires->js(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/addon/edit/closebrackets.min.js'), true);

// Retrieve the list of quiz questions from the database (SQL Question/s).
$quiz_questions = sqlab_get_quiz_questions($sqlab->quizid);

// Redirection and error notification if no questions are found.
if (empty($quiz_questions)) {
    \core\notification::error(get_string('noquestionsfound', 'sqlab'));
    redirect(new moodle_url('/mod/sqlab/view.php', ['id' => $cmid]));
    exit;
}

// Validate and adjust the current page number.
$page = max(0, min($page, count($quiz_questions) - 1));

// Display the Moodle page header.
echo $OUTPUT->header();

// Fetch and display the current question.
$current_question = $quiz_questions[$page];
$question_id = 'question-' . $current_question['questionid'];

// Format the question grade to two decimal places.
$formatted_grade = number_format((float) $current_question['questiongrade'], 2);

// Start of div wrapper to set flex display.
echo ' <div style="display: flex;">';

// Start of main content div.
echo ' <div style="flex: 1; padding-right: 15px;">';

// Start of the div for the main question with its unique ID.
echo ' <div id="' . $question_id . '" class="que shortanswer deferredfeedback notyetanswered">';

// Question information section.
echo ' <div class="info">';
echo ' <h3 class="no">' . get_string('question', 'sqlab') . ' <span class="qno">' . ($page + 1) . '</span></h3>'; // Display the question number.
echo ' <div class="grade">' . get_string('scoresas', 'sqlab') . ' ' . $formatted_grade . '</div>'; // Display the formatted grade.
echo ' </div>';

// Start of question content section.
echo ' <div class="content">';

// Start of question formulation section.
echo ' <div class="formulation clearfix">';

// Display the statement.
echo format_text($current_question['statement'], FORMAT_MOODLE, $formatoptions);

// Accordion sections for SQL results, related concepts, and hints.
echo ' <div class="accordion-container">';
echo ' <h2 class="accordion-title">' . get_string('sqlresults', 'sqlab') . '</h2>';
echo ' <div class="accordion-content">';
echo " <div id='resultDataContainer' class='sql-query-results'></div>";
echo ' </div>';
echo ' </div>';

$formatoptions->filter = true; // Activate all filters for this section.
echo ' <div class="accordion-container">';
echo ' <h2 class="accordion-title">' . get_string('relatedconcepts', 'sqlab') . '</h2>';
echo ' <div class="accordion-content">' . format_text($current_question['relatedconcepts'], FORMAT_HTML, $formatoptions) . '</div>';
echo ' </div>';
$formatoptions->filter = false; // Resets the filter processing for other sections.

echo ' <div class="accordion-container">';
echo ' <h2 class="accordion-title">' . get_string('hints', 'sqlab') . '</h2>';
echo ' <div class="accordion-content">' . format_text($current_question['code'], FORMAT_HTML, $formatoptions) . '</div>';
echo ' </div>';

// CodeMirror editor setup.
echo ' <div class="ablock">';
echo ' <label for="myCodeMirror"></label>';
echo ' <div class="code-editor-container">';

// Theme selector for CodeMirror.
echo '<select id="themeSelector"><option value="" disabled selected>' . get_string('editorthemes', 'sqlab') . '</option>';
foreach ($themes as $theme) {
    echo '<option value="' . $theme . '">' . $theme . '</option>';
}
echo ' </select>';

// Font size selector for CodeMirror.
echo ' <select id="fontSizeSelector" onchange="changeFontSize(this.value)">';
echo ' <option value="" disabled selected>' . get_string('fontsize', 'sqlab') . '</option>';
for ($i = 10; $i <= 30; $i += 2) {
    echo ' <option value="' . $i . 'px">' . $i . '</option>';
}
echo ' </select>';

// CodeMirror editor.
echo ' <textarea id="myCodeMirror" class="form-control" data-question-id="' . $question_id . '"></textarea>';

echo ' </div>'; // Close code-editor-container div.
echo ' </div>'; // Close ablock div.

// Div for code editor actions.
echo ' <div class="code-editor-actions">';
echo ' <button id="executeSqlButton" type="button" class="btn btn-primary">' . get_string('runcode', 'sqlab') . '</button>';
echo ' <button id="evaluateSqlButton" type="button" class="btn btn-success">' . get_string('evaluatecode', 'sqlab') . '</button>';
echo ' <button id="infoButton" type="button" class="btn btn-secondary"><i class="fa fa-question"></i></button>';
echo ' <div id="infoText">' . get_string('beforefinish', 'sqlab') . '</div>';
echo ' </div>';

// Div to display the results of code execution.
echo ' <div id="sqlQueryResults" class="sql-query-results"></div>';

echo ' </div>'; // Close formulation div.
echo ' </div>'; // Close content div.
echo ' </div>'; // Close main div (unique ID).

// Navigation script and summary URL setup.
$summaryUrl = new moodle_url('/mod/sqlab/summary.php', array('attempt' => $attemptid, 'cmid' => $cmid));
$summaryUrlString = $summaryUrl->out(false);

// Small script to add delay so that the data is loaded correctly into the database.
echo '
<script>
function redirectToSummary() {
    setTimeout(function() {
        window.location.href = "' . $summaryUrlString . '";
    }, 1500);
}
</script>';

// Question navigation buttons.
echo ' <div class="navigation-buttons">';
if ($page > 0) {
    echo ' <a href="?attempt=' . $attemptid . '&cmid=' . $cmid . '&page=' . ($page - 1) . '" class="mod_quiz-prev-nav btn btn-secondary">' . get_string('previouspage', 'sqlab') . '</a>';
}
if ($page < count($quiz_questions) - 1) {
    echo ' <a href="?attempt=' . $attemptid . '&cmid=' . $cmid . '&page=' . ($page + 1) . '" class="mod_quiz-next-nav btn btn-primary">' . get_string('nextpage', 'sqlab') . '</a>';
} else {
    echo ' <button class="mod_quiz-next-nav btn btn-primary" onclick="redirectToSummary()">' . get_string('finishattempt', 'sqlab') . '</button>';
}
echo ' </div>';

echo ' </div>'; // Close main content div.

// Question navigation.
echo '<div id="question-nav" class="question-navigation">';
echo '<h5>' . get_string('questionnavtittle', 'sqlab') . '</h5>';
echo '<div class="nav-links">';

foreach ($quiz_questions as $index => $question) {
    $isSelected = ($page == $index) ? 'font-weight: bold; background-color: #e7f1ff; border-left: 4px solid #007bff;' : 'border-left: 4px solid transparent;';
    $questionPageUrl = new moodle_url('/mod/sqlab/attempt.php', array('attempt' => $attemptid, 'cmid' => $cmid, 'page' => $index));
    echo '<a href="' . $questionPageUrl->out() . '" style="display: block; margin-bottom: 2.5px; padding: 8px 12px; ' . $isSelected . ' text-decoration: none; color: #333; border-radius: 4px; transition: background-color 0.3s;">' . get_string('question', 'sqlab') . ' ' . ($index + 1) . '</a>';
}

echo ' </div>'; // Close nav-links div.
echo ' </div>'; // Close question-nav div.

echo ' </div>'; // Close flex div.

// Hidden divs for JS use.
echo " <div id='resultDataSql' style='display:none;'>" . htmlspecialchars($current_question['resultdata'], ENT_QUOTES, 'UTF-8') . "</div>";
echo " <div id='resultDataUserId' style='display:none;'>" . $USER->id . "</div>";
echo " <div id='resultDataSchema' style='display:none;'>" . htmlspecialchars(schema_manager::format_activity_name($sqlab->name), ENT_QUOTES, 'UTF-8') . "</div>";
echo ' <div id="attemptIdContainer" style="display:none;">' . json_encode($attemptid) . '</div>';
echo ' <div id="questionIdContainer" style="display:none;">' . json_encode($current_question['questionid']) . '</div>';
echo ' <div id="cmidContainer" style="display:none;">' . json_encode($cmid) . '</div>';

// JS scripts.
echo '<script src="' . new moodle_url('/mod/sqlab/js/codemirror_config.js') . '"></script>';
echo '<script src="' . new moodle_url('/mod/sqlab/js/localstorage.js') . '"></script>';
echo '<script src="' . new moodle_url('/mod/sqlab/js/sql_executor.js') . '"></script>';
echo '<script src="' . new moodle_url('/mod/sqlab/js/accordion_display.js') . '"></script>';
echo '<script src="' . new moodle_url('/mod/sqlab/js/grade_manager.js') . '"></script>';
echo '<script src="' . new moodle_url('/mod/sqlab/js/context_resultdata_executor.js') . '"></script>';
echo '<script src="' . new moodle_url('/mod/sqlab/js/info_button.js') . '"></script>';

// Display the Moodle page footer.
echo $OUTPUT->footer();
