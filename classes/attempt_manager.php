<?php

namespace mod_sqlab;

defined('MOODLE_INTERNAL') || die();

class attempt_manager
{
    const IN_PROGRESS = 'inprogress';
    const FINISHED = 'finished';
    const OVERDUE = 'overdue';

    /**
     * Creates a new attempt for the user specified in the given SQLab activity.
     *
     * @param int $sqlabid The ID of the SQLab activity.
     * @param int $userid The ID of the user attempting the activity.
     * @return int The ID of the newly created attempt.
     * @throws \dml_exception If a database error occurs.
     */
    public static function create_new_attempt($sqlabid, $userid)
    {
        global $DB;

        try {

            // Check if an attempt already exists for the user.
            $attempts = $DB->get_records('sqlab_attempts', array('sqlabid' => $sqlabid, 'userid' => $userid), 'attempt DESC');
            $lastattempt = reset($attempts);

            // Determine the number of the next attempt.
            $attemptnumber = $lastattempt ? $lastattempt->attempt + 1 : 1;

            $record = new \stdClass();
            $record->sqlabid = $sqlabid;
            $record->userid = $userid;
            $record->attempt = $attemptnumber;
            $record->state = self::IN_PROGRESS;
            $record->timestart = time();
            $record->timefinish = 0; // 0 means that the attempt has not yet been completed.
            $record->timemodified = time();
            $record->sumgrades = 0; // Initially, there is no qualification.

            // Insert new attempt into the database.
            $newattemptid = $DB->insert_record('sqlab_attempts', $record);

            return $newattemptid;

        } catch (\dml_exception $e) {
            error_log("Error creating new attempt for user ID $userid at SQLab ID $sqlabid: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Finalizes an in-progress attempt, marking it as finished and recording the finish time.
     *
     * @param int $attemptid The ID of the attempt to finalize.
     * @return bool True if the attempt was successfully finalized, false if no update was necessary.
     * @throws \dml_exception If a database error occurs.
     */
    public static function finalize_attempt($attemptid)
    {
        global $DB;

        try {

            // Fetch the current attempt record.
            $attempt = $DB->get_record('sqlab_attempts', array('id' => $attemptid), '*');

            if (!$attempt) {
                throw new \moodle_exception('Attempt not found for ID: ' . $attemptid);
            }

            // Only finalize if the attempt is still in progress.
            if ($attempt->state == self::IN_PROGRESS) {
                $attempt->state = self::FINISHED;
                $attempt->timefinish = time(); // Set the finish time to the current time.
                $attempt->timemodified = time(); // Update the modified time.

                // Update the attempt record in the database.
                $DB->update_record('sqlab_attempts', $attempt);
            } else {
                error_log("Attempt with ID $attemptid is not in progress and cannot be finalized.");
                return false;
            }

            return true; // Return true indicating successful finalization.

        } catch (\dml_exception $e) {
            error_log("Error finalizing attempt with ID $attemptid: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Updates the state of a specific attempt.
     *
     * @param int $attemptid The ID of the attempt to update.
     * @param string $newstate The new state to set for the attempt.
     * @throws \dml_exception If a database error occurs.
     */
    public static function update_attempt_state($attemptid, $newstate)
    {
        global $DB;

        try {

            $record = new \stdClass();
            $record->id = $attemptid;
            $record->state = $newstate;
            $record->timemodified = time();

            $DB->update_record('sqlab_attempts', $record);

        } catch (\dml_exception $e) {
            error_log("Error updating attempt state for ID $attemptid to $newstate: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Retrieves the current state of an attempt.
     *
     * @param int $attemptid The ID of the attempt whose state is to be checked.
     * @return string The current state of the attempt.
     * @throws \moodle_exception If the attempt is not found.
     */
    public static function check_attempt_state($attemptid)
    {
        global $DB;

        $attempt = $DB->get_record('sqlab_attempts', array('id' => $attemptid), 'state');

        if (!$attempt) {
            throw new \moodle_exception('Attempt not found: ' . $attemptid);
        }

        return $attempt->state;
    }
}
