<?php

declare(strict_types=1);

use Src\Database\Database;
use Src\objects\User;

require_once '../../settings/settings.php';
require_once '../../src/Database/Database.php';
require_once '../../src/objects/User.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Max-Age: 3600');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

$database = new Database();
$dbConnection = $database->connect();

$data = json_decode(file_get_contents('php://input'));

if(empty($data->username)) {
    http_response_code(400);
    die(json_encode(array('message' => 'Data incomplete', 'success' => false)));
}
$user = new User($dbConnection, $data->username);
if(!$user->restorePassword()) {
    http_response_code(500);
    die(json_encode(array('message' => 'Unknown internal server error.', 'success' => false)));

}
http_response_code(200);
echo json_encode(array('New Password' => $user->getPassword(), 'success' => true));