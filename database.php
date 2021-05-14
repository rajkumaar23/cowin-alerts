<?php

class database
{
    private $connection;
    private $DB_SERVER, $DB_USER, $DB_PASS, $DB_NAME;

    /**
     * Database constructor.
     */
    public function __construct()
    {
        $this->DB_SERVER = $_ENV["DB_HOST"];
        $this->DB_NAME = $_ENV["DB_NAME"];
        $this->DB_PASS = $_ENV["DB_PASSWORD"];
        $this->DB_USER = $_ENV["DB_USERNAME"];
        $this->connection = $this->connect();
        $this->loadMigrations();
    }

    /**
     * Connects to the database using PDO
     * @return PDO
     */
    private function connect(): PDO
    {
        $conn = new PDO("mysql:host=$this->DB_SERVER;dbname=$this->DB_NAME", $this->DB_USER, $this->DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    }

    /**
     * Load initial migrations for creating tables
     */
    public function loadMigrations()
    {
        $migration = " 
        CREATE TABLE IF NOT EXISTS `subscriptions` (
            `telegram_id` int PRIMARY KEY,
            `district_id` int NOT NULL,
            `first_name` varchar(255),
            `username` varchar(255)
        )";

        $this->connection->exec($migration);
    }

    /**
     * Returns the created PDO connection
     * @return PDO
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

}
