<?php

declare(strict_types=1);

namespace Src\objects;

use DateInterval;
use DateTime;
use mysqli;


class Token {

    private string $expires;

    public function __construct(
        private mysqli $connection,
        private string $username,
        private string $token = ''
    ) { }

    public function generate(): bool {


        if (empty($_ENV['TOKEN_TTL'])) {
            die(json_encode(array('message' => 'Token generation failed: no TOKEN_TTL', 'success' => false)));
        }

        //delete outdated tokens
        $stmt = $this->connection->prepare('DELETE FROM `tokens` WHERE expiry_date < ?');

        $now =  date('Y-m-d H:i:s')

        $stmt->bind_param('s', $now);

        $stmt->execute();

        $token =  random_bytes(64);
        $this->token = bin2hex($token);
        $this->expires = date('Y-m-d H:i:s', time() + $_ENV['TOKEN_TTL']);

        $tokenHash = password_hash( $this->token, PASSWORD_BCRYPT);

        $stmt = $this->connection->prepare('INSERT INTO tokens ( username, token, expiry_date ) VALUES ( ?, ?, ? )');

        $stmt->bind_param('sss', $this->username, $tokenHash, $this->expires);

        return $stmt->execute();
    }
    public function validate(): bool {

        $now = date('Y-m-d H:i:s');

        $stmt = $this->connection->prepare('SELECT token FROM tokens WHERE username=? AND expiry_date > ?');

        $stmt->bind_param('ss', $this->username, $now);

        $stmt->execute();

        $stmt->bind_result($token);

        while ($stmt->fetch()) {
            if ( password_verify($this->token, $token) ) {
                return true;
            }
        }
        return false;
    }

    public function getToken(): string {
        return $this->token;
    }
    public function getExpires(): string {
        return $this->expires;
    }
}