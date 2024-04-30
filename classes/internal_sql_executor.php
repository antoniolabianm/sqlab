<?php

namespace mod_sqlab;

defined('MOODLE_INTERNAL') || die();

class internal_sql_executor
{
    /**
     * Execute SQL in a database, optionally fetching results, with dynamic connection control.
     *
     * @param mixed $userId User ID of the student.
     * @param string $sql SQL commands to execute.
     * @param string $schemaName Name of the schema to set for the SQL execution.
     * @param bool $fetchResults Whether to fetch and return results.
     * @param resource|null $dbConnection Optional existing database connection to use.
     * @param bool $adminFlag Whether to connect as admin (true) or as the student (false).
     * @return mixed Returns an array of results if fetching is requested, null otherwise.
     * @throws \moodle_exception Throws exceptions for connection or execution errors.
     */
    public static function execute($userId, $sql, $schemaName, $fetchResults = false, $dbConnection = null, $adminFlag = true)
    {
        global $DB;
        $ownConnection = false; // To track if we've created the connection ourselves.

        if (empty($sql)) {
            return; // No SQL to execute.
        }

        // Use existing connection if provided, otherwise create a new one.
        if (!$dbConnection) {

            // Fetch user credentials from the database.
            $credentials = $DB->get_record('sqlab_db_user_credentials', ['userid' => $userId]);
            if (!$credentials) {
                throw new \moodle_exception('credentialsnotfound', 'mod_sqlab', '', null, "Credentials not found for user ID $userId");
            }

            // Determine the database name from the student's credentials.
            $studentDbName = str_replace("ROLE_", "", $credentials->username);

            // Connect to the database as admin or student depending on adminFlag.
            if ($adminFlag) {
                // Admin uses default configured credentials in dbconnector.
                $dbConnector = new dbconnector($studentDbName);
            } else {
                // Student uses specific credentials.
                $dbConnector = new dbconnector($studentDbName, $credentials->username, encoder::decrypt($credentials->password));
            }

            // Attempt to connect to the database.
            $dbConnection = $dbConnector->connect();
            $ownConnection = true; // Mark that we own this connection.

            if (!$dbConnection) {
                throw new \moodle_exception('dbconnectionerror', 'mod_sqlab', '', null, "Error connecting to the database for user ID $userId");
            }

        }

        try {

            // Execute SQL with transaction control for integrity.
            pg_query($dbConnection, "BEGIN");

            // Set the search path to the specified schema.
            pg_query($dbConnection, "SET search_path TO \"$schemaName\"");

            // Execute the SQL command and optionally fetch results.
            $result = self::execute_sql($dbConnection, $sql, $fetchResults);

            // Commit the transaction.
            pg_query($dbConnection, "COMMIT");

            return $result; // Return results if fetchResults is true, otherwise null.

        } catch (\Exception $e) {
            // Rollback the transaction in case of an error.
            pg_query($dbConnection, "ROLLBACK");
            throw new \moodle_exception('dbexecutionerror', 'mod_sqlab', '', null, $e->getMessage());
        } finally {
            if ($ownConnection && $dbConnection) {
                // Only close the connection if we created it.
                $dbConnector->closeConnection();
            }
        }
    }

    /**
     * Executes SQL queries on the given database connection and handles result fetching.
     *
     * @param resource $dbConnection Database connection resource.
     * @param string $sql SQL command to execute.
     * @param bool $fetchResults Flag indicating whether results should be fetched.
     * @return mixed Returns fetched results if requested, otherwise null.
     * @throws \Exception Throws exception on query failure.
     */
    private static function execute_sql($dbConnection, $sql, $fetchResults = false)
    {
        $result = pg_query($dbConnection, $sql);

        if (!$result) {
            throw new \Exception("Failed to execute SQL: " . pg_last_error($dbConnection));
        }

        return $fetchResults ? pg_fetch_all($result) : null;
    }
}
