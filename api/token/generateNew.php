<?php

declare(strict_types=1);

use Src\Database\Database;
use Src\objects\Token;

require_once '../../settings/settings.php';
require_once '../../src/Database/Database.php';
require_once '../../src/objects/Token.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Max-Age: 3600');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

$database = new Database();
$dbConnection = $database->connect();

$data = json_decode(file_get_contents('php://input'));

$dataEmpty = (empty($data->username) || empty($data->token));

if($dataEmpty) {
    http_response_code(400);
    die(json_encode(array('message' => 'Unable change password. Data is incomplete.', 'success' => false)));
}

$token = new Token($dbConnection, $data->username, $data->token);

if (!$token->validate()) {
    http_response_code(401);
    die(json_encode(array('message' => 'Incorrect token or username.', 'success' => false)));
}

$newToken = new Token($dbConnection, $data->username);

if (!$newToken->generate()) {
    http_response_code(500);
    die(json_encode(array('message' => 'Unknown internal server error', 'success' => false)));
}

http_response_code(200);
echo json_encode(array('token' => $newToken->getToken(), 'expires' => $newToken->getExpires(), 'success' => true));
