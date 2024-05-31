<?php

namespace mod_sqlab;

defined('MOODLE_INTERNAL') || die();

class schema_manager
{
    /**
     * Handles the first attempt for a user in a specific SQLab activity.
     *
     * @param int $attemptid The ID of the user's attempt.
     * @param int $userid The ID of the user.
     * @throws \moodle_exception Throws exceptions if the attempt, activity, user credentials, or database connection cannot be found or established.                          
     */
    public static function handle_first_attempt($attemptid, $userid)
    {
        global $DB;

        // Verify and retrieve the attempt record.
        $attempt = $DB->get_record('sqlab_attempts', ['id' => $attemptid]);

        if (!$attempt) {
            throw new \moodle_exception('attemptnotfound', 'sqlab', '', null, "Attempt $attemptid not found");
        }

        // Verify and retrieve the associated SQLab activity.
        $sqlab = $DB->get_record('sqlab', ['id' => $attempt->sqlabid]);

        if (!$sqlab) {
            throw new \moodle_exception('activitynotfound', 'sqlab', '', null, "Activity associated with attempt $attemptid not found");
        }

        $activityName = self::format_activity_name($sqlab->name);

        // Verify and retrieve user credentials.
        $credentials = $DB->get_record('sqlab_db_user_credentials', ['userid' => $userid]);

        if (!$credentials) {
            throw new \moodle_exception('credentialsnotfound', 'sqlab', '', null, "Credentials not found for user $userid");
        }

        // The student's database name is the username (without the "ROLE_" prefix) from the credentials.
        $studentDbName = str_replace("ROLE_", "", $credentials->username);

        // Connect to the student's database using administrator credentials.
        $dbConnector = new dbconnector($studentDbName);
        $studentDBConnection = $dbConnector->connect();

        if (!$studentDBConnection) {
            throw new \moodle_exception('dbconnectionerror', 'sqlab', '', null, "Error connecting to the student database $studentDbName");
        }

        // Create schema and assign permissions.
        self::create_schema_for_activity($studentDBConnection, $activityName, $credentials->username);
    }

    /**
     * Creates a schema for a specific activity and assigns necessary permissions to a user.
     *
     * @param resource $dbConnection The database connection resource.
     * @param string $activityName The name of the schema.
     * @param string $username The username to whom the permissions will be granted.
     * @throws \moodle_exception Throws an exception if there is an error during schema creation or permission assignment.
     */
    public static function create_schema_for_activity($dbConnection, $activityName, $username)
    {
        try {
            pg_query($dbConnection, "CREATE SCHEMA \"$activityName\"");
            pg_query($dbConnection, "GRANT USAGE ON SCHEMA \"$activityName\" TO \"$username\"");
            pg_query($dbConnection, "GRANT CREATE ON SCHEMA \"$activityName\" TO \"$username\"");
            pg_query($dbConnection, "ALTER DEFAULT PRIVILEGES IN SCHEMA \"$activityName\" GRANT ALL ON TABLES TO \"$username\"");
        } catch (\Exception $e) {
            throw new \moodle_exception('schemacreationerror', 'sqlab', '', null, "Error creating or assigning permissions in schema: " . $e->getMessage());
        }
    }

    /**
     * Formats an activity name to a standard database schema name format.
     *
     * @param string $name The original name of the activity.
     * @return string The formatted schema name.
     */
    public static function format_activity_name($name)
    {
        $name = iconv('UTF-8', 'ASCII//TRANSLIT', $name); // Transliterate to ASCII.
        $name = preg_replace('/\s+/', '_', $name); // Replace spaces with underscores.
        $name = preg_replace('/[^A-Za-z0-9_]/', '', $name); // Remove non-alphanumeric characters.
        return strtoupper($name); // Convert to uppercase.
    }

    /**
     * Drops a schema from the database if it exists.
     *
     * @param resource $dbConnection The database connection resource.
     * @param string $schemaName The name of the schema to be dropped.
     * @throws \moodle_exception Throws an exception if there is an error during the schema deletion.
     */
    public static function drop_schema($dbConnection, $schemaName)
    {
        try {
            // Attempt to drop the schema if it exists, including all contained objects.
            pg_query($dbConnection, "DROP SCHEMA IF EXISTS \"$schemaName\" CASCADE");
        } catch (\Exception $e) {
            throw new \moodle_exception('schemadeletionerror', 'sqlab', '', null, "Error dropping schema: " . $e->getMessage());
        }
    }
}
