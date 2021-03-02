<?php
declare(strict_types=1);

namespace Src\Database;


use mysqli;

class Database {
    private string $host;
    private string $name;
    private string $username;
    private string $password;
    private int $port;

    private mysqli $connection;

    public function __construct() {

        $envVariablesAreEmpty = (
            empty($_ENV['DB_NAME']) ||
            empty($_ENV['DB_HOST']) ||
            empty($_ENV['DB_USERNAME']) ||
            empty($_ENV['DB_PORT'])
        );

        if ($envVariablesAreEmpty) {
            http_response_code(500);
            die(json_encode(array('message' => 'Database Connection Failed: Some strings in settings.php are empty.', 'success' => false)));
        }

        $this->host = $_ENV['DB_HOST'];
        $this->name = $_ENV['DB_NAME'];
        $this->username = $_ENV['DB_USERNAME'];
        $this->password = $_ENV['DB_PASSWORD'];
        $this->port = $_ENV['DB_PORT'];
    }

    public function connect(): mysqli {

        $this->connection = new mysqli($this->host, $this->username, $this->password, $this->name, $this->port);

        $errorDidOccur = $this->connection->connect_errno;

        if ($errorDidOccur) {
            $errorString = $this->connection->connect_error;
            http_response_code(500);
            die(json_encode(array('message' => "Database connection failed: $errorString.", 'success' => false)));
        }

        $this->connection->set_charset('utf8');

        return $this->connection;
    }
    public function getConnection() {
        return $this->connection;
    }

}