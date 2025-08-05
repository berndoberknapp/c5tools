<?php

require __DIR__ . '/../vendor/autoload.php';

use \ubfr\c5tools\Report;


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit();
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    "status" => "OK",
    "about" => "This is COUNTER Validation module",
    "version" => "1.0.0",
    "info" => "Use either the /file.php or /api.php endpoint to validate COUNTER reports from a file or an URL",
]);
