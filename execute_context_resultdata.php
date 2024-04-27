<?php

// Load the necessary configuration, library files and namespaces.
require_once dirname(__FILE__) . '/../../config.php';
require_once dirname(__FILE__) . '/lib.php';
use mod_sqlab\internal_sql_executor;

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
    if (!isset($input['userId']) || !isset($input['sql']) || !isset($input['schemaName'])) {
        throw new moodle_exception('missingparameters', 'sqlab');
    }

    // Extract parameters from the input.
    $userId = $input['userId'];
    $sql = $input['sql'];
    $schemaName = $input['schemaName'];
    $fetchResults = $input['fetchResults'] ?? false;

    // Execute the SQL query using the provided parameters and fetch option.
    $results = internal_sql_executor::execute($userId, $sql, $schemaName, $fetchResults);

    // Output the success status and query results as JSON.
    echo json_encode([
        'status' => 'success',
        'results' => $results
    ]);

} catch (moodle_exception $me) {

    echo json_encode([
        'status' => 'error',
        'message' => $me->getMessage()
    ]);

    exit;

}
