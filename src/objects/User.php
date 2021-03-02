<?php

declare(strict_types=1);

namespace Src\objects;

use mysqli;
use mysqli_result;

class User {
    public function __construct(
        private mysqli $connection,
        private string $name,
        private ?string $password = null
    ) { }

    public function logIn(): bool {

        $stmt = $this->connection->prepare('SELECT password FROM users WHERE username = ?');

        $stmt->bind_param('s',$this->name);


        $stmt->execute();

        $passwordHash = $stmt->get_result()->fetch_array(MYSQLI_ASSOC)['password'];

        if (empty($passwordHash)) {
            return false;
        }
        return password_verify($this->password, $passwordHash);
    }
    public function register(): bool {

        $usernameMeetsRequirements = strlen($this->name) > 3 && strlen($this->name) < 33;

        $passwordMeetsRequirements = (preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*([!@$%&*?#])).+$/', $this->password) && strlen($this->password) > 7);

        if (!$passwordMeetsRequirements) {
            http_response_code(200);
            die(json_encode(array('message' => "Password doesn't meet requirements. It has to be at least 8 chars long and contain at least one lower and uppercase letter, number and special character(!@$%&*?)", 'success' => false)));
        }

        if (!$usernameMeetsRequirements) {
            http_response_code(200);
            die(json_encode(array('message' => 'Username is either too long or too short (should be between 4 and 32 characters).', 'success' => false)));
        }

        $stmt = $this->connection->prepare('SELECT username FROM users WHERE username = ?');

        $stmt->bind_param('s', $this->name);


        $stmt->execute();

        $userExists = !empty($stmt->get_result()->fetch_array(MYSQLI_ASSOC)['username']);


        if ($userExists) {
            http_response_code(200);
            die(json_encode(array('message' => 'This username is already taken.', 'success' => false)));
        }

        $passwordHash = password_hash( $this->password, PASSWORD_BCRYPT);
        $now = date('Y-m-d H:i:s');

        $stmt = $this->connection->prepare('INSERT INTO users ( username, password, register_date, modification_date ) VALUES ( ?, ?, ?, ? )');

        $stmt->bind_param('ssss', $this->name, $passwordHash, $now, $now);

        return $stmt->execute();
    }
    public function restorePassword(): bool {

        function generateRandomNewPasswd($length = 16): string {
            return substr(str_shuffle(str_repeat($x='0123456789#abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@$%&*?#', (int) ceil($length/strlen($x)) )),1,$length);
        }

        $this->password = generateRandomNewPasswd();
        $passwordHash = password_hash( $this->password, PASSWORD_BCRYPT);

        $stmt = $this->connection->prepare('SELECT username FROM users WHERE username = ?');

        $stmt->bind_param('s', $this->name);


        $stmt->execute();

        $userExists = !empty($stmt->get_result()->fetch_array(MYSQLI_ASSOC)['username']);

        if ($userExists) {

            $now = date('Y-m-d H:i:s');
            $stmt = $this->connection->prepare('UPDATE users SET `password`=?, modification_date=? WHERE username=?');

            $stmt->bind_param('sss', $passwordHash, $now,  $this->name);

            return $stmt->execute();
        }
        http_response_code(200);
        die(json_encode(array('message' => 'No user found.', 'success' => false)));
    }
    public function changePassword(): bool {

        $passwordMeetsRequirements = (preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*([!@$%&*?#])).+$/', $this->password) && strlen($this->password) > 7);

        if (!$passwordMeetsRequirements) {
            die(json_encode(array('message' => "Password doesn't meet requirements. It has to be at least 8 chars long and contain at least one lower and uppercase letter, number and special character(!@$%&*?#)", 'success' => false)));
        }

        $passwordHash = password_hash($this->password, PASSWORD_BCRYPT);
        $now = date('Y-m-d H:i:s');

        $stmt = $this->connection->prepare('UPDATE users SET password=?, modification_date=? WHERE username = ?');

        $stmt->bind_param('sss', $passwordHash, $now, $this->name);


        return $stmt->execute();
    }
    public function delete(): bool {

        $stmt = $this->connection->prepare('SELECT ownership.id FROM ownership WHERE username=?');

        $stmt->bind_param('s', $this->name);

        if (!$stmt->execute()) {
            http_response_code(500);
            die(json_encode(array('message' => 'Unknown internal server error.', 'success' => false)));
        }
        if ($stmt->get_result()->fetch_array(MYSQLI_ASSOC)) {
            return false;
        }
        $stmt = $this->connection->prepare('UPDATE sells SET username="anonymous" WHERE username=?');

        $stmt->bind_param('s', $this->name);

        if (!$stmt->execute()) {
            http_response_code(500);
            die(json_encode(array('message' => 'Unknown internal server error', 'success' => false)));
        }

        $stmt = $this->connection->prepare('DELETE FROM users WHERE username=?');

        $stmt->bind_param('s', $this->name);

        $stmt->execute();

        return true;

    }

    //except password and password hash
    public function getAllProps(): mysqli_result {

        $stmt = $this->connection->prepare('SELECT username, money, register_date, modification_date FROM users WHERE username=?');

        $stmt->bind_param('s', $this->name);

        if (!$stmt->execute()) {
            http_response_code(500);
            die(json_encode(array('message' => 'Unknown internal server error', 'success' => false)));
        }

        return $stmt->get_result();
    }

    public function getPassword(): string {
        return $this->password;
    }
    public function setPassword($password): void {
        $this->password = $password;
    }
    public  function sellAllStocksAsAnonymous(): void {
        $stmt = $this->connection->prepare('SELECT ownership.id, stocks.id as stockId, stocks.name, stocks.short, ownership.number, MIN(sells.price_per_one) as price FROM stocks INNER JOIN ownership ON stocks.id = ownership.stock_id LEFT JOIN sells ON sells.stock_id = ownership.stock_id WHERE ownership.username = ? GROUP BY ownership.id');

        $stmt->bind_param('s', $this->name);

        if (!$stmt->execute()) {
            http_response_code(500);
            die(json_encode(array('message' => 'Unknown internal server error', 'success' => false)));
        }

        $allOwnerships = array();
        $result = $stmt->get_result();
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            array_push($allOwnerships, $row);
        }

        foreach ($allOwnerships as $ownership) {

            $stmt = $this->connection->prepare('DELETE FROM `ownership` WHERE id=?');

            $stmt->bind_param('i', $ownership['id']);

            if (!$stmt->execute()) {
                http_response_code(500);
                die(json_encode(array('message' => 'Unknown internal server error', 'success' => false)));
            }

            $pricePerOne = $ownership['price'] ? $ownership['price'] : 100;

            $stmt = $this->connection->prepare('INSERT INTO `sells`(`username`, `stock_id`, `number`, `price_per_one`) VALUES ("anonymous", ?, ?, ?)');

            $stmt->bind_param('sii', $ownership['stockId'], $ownership['number'], $pricePerOne);

            if (!$stmt->execute()) {
                http_response_code(500);
                die(json_encode(array('message' => 'Unknown internal server error', 'success' => false)));
            }

        }
    }
}