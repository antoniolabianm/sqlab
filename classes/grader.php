<?php

namespace mod_sqlab;

defined('MOODLE_INTERNAL') || die();

class grader
{
    /**
     * Grades a specific attempt based on its ID by evaluating the answers to its associated questions.
     *
     * @param int $attemptid The ID of the attempt to grade.
     * @throws \moodle_exception Throws moodle_exception for specific errors controlled by the application.
     * @throws \Exception Throws generic Exception for uncontrolled or external errors.
     */
    public static function gradeAttempt($attemptid)
    {
        global $DB;

        // Load the attempt details.
        $attempt = $DB->get_record('sqlab_attempts', ['id' => $attemptid]);
        if (!$attempt) {
            throw new \moodle_exception('attemptnotfound', 'sqlab');
        }

        // Retrieve the responses for the attempt.
        $questions = $DB->get_records('sqlab_responses', ['attemptid' => $attemptid, 'userid' => $attempt->userid]);

        // Load the SQLab instance data.
        $sqlab = $DB->get_record('sqlab', ['id' => $attempt->sqlabid]);
        if (!$sqlab) {
            throw new \moodle_exception('sqlabnotfound', 'sqlab');
        }

        // Validate the schema name.
        $schemaName = schema_manager::format_activity_name($sqlab->name);
        if (empty($schemaName)) {
            throw new \moodle_exception('schemanameerror', 'sqlab');
        }

        // Prepare database connection using user's specific credentials.
        $userCredentials = $DB->get_record('sqlab_db_user_credentials', ['userid' => $attempt->userid]);
        if (!$userCredentials) {
            throw new \moodle_exception('nocredentials', 'sqlab');
        }

        $studentDbName = str_replace("ROLE_", "", $userCredentials->username);
        $dbConnector = new dbconnector($studentDbName);
        $studentDBConnection = $dbConnector->connect();

        if (!$studentDBConnection) {
            throw new \moodle_exception('dbconnectionerror', 'sqlab');
        }

        try {

            // Initialize flag to prevent schema deletion on error.
            $deleteSchema = false;

            // Remove the existing scheme to ensure a clean state of the scheme.
            schema_manager::drop_schema($studentDBConnection, $schemaName);

            // Recreate the scheme, preparing it to evaluate the attempt.
            schema_manager::create_schema_for_activity($studentDBConnection, $schemaName, $userCredentials->username);

            // Initialize total grade for the attempt.
            $totalGrade = 0.0;

            // Evaluate each question response and calculate the total grade.
            foreach ($questions as $response) {
                $result = self::gradeQuestion($studentDBConnection, $response, $schemaName, $sqlab);
                $questionGrade = (float) $result['grade'];

                // Store the individual grades and feedback for each response.
                $DB->set_field('sqlab_responses', 'gradeobtained', $questionGrade, ['id' => $response->id]);
                $DB->set_field('sqlab_responses', 'feedback', $result['feedback'], ['id' => $response->id]);

                $totalGrade += $questionGrade;
            }

            // Update the total grade for the attempt in the database.
            $DB->set_field('sqlab_attempts', 'sumgrades', $totalGrade, ['id' => $attemptid]);

            // Set flag to true as all operations completed successfully.
            $deleteSchema = true;

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        } finally {
            // Conditionally drop the schema if all operations were successful.
            if ($deleteSchema) {
                schema_manager::drop_schema($studentDBConnection, $schemaName);
            }

            // Always close the database connection to free resources.
            $dbConnector->closeConnection();
        }
    }

    /**
     * Grades an individual question based on the student's SQL response.
     *
     * @param resource $dbConnection The database connection resource.
     * @param stdClass $response The response object containing details like the student's submitted answer.
     * @param string $schemaName The name of the database schema where the queries are to be executed.
     * @param stdClass $sqlab Instance data containing quiz and question identifiers.
     * @return array Returns an array with 'grade' and 'feedback' keys.
     * @throws \moodle_exception Throws moodle_exception for specific errors controlled by the application.
     * @throws \Exception Throws generic Exception for uncontrolled or external errors.
     */
    protected static function gradeQuestion($dbConnection, $response, $schemaName, $sqlab)
    {
        // Return zero grade if student's response is empty.
        if (empty($response->response)) {
            return ['grade' => 0.0, 'feedback' => get_string('no_response_feedback', 'sqlab')];
        }

        // Get all questions from the quiz.
        $quiz_questions = sqlab_get_quiz_questions($sqlab->quizid);

        if (empty($quiz_questions)) {
            throw new \moodle_exception('noquestionsfound', 'sqlab');
        }

        // Find the specific question for the current response.
        $question = null;
        foreach ($quiz_questions as $q) {
            if ($q['questionid'] == $response->questionid) {
                $question = $q;
                break;
            }
        }

        if (!$question) {
            throw new \moodle_exception('questionnotfound', 'sqlab');
        }

        // Calculate the total penalty for executions.
        $totalDecrease = (float) $response->execution_count * $question['decreaseattempt'];

        // Calculate the new maximum possible score after the penalty.
        $newMaxGrade = (float) $response->currentmaxgrade - $totalDecrease;

        // Ensure that the new maximum grade is not lower than the minimum grade allowed.
        $maxGrade = (float) max($newMaxGrade, $question['mingrade']);

        // Generate and prepare relational schema and data if available.
        if (!empty($question['relationalschema'])) {
            internal_sql_executor::execute($response->userid, $question['relationalschema'], $schemaName, false, $dbConnection);
        }

        if (!empty($question['data'])) {
            internal_sql_executor::execute($response->userid, $question['data'], $schemaName, false, $dbConnection);
        }

        // Create the solution given by the student in the clean database.
        internal_sql_executor::execute($response->userid, $response->response, $schemaName, false, $dbConnection);

        // Create the function 'compare_views_with_order_and_diff'.
        self::generateEvaluatorFunction($dbConnection);

        // Create the views.
        internal_sql_executor::execute($response->userid, $question['sqlcheck'], $schemaName, false, $dbConnection);

        // Compare the views using the SQL function.
        $result = pg_query($dbConnection, $question['sqlcheckrun']);

        if (!$result) {
            $error = pg_last_error($dbConnection);
            throw new \Exception($error);
        }

        // Using regular expression to extract view names.
        if (preg_match("/compare_views_with_order_and_diff\('(.+?)', '(.+?)'\)/", $question['sqlcheckrun'], $matches) && count($matches) === 3) {
            $view1 = $matches[1];
            $view2 = $matches[2];
        } else {
            // Throw an exception if the names of the views could not be extracted from the query.
            throw new \moodle_exception('view_names_not_found', 'sqlab');
        }

        $allRowsCorrect = true; // Initialize flag to true, assuming all rows are initially correct.
        $feedbackDetails = []; // Array to store HTML details for feedback.
        $rows = []; // Array to store each row's HTML representation.

        while ($row = pg_fetch_assoc($result)) {

            if ($row['is_row_correct'] !== 't') {
                $allRowsCorrect = false; // Set flag to false if any row is not correct.
            }

            // Construct row HTML for display.
            $rows[] = "<tr class='sql-results-row'>\n" .
                "<td class='sql-results-data'>{$row['norder']}</td>\n" .
                "<td class='sql-results-data'>" . ($row['is_row_correct'] === 't' ? get_string('yes', 'sqlab') : get_string('no', 'sqlab')) . "</td>\n" .
                "<td class='sql-results-data'>{$row['is_in_solution']}</td>\n" .
                "<td class='sql-results-data'>" . ($row['view1_row'] ? htmlspecialchars($row['view1_row']) : get_string('not_present', 'sqlab')) . "</td>\n" .
                "<td class='sql-results-data'>" . ($row['view2_row'] ? htmlspecialchars($row['view2_row']) : get_string('not_present', 'sqlab')) . "</td>\n" .
                "</tr>\n";

        }

        if (!$allRowsCorrect) {

            // Add table header.
            $feedbackDetails[] = "<table class='sql-query-results'>\n" .
                "<tr class='sql-results-header'>\n" .
                "<th class='sql-results-header'>" . get_string('row', 'sqlab') . "</th>\n" .
                "<th class='sql-results-header'>" . get_string('is_correct', 'sqlab') . "</th>\n" .
                "<th class='sql-results-header'>" . get_string('status', 'sqlab') . "</th>\n" .
                "<th class='sql-results-header'>" . get_string('your_answer', 'sqlab') . "</th>\n" .
                "<th class='sql-results-header'>" . get_string('expected_answer', 'sqlab') . "</th>\n" .
                "</tr>\n";

            // Add rows to the table.
            foreach ($rows as $rowHtml) {
                $feedbackDetails[] = $rowHtml;
            }

            // Close the HTML table structure.
            $feedbackDetails[] = "</table>\n";

        }

        // Clean up views to avoid future conflicts.
        pg_query($dbConnection, "DROP VIEW IF EXISTS $view1, $view2;");

        // Return the results of the comparison and the score.
        if ($allRowsCorrect) {
            return ['grade' => $maxGrade, 'feedback' => get_string('all_rows_correct_feedback', 'sqlab')];
        } else {
            $detailedFeedback = implode("\n", $feedbackDetails);
            return ['grade' => 0.0, 'feedback' => $detailedFeedback];
        }
    }

    /**
     * Ensures the evaluator function is present in the PostgreSQL database.
     * If the function does not exist, it is created from an SQL file.
     *
     * @param resource $dbConnection The database connection resource.
     * @throws \moodle_exception Throws moodle_exception for specific errors controlled by the application.
     */
    protected static function generateEvaluatorFunction($dbConnection)
    {
        // Define the path to the SQL file containing the function definition.
        $functionSqlPath = __DIR__ . '/../db/functions.sql';

        // Check if the SQL file exists and throw an exception if not.
        if (!file_exists($functionSqlPath)) {
            throw new \moodle_exception('sql_file_not_exist', 'sqlab', '', null, $functionSqlPath);
        }

        // Load the SQL query from the file.
        $functionSql = file_get_contents($functionSqlPath);
        if (!$functionSql) {
            throw new \moodle_exception('unable_to_load_sql', 'sqlab');
        }

        // Create or replace the function in the database.
        $createFunction = pg_query($dbConnection, $functionSql);
        if (!$createFunction) {
            throw new \moodle_exception('function_creation_failed', 'sqlab', '', null, pg_last_error($dbConnection));
        }
    }
}
