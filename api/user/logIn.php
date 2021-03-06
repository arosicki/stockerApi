<?php

declare(strict_types=1);

use Src\Database\Database;
use Src\objects\Token;
use Src\objects\User;

require_once '../../settings/settings.php';
require_once '../../src/Database/Database.php';
require_once '../../src/objects/User.php';
require_once '../../src/objects/Token.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Max-Age: 3600');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

$database = new Database();
$dbConnection = $database->connect();

$data = json_decode(file_get_contents('php://input'));

$dataEmpty = (empty($data->username) || empty($data->password));

if($dataEmpty) {
    http_response_code(400);
    die(json_encode(array('message' => 'Unable to log In. Data is incomplete.', 'success' => false)));
}

$user = new User($dbConnection, $data->username, $data->password);
if(!$user->logIn()) {
    http_response_code(200);
    die(json_encode(array('message' => 'Wrong username or password', 'success' => false)));
}

$token = new Token($dbConnection, $data->username, '');
if (!$token->generate()) {
    http_response_code(500);
    die(json_encode(array('message' => 'Token generation error', 'success' => false)));
}
http_response_code(200);
echo json_encode(array('token' => $token->getToken(), 'expires' => $token->getExpires(), 'success' => true));