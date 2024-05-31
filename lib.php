<?php

// Prevent PHP scripts from being accessed directly from the browser.
defined('MOODLE_INTERNAL') || die();

/**
 * Determines the features supported by the SQLab module.
 *
 * @param string $feature The constant name of the feature being checked.
 * @return mixed Returns true or false for most features, specific values for certain features,
 *               or null if the feature is not recognized.
 */
function sqlab_supports($feature)
{
    $features = [
        FEATURE_GROUPS => false, // No support for group assignments.
        FEATURE_GROUPINGS => false, // No support for groupings.
        FEATURE_MOD_INTRO => false, // No introductory text field.
        FEATURE_COMPLETION_TRACKS_VIEWS => false, // Does not interact with completion views.
        FEATURE_COMPLETION_HAS_RULES => false, // No additional completion rules.
        FEATURE_GRADE_HAS_GRADE => true,  // Supports grades.
        FEATURE_GRADE_OUTCOMES => true,  // Supports grading outcomes.
        FEATURE_BACKUP_MOODLE2 => true,  // Supports Moodle 2 backup and restore.
        FEATURE_SHOW_DESCRIPTION => true,  // Shows description on course page.
        FEATURE_ADVANCED_GRADING => false, // No support for advanced grading methods.
        FEATURE_CONTROLS_GRADE_VISIBILITY => true  // Controls visibility of grades.
    ];

    if (array_key_exists($feature, $features)) {
        return $features[$feature];
    }

    if ($feature == FEATURE_MOD_PURPOSE && defined('FEATURE_MOD_PURPOSE')) {
        return MOD_PURPOSE_ASSESSMENT; // Defines module as 'assessment' type.
    }

    return null; // Feature not recognized.
}

/**
 * Adds a new SQLab instance to the database.
 *
 * @param object $data An object containing the data for the new SQLab instance, including the quiz ID.
 * @return int The ID of the newly created SQLab instance.
 * @throws coding_exception Throws exception if the Quiz ID is invalid.
 */
function sqlab_add_instance($data)
{
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;

    if (empty($data->quizid) || !is_numeric($data->quizid)) {
        throw new coding_exception('Invalid Quiz ID');
    }

    $id = $DB->insert_record('sqlab', $data);

    return $id;
}

/**
 * Updates an existing SQLab instance in the database.
 *
 * @param object $data An object containing the new data for the SQLab instance, including the instance ID.
 * @return bool Returns true upon successful update of the instance.
 * @throws coding_exception Throws exception if the instance ID is missing in the data.
 */
function sqlab_update_instance($data)
{
    global $DB;

    if (empty($data->instance)) {
        throw new coding_exception('Instance ID is missing');
    }

    $data->id = $data->instance;

    $data->timemodified = time();

    $DB->update_record('sqlab', $data);

    return true;
}

/**
 * Deletes a specific instance of an SQLab record by ID.
 *
 * @param int $id The ID of the SQLab instance to be deleted.
 * @return bool Returns true if the record was successfully deleted, false if the record does not exist.
 */
function sqlab_delete_instance($id)
{
    global $DB;

    if (!$DB->record_exists('sqlab', array('id' => $id))) {
        return false;
    }

    $DB->delete_records('sqlab', array('id' => $id));

    return true;
}

/**
 * Retrieves detailed information for all questions associated with a specific quiz.
 *
 * @param int $quizid The ID of the quiz for which questions are being retrieved.
 * @return array An array of associative arrays, each representing a quiz question's detailed information.
 */
function sqlab_get_quiz_questions($quizid)
{
    global $DB;

    $questions = [];

    $sql = "SELECT q.id AS questionid, q.questiontext, q.name AS questionname, q.createdby, u.firstname, u.lastname, 
                slot.maxmark AS questiongrade,
                qso.relatedconcepts, qso.relationalschema, qso.data, qso.code, qso.resultdata, 
                qso.subjectivedifficulty, qso.objectivedifficulty, qso.solution,
                qso.decreaseattempt, qso.mingrade, qso.sqlcheck, qso.sqlcheckrun
            FROM {quiz_slots} slot
            LEFT JOIN {question_references} qr ON qr.component = 'mod_quiz' AND qr.questionarea = 'slot' AND qr.itemid = slot.id
            LEFT JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
            LEFT JOIN (
                SELECT qv1.questionbankentryid, qv1.questionid
                FROM {question_versions} qv1
                WHERE qv1.id IN (
                    SELECT MAX(qv2.id)
                    FROM {question_versions} qv2
                    GROUP BY qv2.questionbankentryid
                )
            ) qv ON qv.questionbankentryid = qbe.id
            LEFT JOIN {question} q ON q.id = qv.questionid
            LEFT JOIN {user} u ON q.createdby = u.id
            LEFT JOIN {qtype_sqlquestion_options} qso ON qso.questionid = q.id
            WHERE slot.quizid = ?;";

    $params = [$quizid];
    $question_records = $DB->get_records_sql($sql, $params);

    foreach ($question_records as $record) {
        $questions[] = [
            'questionid' => $record->questionid,
            'statement' => $record->questiontext,
            'questionname' => $record->questionname,
            'authorid' => $record->createdby,
            'author' => $record->firstname . ' ' . $record->lastname,
            'questiongrade' => $record->questiongrade,
            'relatedconcepts' => $record->relatedconcepts,
            'relationalschema' => $record->relationalschema,
            'data' => $record->data,
            'code' => $record->code,
            'resultdata' => $record->resultdata,
            'subjectivedifficulty' => $record->subjectivedifficulty,
            'objectivedifficulty' => $record->objectivedifficulty,
            'solution' => $record->solution,
            'decreaseattempt' => $record->decreaseattempt,
            'mingrade' => $record->mingrade,
            'sqlcheck' => $record->sqlcheck,
            'sqlcheckrun' => $record->sqlcheckrun
        ];
    }

    return $questions;
}

/**
 * Retrieves the number of attempts for a specified quiz.
 *
 * @param int $quizid The ID of the quiz for which the attempt count is being retrieved.
 * @return int The number of attempts for the specified quiz.
 * @throws \moodle_exception Throws an exception if the quiz cannot be found in the database.
 */
function sqlab_get_quiz_attempts_count($quizid)
{
    global $DB;

    $sql = "SELECT attempts FROM {quiz} WHERE id = ?";
    $params = [$quizid];
    $quiz = $DB->get_record_sql($sql, $params);

    if ($quiz) {
        return $quiz->attempts;
    } else {
        throw new \moodle_exception('Quiz not found for ID: ' . $quizid);
    }
}

/**
 * Determines whether a specific question has been answered by a user within a given attempt.
 *
 * @param int $attemptid The ID of the attempt.
 * @param int $questionid The ID of the question.
 * @param int $userid The ID of the user.
 * @return bool Returns true if a valid response exists, false otherwise.
 */
function sqlab_is_question_answered($attemptid, $questionid, $userid)
{
    global $DB;

    $response = $DB->get_record(
        'sqlab_responses',
        array(
            'attemptid' => $attemptid,
            'questionid' => $questionid,
            'userid' => $userid
        )
    );

    return !empty($response) && !empty($response->response);
}
