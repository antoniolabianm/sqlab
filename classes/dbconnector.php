<?php

namespace mod_sqlab;

defined('MOODLE_INTERNAL') || die();

class dbconnector
{
    private $host;
    private $port;
    private $dbname;
    private $user;
    private $password;
    private $conn = null; // Holds the active database connection.

    /**
     * Constructs a new dbconnector instance with optional connection parameters.
     *
     * @param string $dbname The name of the database to connect to.
     * @param string $user The username for the database connection.
     * @param string $password The password for the database connection.
     * @param string $host The host of the database server.
     * @param string $port The port of the database server.
     */
    public function __construct($dbname = 'postgres', $user = 'postgres', $password = 'postgres', $host = 'postgres', $port = '5432')
    {
        $this->host = $host;
        $this->port = $port;
        $this->dbname = $dbname;
        $this->user = $user;
        $this->password = $password;
    }

    /**
     * Establishes a connection to the database using the provided credentials.
     *
     * @return resource|null The database connection resource if successful, or null if the connection fails.
     */
    public function connect()
    {
        if ($this->conn === null) {

            // Prepare connection string and attempt to establish a PostgreSQL connection.
            $connectionString = "host={$this->host} port={$this->port} dbname={$this->dbname} user={$this->user} password={$this->password}";
            $this->conn = pg_connect($connectionString);

            if (!$this->conn) {
                error_log("Database connection error: " . pg_last_error());
                return null;
            }

        }

        return $this->conn;
    }

    /**
     * Closes the active database connection if one exists.
     */
    public function closeConnection()
    {
        if ($this->conn !== null) {
            pg_close($this->conn);
            $this->conn = null; // Reset the connection to ensure it's no longer usable.
        }
    }
}
