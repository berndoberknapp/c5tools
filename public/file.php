<?php

require __DIR__ . '/../vendor/autoload.php';

use \ubfr\c5tools\Report;


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}

$tmpfile = tmpfile();
$path = stream_get_meta_data($tmpfile)['uri'];
copy("php://input", $path);

$report = null;
try {
    $report = Report::fromFile(
        $path,
        $_GET["extension"],
    );
    $checkResult = $report->getCheckResult();
} catch (Exception $e) {
    $checkResult = new \ubfr\c5tools\CheckResult();
    $checkResult->addFatalError($e->getMessage(), $e->getMessage());
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    "memory" => memory_get_peak_usage(true),
    "result" => json_decode($checkResult->asJson()),
]);
