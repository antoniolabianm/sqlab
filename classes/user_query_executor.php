<?php

namespace mod_sqlab;

defined('MOODLE_INTERNAL') || die();

class user_query_executor
{
    /**
     * Analyzes a multi-line SQL script and determines the types of queries it contains.
     *
     * @param string $sql The full SQL script containing one or more statements.
     * @return array An array of identified query types for each statement in the script.
     */
    public static function detectQueryType($sql)
    {
        $lines = explode("\n", $sql);
        $queryTypes = [];
        $currentStatement = '';

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Skip empty lines and comments.
            if (empty($trimmedLine) || strpos($trimmedLine, '--') === 0) {
                continue;
            }

            $currentStatement .= ' ' . $trimmedLine;

            // If semicolon found, process the statement.
            if (strrpos($trimmedLine, ';') === strlen($trimmedLine) - 1) {
                $queryType = self::detectStatementType($currentStatement);
                $queryTypes[] = $queryType;
                $currentStatement = ''; // Reset for the next statement.
            }
        }

        return $queryTypes;
    }

    /**
     * Identifies the type of a single SQL statement.
     *
     * @param string $statement The SQL statement to classify.
     * @return string The identified type of the SQL statement, such as 'SELECT', 'CREATE TABLE', or 'UNKNOWN'.
     */
    private static function detectStatementType($statement)
    {
        $normalizedStatement = strtoupper(trim(preg_replace('/\s+/', ' ', $statement)));
        $words = explode(' ', $normalizedStatement);

        // List of simple SQL keywords.
        $simpleKeywords = [
            'SELECT',
            'INSERT',
            'UPDATE',
            'DELETE',
            'GRANT',
            'REVOKE',
            'ROLLBACK',
            'SAVEPOINT',
            'SET',
            'COPY',
            'ANALYZE',
            'EXPLAIN',
            'VACUUM',
            'TRUNCATE',
            'LISTEN',
            'NOTIFY',
            'MOVE',
            'FETCH',
            'PREPARE',
            'EXECUTE',
            'DECLARE',
            'BEGIN',
            'COMMIT'
        ];

        // Check for simple keywords.
        foreach ($simpleKeywords as $keyword) {
            if (strpos($normalizedStatement, $keyword) !== false) {
                return $keyword;
            }
        }

        // Mapping of compound keywords and their potential followers.
        $compoundKeywords = [
            'CREATE' => ['TABLE', 'VIEW', 'INDEX', 'SCHEMA', 'DATABASE', 'FUNCTION', 'SEQUENCE', 'TRIGGER', 'ROLE', 'EXTENSION', 'DOMAIN'],
            'DROP' => ['TABLE', 'VIEW', 'INDEX', 'SCHEMA', 'DATABASE', 'FUNCTION', 'SEQUENCE', 'TRIGGER', 'ROLE', 'EXTENSION', 'DOMAIN'],
            'ALTER' => ['TABLE', 'VIEW', 'INDEX', 'SCHEMA', 'DATABASE', 'FUNCTION', 'SEQUENCE', 'TRIGGER', 'ROLE', 'EXTENSION', 'DOMAIN'],
            'RELEASE' => ['SAVEPOINT'],
            'LOCK' => ['TABLE']
        ];

        // Check for compound keywords.
        if (count($words) > 1) {
            foreach ($compoundKeywords as $key => $values) {
                if ($words[0] == $key) {
                    foreach ($values as $value) {
                        if ($words[1] == $value) {
                            return "$key $value";
                        }
                    }
                }
            }
        }

        return 'UNKNOWN'; // Default return value if no known patterns are detected.
    }

    /**
     * Executes SQL statements for a specific attempt and schema, handling connections and errors.
     *
     * @param int $attemptid The ID of the attempt for which SQL is executed.
     * @param string $sql The SQL statement(s) to be executed.
     * @param string $schemaName The schema name to set for the execution context.
     * @return array An array containing results or error information for each statement executed.
     * @throws moodle_exception Throws exceptions if SQL is empty, credentials are not found, or the database connection fails.
     */
    public static function execute_user_sql($attemptid, $sql, $schemaName)
    {
        global $DB;

        if (empty($sql)) {
            throw new moodle_exception('emptyquery', 'mod_sqlab');
        }

        $attempt = $DB->get_record('sqlab_attempts', ['id' => $attemptid], '*', MUST_EXIST);
        $credentials = $DB->get_record('sqlab_db_user_credentials', ['userid' => $attempt->userid], '*', MUST_EXIST);

        if (!$credentials) {
            throw new moodle_exception('credentialsnotfound', 'mod_sqlab');
        }

        $decrypted_password = encoder::decrypt($credentials->password);
        $studentDbName = str_replace("ROLE_", "", $credentials->username);

        $dbConnector = new dbconnector($studentDbName, $credentials->username, $decrypted_password);
        $conn = $dbConnector->connect();

        if (!$conn) {
            throw new moodle_exception('dbconnectionerror', 'mod_sqlab');
        }

        try {

            pg_query($conn, "SET search_path TO \"$schemaName\"");
            pg_send_query($conn, $sql);

            $combinedResults = [];
            $queryTypes = self::detectQueryType($sql);
            $queryIndex = 0;

            while ($result = pg_get_result($conn)) {
                $queryType = $queryTypes[$queryIndex++] ?? 'UNKNOWN';

                $state = pg_result_error_field($result, PGSQL_DIAG_SQLSTATE);

                if ($state) {
                    $errorMsg = pg_result_error($result);
                    $combinedResults[] = [
                        'error' => true,
                        'message' => $errorMsg,
                        'sqlstate' => $state
                    ];
                } else {
                    $individualResultData = pg_fetch_all($result) ?: [];
                    $affectedRows = pg_affected_rows($result);
                    $combinedResults[] = [
                        'data' => $individualResultData,
                        'affectedRows' => $affectedRows,
                        'type' => $queryType
                    ];
                }
            }

            return $combinedResults;

        } finally {
            $dbConnector->closeConnection();
        }
    }
}
