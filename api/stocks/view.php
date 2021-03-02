<?php

declare(strict_types=1);

use Src\Database\Database;
use Src\objects\Stock;

require_once '../../settings/settings.php';
require_once '../../src/Database/Database.php';
require_once '../../src/objects/Stock.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');

$database = new Database();
$dbConnection = $database->connect();

$stock = new Stock($dbConnection, (int)$_GET['id'], $_GET['name']);

if (!empty($_GET['id'])) {

    $result = array('result' => $stock->readOne());
    $result['success'] = true;
    http_response_code(200);
    die(json_encode($result));
}

if (!empty($_GET['name'])) {

    $result = array('result' => $stock->queryAll());
    $result['success'] = true;
    http_response_code(200);
    die(json_encode($result));

}

$result = array('result' => $stock->readAll());
$result['success'] = true;
http_response_code(200);
echo json_encode($result);