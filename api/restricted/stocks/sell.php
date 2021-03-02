<?php

declare(strict_types=1);

use Src\Database\Database;
use Src\objects\Stock;
use Src\objects\Token;

require_once '../../../settings/settings.php';
require_once '../../../src/Database/Database.php';
require_once '../../../src/objects/Token.php';
require_once '../../../src/objects/Stock.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Max-Age: 3600');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

$database = new Database();
$dbConnection = $database->connect();

$data = json_decode(file_get_contents('php://input'));

$dataEmpty = (empty($data->username) || empty($data->token) || empty($data->number) || empty($data->pricePerOne) || empty($data->stockId));

if ($dataEmpty) {
    http_response_code(400);
    die(json_encode(array('message' => 'Data is incomplete.', 'success' => false)));
}

$token = new Token($dbConnection, $data->username, $data->token);

if (!$token->validate()) {
    http_response_code(401);
    die(json_encode(array('message' => 'Incorrect token or username.', 'success' => false)));
}

$stock = new Stock($dbConnection, $data->stockId, null, $data->username, $data->number, $data->pricePerOne);

if (!$stock->sell()) {
    http_response_code(500);
    die(json_encode(array('message' => "Internal server error. Might've messed a lot of things up", 'success' => false)));
}
http_response_code(200);
die(json_encode(array('success' => true)));
