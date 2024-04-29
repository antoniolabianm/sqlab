<?php

// Load the necessary configuration, library files and namespaces.
require_once dirname(__FILE__) . '/../../config.php';
require_once dirname(__FILE__) . '/lib.php';
use mod_sqlab\user_query_executor;
use mod_sqlab\schema_manager;

// Set the content type of the response to JSON.
header('Content-Type: application/json');

try {

    // Ensure the request method is POST.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new moodle_exception('invalidrequestmethod', 'sqlab');
    }

    // Retrieve JSON input from the request body and decode it.
    $json_input = file_get_contents('php://input');
    $input = json_decode($json_input, true);

    // Check if all required parameters are provided.
    if (!isset($input['attempt']) || !isset($input['sql']) || !isset($input['action'])) {
        throw new moodle_exception('missingparameters', 'sqlab');
    }

    // Extract parameters from the input.
    $attemptid = $input['attempt'];
    $sql = $input['sql'];
    $action = $input['action'];

    // Retrieve records from the database based on the provided attempt ID.
    $attempt = $DB->get_record('sqlab_attempts', ['id' => $attemptid], '*', IGNORE_MISSING);
    if (!$attempt) {
        throw new moodle_exception('invalidattemptid', 'sqlab');
    }

    $sqlab = $DB->get_record('sqlab', ['id' => $attempt->sqlabid], '*', IGNORE_MISSING);
    if (!$sqlab) {
        throw new moodle_exception('invalidsqlabid', 'sqlab');
    }

    $schemaName = schema_manager::format_activity_name($sqlab->name);

    // Execute the SQL query using the formatted schema name.
    $executionResults = user_query_executor::execute_user_sql($attemptid, $sql, $schemaName);

    // Prepare the initial response structure.
    $response = ['status' => 'success', 'results' => []];
    $resultsArray = [];

    // Process execution results and handle potential errors.
    foreach ($executionResults as $result) {
        if (isset($result['error'])) {
            $response['status'] = 'error';
            $response['message'] = $result['message'];
            $resultsArray[] = $result['message'];
            break;
        } else {
            $response['results'][] = $result;
            $resultsArray[] = $result;
        }
    }

    // Prepare a record for database insertion.
    $record = new stdClass();
    $record->userid = $USER->id;
    $record->attemptid = $attemptid;
    $record->executed_code = $sql;
    $record->action = $action;
    $record->received_reply = json_encode($resultsArray);
    $record->execution_timestamp = date('Y-m-d\TH:i:s\Z');

    // Insert the execution record into the database.
    $DB->insert_record('sqlab_code_executions', $record);

    // Output the response as JSON.
    echo json_encode($response);

} catch (moodle_exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
