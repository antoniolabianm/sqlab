<?php

namespace mod_sqlab;

defined('MOODLE_INTERNAL') || die();

class user_query_executor
{
    // List of SQL keywords to ignore while processing queries.
    protected static $ignoreConnectors = [
        'OR',
        'REPLACE',
        'AND',
        'ON',
        'LIMIT',
        'WHERE',
        'JOIN',
        'GROUP',
        'ORDER',
        'OPTION',
        'LEFT',
        'INNER',
        'RIGHT',
        'OUTER',
        'SET',
        'HAVING',
        'VALUES'
    ];

    /**
     * Detects the types of SQL statements within a provided SQL query.
     *
     * @param string $sql The complete SQL query string from which types of statements are to be detected.
     * @return array An array listing the detected types of the SQL statements.
     */
    private static function detectQueryType($sql)
    {
        // Remove SQL comments to prepare the string for processing.
        $sql = preg_replace('/(--.*)|(\s*\/\*[\s\S]*?\*\/)/', '', $sql);

        // Split the cleaned SQL into individual statements for further analysis.
        $statements = self::splitSqlStatements($sql);
        $queryTypes = [];

        // Iterate through each statement to determine its type.
        foreach ($statements as $statement) {
            if (trim($statement)) {
                // Detect and record the type of the SQL statement.
                $queryType = self::detectStatementType($statement);
                $queryTypes[] = $queryType;
            }
        }

        // Return the list of detected SQL statement types.
        return $queryTypes;
    }

    /**
     * Splits the provided SQL into individual statements.
     * Handles special SQL blocks (functions, triggers) and regular SQL statements.
     *
     * @param string $sql The SQL string cleaned of comments and unnecessary connectors.
     * @return array An array containing individual SQL statements, properly ordered.
     */
    private static function splitSqlStatements($sql)
    {
        $statements = [];

        // Remove common SQL connectors to simplify the remaining string for further processing.
        $sql = preg_replace('/\b(' . implode('|', self::$ignoreConnectors) . ')\b/i', '', $sql);

        // Patterns to identify complex SQL constructs like stored functions and triggers.
        $patterns = [
            '/CREATE\s+FUNCTION\s+[\s\S]+?\$\$[\s\S]*?\$\$\s*LANGUAGE\s*plpgsql\s*;/i', // Matches PostgreSQL-style stored functions.
            '/CREATE\s+TRIGGER\s+[\s\S]+?EXECUTE\s+PROCEDURE\s+[\s\S]+?;/i' // Matches SQL triggers up to the EXECUTE PROCEDURE part.
        ];

        // Extract special SQL blocks based on predefined patterns and store their positions for sequential processing.
        $blocks = [];
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $sql, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[0] as $match) {
                $blocks[] = ['sql' => $match[0], 'start' => $match[1], 'end' => $match[1] + strlen($match[0])];
            }
        }

        // Sort identified blocks to maintain the natural order of SQL statements in the script.
        usort($blocks, function ($a, $b) {
            return $a['start'] - $b['start'];
        });

        // Reconstruct the full list of SQL statements, ensuring all code blocks are integrated in their original order.
        $lastPos = 0;
        foreach ($blocks as $block) {
            // Add SQL code found before each block.
            $intermediateSql = substr($sql, $lastPos, $block['start'] - $lastPos);
            $statements = array_merge($statements, self::extractStatements($intermediateSql));
            // Add the block itself.
            $statements[] = $block['sql'];
            $lastPos = $block['end'];
        }

        // Append any SQL code that follows the last block.
        $remainingSql = substr($sql, $lastPos);
        $statements = array_merge($statements, self::extractStatements($remainingSql));

        return $statements;
    }

    /**
     * Splits a given SQL string into individual statements based on the semicolon delimiter.
     *
     * @param string $sql The segment of SQL code to be split into distinct statements.
     * @return array An array of cleanly trimmed SQL statements, each terminated with a semicolon.
     */
    private static function extractStatements($sql)
    {
        $result = [];

        // Use a regex to split the SQL string at semicolons, only if they are not within quotes or comments.
        foreach (preg_split('/;(?=(?:[^\'"]|\'[^\']*\'|"[^"]*")*$)/', $sql) as $stmt) {
            $trimmed = trim($stmt);
            if ($trimmed) {
                // Ensure each statement ends with a semicolon for correct SQL syntax.
                $result[] = $trimmed . ';';
            }
        }

        return $result;
    }

    /**
     * Determines the SQL command type of a given SQL statement by examining its starting keyword.
     *
     * @param string $statement The single SQL statement to analyze.
     * @return string The type of the SQL command (e.g., 'CREATE TABLE', 'DROP INDEX') or 'UNKNOWN' if no match is found.
     */
    private static function detectStatementType($statement)
    {
        $normalizedStatement = strtoupper(trim($statement));

        // Define patterns for SQL command types for CREATE, DROP, and ALTER operations.
        $commandPatterns = [
            'CREATE' => [
                'FUNCTION' => '/^CREATE\s+FUNCTION/i',
                'TRIGGER' => '/^CREATE\s+TRIGGER/i',
                'TABLE' => '/^CREATE\s+TABLE/i',
                'INDEX' => '/^CREATE\s+INDEX/i',
                'SEQUENCE' => '/^CREATE\s+SEQUENCE/i',
                'VIEW' => '/^CREATE\s+VIEW/i',
                'DOMAIN' => '/^CREATE\s+DOMAIN/i'
            ],
            'DROP' => [
                'FUNCTION' => '/^DROP\s+FUNCTION/i',
                'TRIGGER' => '/^DROP\s+TRIGGER/i',
                'TABLE' => '/^DROP\s+TABLE/i',
                'INDEX' => '/^DROP\s+INDEX/i',
                'SEQUENCE' => '/^DROP\s+SEQUENCE/i',
                'VIEW' => '/^DROP\s+VIEW/i',
                'DOMAIN' => '/^DROP\s+DOMAIN/i'
            ],
            'ALTER' => [
                'FUNCTION' => '/^ALTER\s+FUNCTION/i',
                'TRIGGER' => '/^ALTER\s+TRIGGER/i',
                'TABLE' => '/^ALTER\s+TABLE/i',
                'INDEX' => '/^ALTER\s+INDEX/i',
                'SEQUENCE' => '/^ALTER\s+SEQUENCE/i',
                'VIEW' => '/^ALTER\s+VIEW/i',
                'DOMAIN' => '/^ALTER\s+DOMAIN/i'
            ]
        ];

        // Iterate through the command patterns to find a match for the normalized statement.
        foreach ($commandPatterns as $command => $patterns) {
            foreach ($patterns as $type => $pattern) {
                if (preg_match($pattern, $normalizedStatement)) {
                    return $command . ' ' . $type; // Return the match as a concatenated string of command and type.
                }
            }
        }

        // List of additional common SQL commands to check.
        $otherCommands = [
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
            'COMMIT',
            'DROP',
            'ALTER',
            'LOCK',
            'RELEASE'
        ];

        // Check for other common SQL keywords.
        foreach ($otherCommands as $keyword) {
            if (strpos($normalizedStatement, $keyword) === 0) {
                return $keyword; // Return the keyword if it matches the beginning of the statement.
            }
        }

        // Return 'UNKNOWN' if no known SQL command patterns are matched.
        return 'UNKNOWN';
    }

    /**
     * Executes SQL statements for a specified attempt and schema, handling database connection and execution.
     *
     * @param int $attemptid The unique identifier of the attempt for which SQL is executed.
     * @param string $sql The SQL statement or multiple statements to be executed.
     * @param string $schemaName The schema name to set for the execution context.
     * @return array An array containing results or error information for each executed statement.
     * @throws moodle_exception Throws exceptions for various failures such as empty SQL, missing credentials, or database connection errors.
     */
    public static function execute_user_sql($attemptid, $sql, $schemaName)
    {
        global $DB;

        // Ensure SQL is not empty.
        if (empty($sql)) {
            throw new moodle_exception('emptyquery', 'mod_sqlab');
        }

        // Retrieve attempt and user credentials records.
        $attempt = $DB->get_record('sqlab_attempts', ['id' => $attemptid], '*', MUST_EXIST);
        $credentials = $DB->get_record('sqlab_db_user_credentials', ['userid' => $attempt->userid], '*', MUST_EXIST);

        // Handle missing credentials.
        if (!$credentials) {
            throw new moodle_exception('credentialsnotfound', 'mod_sqlab');
        }

        // Decrypt password and establish database connection.
        $decrypted_password = encoder::decrypt($credentials->password);
        $studentDbName = str_replace("ROLE_", "", $credentials->username);
        $dbConnector = new dbconnector($studentDbName, $credentials->username, $decrypted_password);
        $conn = $dbConnector->connect();

        // Handle connection failure.
        if (!$conn) {
            throw new moodle_exception('dbconnectionerror', 'mod_sqlab');
        }

        try {

            // Set the search path to the specified schema.
            pg_query($conn, "SET search_path TO \"$schemaName\"");
            pg_send_query($conn, $sql);

            $combinedResults = [];
            $queryTypes = self::detectQueryType($sql);
            $queryIndex = 0;

            // Fetch results for each query executed.
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
            // Ensure the database connection is closed.
            $dbConnector->closeConnection();
        }
    }
}
