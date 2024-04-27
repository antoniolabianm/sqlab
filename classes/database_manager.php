<?php

namespace mod_sqlab;

defined('MOODLE_INTERNAL') || die();

class database_manager
{
    /**
     * Retrieves the ID of a role based on its short name.
     *
     * @param string $roleShortName The short name of the role to find.
     * @return int The ID of the role.
     * @throws \moodle_exception If the role cannot be found.
     */
    public static function get_role_id_by_shortname($roleShortName)
    {
        global $DB;

        // Attempt to find the role in the database.
        $role = $DB->get_record('role', array('shortname' => $roleShortName), '*', IGNORE_MISSING);

        // If the role does not exist, throw an exception.
        if (!$role) {
            throw new \moodle_exception('rolenotfound', 'error', '', null, "Role short name: $roleShortName not found.");
        }

        // Return the ID of the found role.
        return $role->id;
    }

    /**
     * Processes a role assignment event to potentially set up a user-specific database.
     *
     * @param array $event_data Data associated with the role assignment event.
     * @throws \moodle_exception If the user cannot be found or a database error occurs.
     */
    public static function handle_role_assignment($event_data)
    {
        global $DB;

        try {

            $role_id = $event_data['objectid'];
            $user_id = $event_data['relateduserid'];

            // Return immediately if the assigned role is not 'student'.
            if ($role_id != self::get_role_id_by_shortname('student')) {
                return;
            }

            // Retrieve user record from the database.
            $user = $DB->get_record('user', array('id' => $user_id));
            if (!$user) {
                throw new \moodle_exception('usernotfound', 'error', '', null, "User ID: $user_id not found.");
            }

            // Format a database name using the user's information.
            $db_name = self::format_db_name($user->firstname, $user->lastname, $user_id);

            // Check if the database already exists and return if it does.
            if (self::check_database_exists($db_name)) {
                return;
            }

            // Create a new database for the user if it does not exist.
            self::create_user_database($db_name, $user_id);

        } catch (\dml_exception $e) {
            error_log("Error in handle_role_assignment for user with ID $user_id: " . $e->getMessage());
            throw $e; // Re-throw the exception after logging it.
        }
    }

    /**
     * Formats a database name using a first name, last name, and user ID.
     *
     * @param string $firstname The user's first name.
     * @param string $lastname The user's last name.
     * @param int $user_id The user's ID.
     * @return string The formatted database name in uppercase.
     */
    private static function format_db_name($firstname, $lastname, $user_id)
    {
        $maxLength = 63; // Maximum length for PostgreSQL identifiers.
        $firstname = self::sanitize_name($firstname);
        $lastname = self::sanitize_name($lastname);
        $db_name = strtoupper($firstname . '_' . $lastname . '_' . $user_id);

        // Shorten the name parts if the combined length exceeds the maximum.
        if (strlen($db_name) > $maxLength) {
            $availableLength = $maxLength - strlen($user_id) - 2; // Account for underscores.
            $halfLength = floor($availableLength / 2);
            $shortFirstname = substr($firstname, 0, $halfLength);
            $shortLastname = substr($lastname, 0, $halfLength);
            $db_name = strtoupper($shortFirstname . '_' . $shortLastname . '_' . $user_id);
        }

        return $db_name;
    }

    /**
     * Sanitizes a name by removing non-alphanumeric characters and converting spaces to underscores.
     *
     * @param string $name The name to sanitize.
     * @return string The sanitized name.
     */
    private static function sanitize_name($name)
    {
        $name = iconv('UTF-8', 'ASCII//TRANSLIT', $name); // Convert accents and special characters to ASCII.
        $name = preg_replace('/\s+/', '_', $name); // Replace spaces with underscores.
        $name = preg_replace('/[^A-Za-z0-9_]/', '', $name); // Remove non-alphanumeric characters.
        return $name;
    }

    /**
     * Generates a random password of a specified length, including at least one digit, uppercase letter, lowercase letter, and special character.
     *
     * @param int $length The desired length of the password.
     * @return string The randomly generated password.
     */
    private static function generate_random_password($length = 12)
    {
        $digit = chr(mt_rand(48, 57)); // 0-9
        $upper = chr(mt_rand(65, 90)); // A-Z
        $lower = chr(mt_rand(97, 122)); // a-z
        $special_chars = '!@$%^&*-_+='; // PostgreSQL secure special characters.
        $special = $special_chars[mt_rand(0, strlen($special_chars) - 1)]; // Random special character.

        $seed = str_shuffle($digit . $upper . $lower . $special);

        for ($i = 0; $i < $length - 4; $i++) {
            $options = [$digit, $upper, $lower, $special_chars[mt_rand(0, strlen($special_chars) - 1)]];
            $seed .= $options[mt_rand(0, count($options) - 1)]; // Add additional random characters.
        }

        return str_shuffle($seed); // Shuffle to ensure randomness.
    }

    /**
     * Saves encrypted student credentials to the database.
     *
     * @param int $user_id The user ID of the student.
     * @param string $student_username The username of the student.
     * @param string $student_password The plaintext password of the student.
     * @throws \dml_exception If there is a database manipulation error.
     */
    private static function save_student_credentials($user_id, $student_username, $student_password)
    {
        global $DB;

        try {

            $encrypted_password = encoder::encrypt($student_password);

            $data = new \stdClass();
            $data->userid = $user_id;
            $data->username = $student_username;
            $data->password = $encrypted_password;

            // Insert the new credentials record into the database.
            $DB->insert_record('sqlab_db_user_credentials', $data);

        } catch (\dml_exception $e) {
            error_log("Error saving user credentials for user ID $user_id: " . $e->getMessage());
            throw $e; // Re-throw the exception after logging.
        }
    }

    /**
     * Checks if a database exists by connecting to PostgreSQL and querying the database list.
     *
     * @param string $db_name The name of the database to check.
     * @return bool Returns true if the database exists, false otherwise.
     */
    private static function check_database_exists($db_name)
    {
        $dbConnector = new dbconnector();
        $conn = $dbConnector->connect();

        if (!$conn) {
            error_log("Error: Unable to establish connection to check if the database $db_name exists.");
            return false;
        }

        $result = pg_query($conn, "SELECT 1 FROM pg_database WHERE datname = '$db_name'");
        if (!$result) {
            error_log("Error: Failed to check if the database $db_name exists: " . pg_last_error($conn));
            return false;
        }

        $exists = pg_num_rows($result) > 0;
        pg_free_result($result);
        $dbConnector->closeConnection();

        return $exists;
    }

    /**
     * Creates a user-specific database and associated login role with permissions.
     *
     * @param string $db_name The name of the database to be created.
     * @param int $user_id The user ID for whom the database is being created.
     */
    private static function create_user_database($db_name, $user_id)
    {
        $dbConnector = new dbconnector();
        $conn = $dbConnector->connect();

        if (!$conn) {
            error_log("Error: Failed to establish a connection to create the database $db_name.");
            return;
        }

        // Attempt to create the database.
        if (!pg_query($conn, "CREATE DATABASE \"$db_name\" WITH OWNER = postgres TEMPLATE = template0 ENCODING = 'UTF8'")) {
            error_log("Error creating the database $db_name: " . pg_last_error($conn));
            return;
        }

        // Generate role name and password for the database.
        $student_username = 'ROLE_' . $db_name;
        $student_password = self::generate_random_password();

        // Attempt to create the role.
        if (!pg_query($conn, "CREATE ROLE \"$student_username\" LOGIN PASSWORD '$student_password'")) {
            error_log("Error creating the role $student_username: " . pg_last_error($conn));
            return;
        }

        // Grant connection permissions to the role.
        if (!pg_query($conn, "GRANT CONNECT ON DATABASE \"$db_name\" TO \"$student_username\"")) {
            error_log("Error granting permissions to the role $student_username: " . pg_last_error($conn));
            return;
        }

        // Save the credentials in the database.
        self::save_student_credentials($user_id, $student_username, $student_password);

        // Close the database connection.
        $dbConnector->closeConnection();
    }
}
