<?php

/**
 * validate_file.php is a demo script for validating COUNTER report files
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

require __DIR__ . '/../vendor/autoload.php';

$options = getopt('f:d::j::m::t::x::');
if (! isset($options['f']) || (isset($options['x']) && empty($options['x']))) {
    print <<<EOS
usage: php {$argv[0]} -f<filename> [-d] [-j] [-m] [-t] [-x<filename>]
Validate a COUNTER R5 or R5.1 report file.

Options:
  -f<filename> COUNTER report file to validate
  -d           debug - show internal representation
  -j           show JSON representation (if report is usable)
  -m           show memory used
  -t           show time used
  -x<filename> save validation report as Excel file

EOS;
    exit(5);
}

$filename = $options['f'];
$debug = isset($options['d']);
$showJson = isset($options['j']);
$showMemory = isset($options['m']);
$showTime = isset($options['t']);
$xlsxResult = isset($options['x']);

$startTime = microtime(true);
try {
    $report = \ubfr\c5tools\Report::fromFile($filename);
    $checkResult = $report->getCheckResult();
} catch (Exception $e) {
    $report = null;
    $checkResult = new \ubfr\c5tools\CheckResult();
    $checkResult->addFatalError($e->getMessage(), $e->getMessage());
}

if ($xlsxResult) {
    print('Saving validation report in ' . $options['x'] . "\n");
    $checkResult->saveXlsx($options['x'], 0);
} else {
    print $checkResult->asText(0);
}
if ($showTime) {
    $endTime = microtime(true);
    printf("\nTime: %.2f s\n", $endTime - $startTime);
}
if ($showMemory) {
    printf("\nMemory: %.2f MB\n", memory_get_peak_usage() / 1024 / 1024);
}
if ($report !== null) {
    print("\nReport is " . ($report->isUsable() ? "usable" : "unusable") . "\n");
    if ($debug) {
        print("\n");
        $report->debug();
    }
    if ($showJson && $report->isUsable()) {
        print("\n");
        print(json_encode($report->asJson(), JSON_PRETTY_PRINT));
        print("\n");
    }
}
