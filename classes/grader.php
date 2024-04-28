<?php

namespace mod_sqlab;

defined('MOODLE_INTERNAL') || die();

class grader
{
    /**
     * Grades a specific attempt based on its ID by evaluating the answers to its associated questions.
     *
     * @param int $attemptid The ID of the attempt to grade.
     * @throws moodle_exception Throws exceptions for database connection errors, missing credentials, or other database-related errors.
     */
    public static function gradeAttempt($attemptid)
    {
        global $DB;

        // Load the attempt details.
        $attempt = $DB->get_record('sqlab_attempts', ['id' => $attemptid]);
        if (!$attempt) {
            throw new \moodle_exception('attemptnotfound', 'sqlab', '', "Attempt not found with ID {$attemptid}");
        }

        // Retrieve the responses for the attempt.
        $questions = $DB->get_records('sqlab_responses', ['attemptid' => $attemptid, 'userid' => $attempt->userid]);
        if (empty($questions)) {
            throw new \moodle_exception('noresponsesfound', 'sqlab', '', "No responses found for attempt ID {$attemptid}");
        }

        // Load the SQLab instance data.
        $sqlab = $DB->get_record('sqlab', ['id' => $attempt->sqlabid]);
        if (!$sqlab) {
            throw new \moodle_exception('sqlabnotfound', 'sqlab', '', "SQLab instance not found with ID {$sqlab->id}");
        }

        // Validate the schema name.
        $schemaName = schema_manager::format_activity_name($sqlab->name);
        if (empty($schemaName)) {
            throw new \moodle_exception('schemanameerror', 'sqlab', '', "Error formatting schema name for SQLab ID {$sqlab->id}");
        }

        // Prepare database connection using user's specific credentials.
        $userCredentials = $DB->get_record('sqlab_db_user_credentials', ['userid' => $attempt->userid]);
        if (!$userCredentials) {
            throw new \moodle_exception('nocredentials', 'sqlab', '', "No credentials found for user ID {$attempt->userid}");
        }

        $studentDbName = str_replace("ROLE_", "", $userCredentials->username);
        $dbConnector = new dbconnector($studentDbName);
        $studentDBConnection = $dbConnector->connect();

        if (!$studentDBConnection) {
            throw new \moodle_exception('dbconnectionerror', 'sqlab', '', "Cannot connect to database for SQLab instance with ID {$sqlab->id}");
        }

        try {

            // Drop the old schema.
            schema_manager::drop_schema($studentDBConnection, $schemaName);

            // Recreate a clean schema.
            schema_manager::create_schema_for_activity($studentDBConnection, $schemaName, $userCredentials->username);

            // Evaluate each question to obtain an overall assessment of the attempt.
            $totalGrade = 0.0;
            foreach ($questions as $response) {
                $result = self::gradeQuestion($studentDBConnection, $response, $schemaName, $sqlab);
                $questionGrade = (float) $result['grade'];

                $DB->set_field('sqlab_responses', 'gradeobtained', $questionGrade, ['id' => $response->id]);
                $DB->set_field('sqlab_responses', 'feedback', $result['feedback'], ['id' => $response->id]);

                $totalGrade += $questionGrade;
            }

            // Update the total grade for the attempt.
            $DB->set_field('sqlab_attempts', 'sumgrades', $totalGrade, ['id' => $attemptid]);

        } catch (\Exception $e) {
            throw new \moodle_exception('dberror', 'sqlab', '', "Transaction failed on attempt ID {$attemptid}: " . $e->getMessage());
        } finally {
            schema_manager::drop_schema($studentDBConnection, $schemaName);
            $dbConnector->closeConnection(); // Close the student's database connection.
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
     * @throws moodle_exception Throws exceptions if quiz questions are not found, the specific question is not available, or any database errors occur.
     */
    protected static function gradeQuestion($dbConnection, $response, $schemaName, $sqlab)
    {
        // Return zero grade if student's response is empty.
        if (empty($response->response)) {
            return ['grade' => 0.0, 'feedback' => get_string('no_response_provided', 'sqlab')];
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
            throw new \moodle_exception('questionnotfound', 'sqlab', '', "No question found for question ID {$response->questionid}");
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
            throw new \moodle_exception('dberror', 'sqlab', '', "Comparison failed: $error");
        }

        // Using regular expression to extract view names.
        if (preg_match("/compare_views_with_order_and_diff\('(.+?)', '(.+?)'\)/", $question['sqlcheckrun'], $matches) && count($matches) === 3) {
            $view1 = $matches[1];
            $view2 = $matches[2];
        } else {
            // Throw an exception if the names of the views could not be extracted from the query.
            throw new \moodle_exception("The names of the views could not be found in the query.");
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

            // Add rows to the table,
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
            return ['grade' => $maxGrade, 'feedback' => get_string('all_rows_correct', 'sqlab')];
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
     */
    protected static function generateEvaluatorFunction($dbConnection)
    {
        // Define the path to the SQL file containing the function definition.
        $functionSqlPath = __DIR__ . '/../db/functions.sql';

        // Check if the SQL file exists and throw an exception if not.
        if (!file_exists($functionSqlPath)) {
            throw new \Exception("SQL file does not exist at the specified path: {$functionSqlPath}");
        }

        // Load the SQL query from the file.
        $functionSql = file_get_contents($functionSqlPath);
        if (!$functionSql) {
            throw new \Exception("Unable to load function definition from the SQL file.");
        }

        // Create or replace the function in the database.
        $createFunction = pg_query($dbConnection, $functionSql);
        if (!$createFunction) {
            throw new \Exception("Failed to create function: " . pg_last_error($dbConnection));
        }
    }
}
