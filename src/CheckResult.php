<?php

/**
 * CheckResult collects validation notices, warnings and errors and generates validation reports
 *
 * CheckResults are created by adding notices, warnings and errors and the report header. Validation reports can
 * be returned as arrays, spreadsheets, and in text and JSON format, either in full detail or (not for JSON) as
 * summaries with a threshold that determines the level of detail. The JSON returned by {@see CheckResult::asJson()}
 * can be used to recreate the CheckResult with {@see CheckResult::fromJson()}.
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ubfr\c5tools\data\ReportHeader;

class CheckResult
{
    public const CR_PASSED = 0;

    public const CR_NOTICE = 1;

    public const CR_WARNING = 2;

    public const CR_ERROR = 3;

    public const CR_CRITICAL = 4;

    public const CR_FATAL = 5;

    protected static array $levelNames = [
        self::CR_PASSED => 'Passed',
        self::CR_NOTICE => 'Notice',
        self::CR_WARNING => 'Warning',
        self::CR_ERROR => 'Error',
        self::CR_CRITICAL => 'Critical error',
        self::CR_FATAL => 'Fatal error'
    ];

    protected int $result = self::CR_PASSED;

    protected array $messages = [];

    protected array $summaryMessages = [];

    protected array $numberOfMessages = [
        self::CR_PASSED => 0,
        self::CR_NOTICE => 0,
        self::CR_WARNING => 0,
        self::CR_ERROR => 0,
        self::CR_CRITICAL => 0,
        self::CR_FATAL => 0
    ];

    protected array $header = [];

    protected array $reportinfo = [];

    protected string $datetime;

    public function __construct()
    {
        $this->datetime = date('Y-m-d H:i:s');
    }

    public function getResult(): int
    {
        return $this->result;
    }

    public function getHeader(): array
    {
        return $this->header;
    }

    public function getReportInfo(): array
    {
        return $this->reportinfo;
    }

    public function getDatetime(): string
    {
        return $this->datetime;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function hasError(): bool
    {
        return ($this->result >= self::CR_ERROR);
    }

    public static function getResultNames(): array
    {
        return self::$levelNames;
    }

    public function getResultName(): string
    {
        return self::$levelNames[$this->result];
    }

    public function setReportHeader(ReportHeader $reportHeader): void
    {
        $institutionName = $reportHeader->get('Institution_Name') ?? '(Institution_Name missing)';
        $created = $reportHeader->get('Created') ?? '(Created missing or invalid)';
        $createdBy = $reportHeader->get('Created_By') ?? '(Created_By missing)';
        $beginDate = $reportHeader->getBeginDate() ?? '(Begin_Date missing or invalid)';
        $endDate = $reportHeader->getEndDate() ?? '(End_Date missing or invalid)';

        $this->header['result'] = [
            'Validation Result for COUNTER Release ' . $reportHeader->get('Release') . ' Report',
            '',
            $reportHeader->get('Report_Name') . ' (' . $reportHeader->get('Report_ID') . ')',
            'for ' . $institutionName,
            'created ' . $created . ' by ' . $createdBy,
            'covering ' . $beginDate . ' to ' . $endDate,
            '(please see the Report Header sheet for details)'
        ];
        $this->header['report'] = $reportHeader->asCells();

        $this->reportinfo['report_id'] = $reportHeader->getReportId();
        $this->reportinfo['format'] = $reportHeader->getFormat();
        $this->reportinfo['cop_version'] = $reportHeader->getConfig()->getRelease();
        $this->reportinfo['institution_name'] = $institutionName;
        $this->reportinfo['created'] = $created;
        $this->reportinfo['created_by'] = $createdBy;
        $this->reportinfo['begin_date'] = $beginDate;
        $this->reportinfo['end_date'] = $endDate;
    }

    protected function escapeUnicode(?string $string): ?string
    {
        if ($string === null) {
            return $string;
        } else {
            $matches = [];
            if (preg_match('/[^\p{L}\p{M}\p{N}\p{P}\p{S}\p{Zs}]/u', $string, $matches)) {
                foreach ($matches as $match) {
                    $string = str_replace($match, substr(json_encode($match), 1, -1), $string);
                }
            }
            return $string;
        }
    }

    protected function addMessage(
        int $level,
        string $summary,
        string $message,
        ?string $position,
        ?string $data,
        ?string $hint
    ): void {
        if ($this->result < $level) {
            $this->result = $level;
        }
        $this->messages[] = [
            'l' => $level,
            's' => $this->escapeUnicode($summary),
            'm' => $this->escapeUnicode($message),
            'p' => $position,
            'd' => $this->escapeUnicode($data),
            'h' => $hint
        ];
        $this->numberOfMessages[$level]++;
    }

    protected function addSummaryMessage(
        int $level,
        string $summary,
        string $message,
        ?string $position,
        ?string $data,
        ?string $hint
    ): void {
        $this->addMessage($level, $summary, $message, $position, $data, $hint);

        if ($hint !== null && $hint !== '') {
            $summary = $this->escapeUnicode($summary) . ', ' . $hint;
        }
        if (! isset($this->summaryMessages[$level])) {
            $this->summaryMessages[$level] = [];
        }
        if (! isset($this->summaryMessages[$level][$summary])) {
            $this->summaryMessages[$level][$summary] = 1;
        } else {
            $this->summaryMessages[$level][$summary]++;
        }
    }

    public function addFatalError(string $summary, string $message): void
    {
        $this->addSummaryMessage(self::CR_FATAL, $summary, $message, null, null, null);
    }

    public function addCriticalError(
        string $summary,
        string $message,
        ?string $position,
        ?string $data,
        ?string $hint = null
    ): void {
        $this->addSummaryMessage(self::CR_CRITICAL, $summary, $message, $position, $data, $hint);
    }

    public function addError(
        string $summary,
        string $message,
        ?string $position,
        ?string $data,
        ?string $hint = null
    ): void {
        $this->addSummaryMessage(self::CR_ERROR, $summary, $message, $position, $data, $hint);
    }

    public function addWarning(
        string $summary,
        string $message,
        ?string $position,
        ?string $data,
        ?string $hint = null
    ): void {
        $this->addSummaryMessage(self::CR_WARNING, $summary, $message, $position, $data, $hint);
    }

    public function addNotice(
        string $summary,
        string $message,
        ?string $position,
        ?string $data,
        ?string $hint = null
    ): void {
        $this->addSummaryMessage(self::CR_NOTICE, $summary, $message, $position, $data, $hint);
    }

    public static function getLevelNames(): array
    {
        return self::$levelNames;
    }

    public function getLevelName(int $level): string
    {
        if (! isset(self::$levelNames[$level])) {
            throw new \InvalidArgumentException("level {$level} invalid");
        }
        return self::$levelNames[$level];
    }

    public function getLevelForName(string $name): int
    {
        foreach (self::$levelNames as $level => $levelName) {
            if ($name === $levelName) {
                return $level;
            }
        }
        throw new \InvalidArgumentException("level name {$name} invalid");
    }

    public function getNumberOfMessages(int $level): int
    {
        if (! isset(self::$levelNames[$level])) {
            throw new \InvalidArgumentException("level {$level} invalid");
        }

        return $this->numberOfMessages[$level];
    }

    public static function fromJson($json): self
    {
        static $requiredProperties = [
            'datetime',
            'result',
            'header',
            'messages'
        ];
        static $optionalProperties = [
            'reportinfo'
        ];

        $checkResult = new self();

        $object = json_decode($json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('error decoding JSON - ' . json_last_error_msg());
        }
        if (! is_object($object)) {
            throw new \InvalidArgumentException('JSON invalid - top level element must be object');
        }
        $array = (array) $object;

        $missingProperties = array_diff($requiredProperties, array_keys($array));
        if (! empty($missingProperties)) {
            throw new \InvalidArgumentException(
                'JSON invalid - properties (' . implode(', ', $missingProperties) . ') missing'
            );
        }
        $invalidProperties = array_diff(array_keys($array), array_merge($requiredProperties, $optionalProperties));
        if (! empty($invalidProperties)) {
            throw new \InvalidArgumentException(
                'JSON invalid - properties (' . implode(', ', $invalidProperties) . ') invalid'
            );
        }

        foreach ($array as $key => $value) {
            $method = $key . 'FromJson';
            $checkResult->$method($value);
        }

        if (is_string($array['result'])) {
            $array['result'] = $checkResult->getLevelForName($array['result']);
        }
        if ($checkResult->getResult() !== $array['result']) {
            throw new \InvalidArgumentException("JSON invalid - result value doesn't match messages");
        }

        return $checkResult;
    }

    protected function datetimeFromJson($datetime): void
    {
        if (! is_string($datetime)) {
            throw new \InvalidArgumentException('JSON invalid - datetime value must be string');
        }
        if (
            ! preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/', $datetime) ||
            strtotime($datetime) === false
        ) {
            throw new \InvalidArgumentException("JSON invalid - datetime value ({$datetime}) invalid");
        }

        $this->datetime = $datetime;
    }

    protected function resultFromJson($result): void
    {
        if (is_int($result)) {
            $this->getLevelName($result);
        } elseif (is_string($result)) {
            $this->getLevelForName($result);
        } else {
            throw new \InvalidArgumentException('JSON invalid - result value must be integer or string');
        }

        // result is not set, but computed from messages and then checked
    }

    protected function headerFromJson($header): void
    {
        $properties = [
            'result',
            'report'
        ];

        if (! is_object($header)) {
            throw new \InvalidArgumentException('JSON invalid -  header must be object');
        }
        $header = (array) $header;

        if (empty($header)) {
            $this->header = [];
            return;
        }

        $missingProperties = array_diff($properties, array_keys($header));
        if (! empty($missingProperties)) {
            throw new \InvalidArgumentException(
                'JSON invalid - properties (' . implode(', ', $missingProperties) . ') missing'
            );
        }
        $invalidProperties = array_diff(array_keys($header), $properties);
        if (! empty($invalidProperties)) {
            throw new \InvalidArgumentException(
                'JSON invalid - properties (' . implode(', ', $invalidProperties) . ') invalid'
            );
        }

        if (! is_array($header['result'])) {
            throw new \InvalidArgumentException("JSON invalid -  header.result must be array");
        }
        foreach ($header['result'] as $value) {
            if (! is_string($value)) {
                throw new \InvalidArgumentException("JSON invalid -  header.result values must be strings");
            }
        }
        if (! is_object($header['report'])) {
            throw new \InvalidArgumentException("JSON invalid -  header.report must be object");
        }
        foreach ($header['report'] as $value) {
            if (! is_string($value)) {
                throw new \InvalidArgumentException("JSON invalid -  header.result values must be strings");
            }
        }

        $this->header = [
            'result' => $header['result'],
            'report' => (array) $header['report']
        ];
    }

    protected function messagesFromJson($messages): void
    {
        if (! is_array($messages)) {
            throw new \InvalidArgumentException('JSON invalid -  messages must be array');
        }

        foreach ($messages as $index => $message) {
            $position = "messages[{$index}]";
            if (! is_object($message)) {
                throw new \InvalidArgumentException("JSON invalid - {$position} must be object");
            }
            $this->messageFromJson($position, (array) $message);
        }
    }

    protected function messageFromJson(string $position, array $message): void
    {
        static $properties = [
            'l',
            's',
            'm',
            'p',
            'd',
            'h'
        ];

        $missingProperties = array_diff($properties, array_keys($message));
        if (! empty($missingProperties)) {
            throw new \InvalidArgumentException(
                "JSON invalid - {$position} properties (" . implode(', ', $missingProperties) . ') missing'
            );
        }
        $invalidProperties = array_diff(array_keys($message), $properties);
        if (! empty($invalidProperties)) {
            throw new \InvalidArgumentException(
                "JSON invalid - {$position} properties (" . implode(', ', $invalidProperties) . ') invalid'
            );
        }

        if (
            ! (is_int($message['l']) || is_string($message['l'])) || ! is_string($message['s']) ||
            ! is_string($message['m']) || ! ($message['p'] === null || is_string($message['p'])) ||
            ! ($message['d'] === null || is_string($message['d'])) ||
            ! ($message['h'] === null || is_string($message['h']))
        ) {
            throw new \InvalidArgumentException("JSON invalid - {$position} properties type mismatch");
        }
        if (is_string($message['l'])) {
            $message['l'] = $this->getLevelForName($message['l']);
        }
        if (
            $message['l'] < \ubfr\c5tools\CheckResult::CR_NOTICE ||
            $message['l'] > \ubfr\c5tools\CheckResult::CR_FATAL
        ) {
            throw new \InvalidArgumentException("JSON invalid - {$position}[l] value (" . $message['l'] . ') invalid');
        }
        $message['p'] = $this->getPositionForName($message['p']);

        $this->addSummaryMessage(
            $message['l'],
            $message['s'],
            $message['m'],
            $message['p'],
            $message['d'],
            $message['h']
        );
    }

    protected function reportinfoFromJson($reportinfo): void
    {
        static $properties = [
            'report_id',
            'format',
            'cop_version',
            'institution_name',
            'created',
            'created_by',
            'begin_date',
            'end_date'
        ];

        if (! is_object($reportinfo)) {
            throw new \InvalidArgumentException('JSON invalid - reportinfo must be object');
        }
        $reportinfo = (array) $reportinfo;

        if (empty($reportinfo)) {
            $this->reportinfo = [];
            return;
        }

        $missingProperties = array_diff($properties, array_keys($reportinfo));
        if (! empty($missingProperties)) {
            throw new \InvalidArgumentException(
                'JSON invalid - properties (' . implode(', ', $missingProperties) . ') missing'
            );
        }
        $invalidProperties = array_diff(array_keys($reportinfo), $properties);
        if (! empty($invalidProperties)) {
            throw new \InvalidArgumentException(
                'JSON invalid - properties (' . implode(', ', $invalidProperties) . ') invalid'
            );
        }

        foreach ($reportinfo as $value) {
            if ($value !== null && ! is_string($value)) {
                throw new \InvalidArgumentException('JSON invalid - reportinfo values must be strings (or null)');
            }
        }

        $this->reportinfo = $reportinfo;
    }

    public function asJson(): string
    {
        usort($this->messages, array(
            '\ubfr\c5tools\CheckResult',
            'sortMessages'
        ));

        $messages = [];
        foreach ($this->messages as $message) {
            $messages[] = [
                // 'i' => substr($this->getLevelName($message['l']), 0, 1) . '000', // TODO: ID
                'l' => $this->getLevelName($message['l']),
                's' => $message['s'],
                'm' => $message['m'],
                'p' => $this->getPositionName($message),
                'd' => $message['d'],
                'h' => $message['h']
            ];
        }

        return json_encode(
            [
                'datetime' => $this->getDatetime(),
                'result' => $this->getResultName(),
                'header' => (object) $this->getHeader(),
                'reportinfo' => (object) $this->getReportInfo(),
                'messages' => $messages
            ]
        );
    }

    public function asText(int $summaryThreshold = 25): string
    {
        if (! is_int($summaryThreshold) || $summaryThreshold < 0) {
            throw new \InvalidArgumentException("summary threshold {$summaryThreshold} invalid");
        }

        usort($this->messages, array(
            '\ubfr\c5tools\CheckResult',
            'sortMessages'
        ));

        $text = '';
        foreach ($this->messages as $message) {
            $text .= $this->getMessageAsText($message, $summaryThreshold);
        }
        if ($summaryThreshold > 0) {
            foreach ($this->summaryMessages as $level => $levelSummaryMessages) {
                foreach ($levelSummaryMessages as $summary => $numMessages) {
                    if ($numMessages >= $summaryThreshold) {
                        $text .= $this->getSummaryMessageAsText($level, $summary, $numMessages);
                    }
                }
            }
        }
        $text .= 'Result at ' . $this->datetime . ': ' . $this->getLevelName($this->result) . "\n";

        return $text;
    }

    public function asArray(int $summaryThreshold = 25): array
    {
        if (! is_int($summaryThreshold) || $summaryThreshold < 0) {
            throw new \InvalidArgumentException("summary threshold {$summaryThreshold} invalid");
        }

        usort($this->messages, array(
            '\ubfr\c5tools\CheckResult',
            'sortMessages'
        ));

        $array = [];
        foreach ($this->messages as $message) {
            if (! $this->useSummaryMessage($message, $summaryThreshold)) {
                $array[] = [
                    'number' => 1,
                    'level' => $message['l'],
                    'header' => $this->getLevelPositionName($message),
                    'data' => ($message['d'] ?? ''),
                    'message' => $message['m'] . ($message['h'] !== null ? ', ' . $message['h'] : '') . '.'
                ];
            }
        }
        if ($summaryThreshold > 0) {
            foreach ($this->summaryMessages as $level => $levelSummaryMessages) {
                foreach ($levelSummaryMessages as $summary => $numMessages) {
                    if ($numMessages >= $summaryThreshold) {
                        $array[] = [
                            'number' => $numMessages,
                            'level' => $level,
                            'header' => $numMessages . ' ' . $this->getLevelName($level) .
                            ($numMessages !== 1 ? 's' : ''),
                            'data' => '(Summary)',
                            'message' => $summary . '.'
                        ];
                    }
                }
            }
        }

        return $array;
    }

    public function asSpreadsheet(int $summaryThreshold = 500): Spreadsheet
    {
        if (! is_int($summaryThreshold) || $summaryThreshold < 0) {
            throw new \InvalidArgumentException("summary threshold {$summaryThreshold} invalid");
        }

        $spreadsheet = $this->getResultSpreadsheet($summaryThreshold);
        $this->addResultHeader($spreadsheet);
        $this->addReportHeader($spreadsheet);

        return $spreadsheet;
    }

    public function saveXlsx(string $filename, int $summaryThreshold): void
    {
        $spreadsheet = $this->asSpreadsheet($summaryThreshold);
        $writer = new Xlsx($spreadsheet);
        $writer->save($filename);
    }

    protected function getResultSpreadsheet(int $summaryThreshold = 500): Spreadsheet
    {
        if (! is_int($summaryThreshold) || $summaryThreshold < 0) {
            throw new \InvalidArgumentException("summary threshold {$summaryThreshold} invalid");
        }

        usort($this->messages, array(
            '\ubfr\c5tools\CheckResult',
            'sortMessages'
        ));

        $spreadsheet = new Spreadsheet();
        $defaultStyle = $spreadsheet->getDefaultStyle();
        $defaultStyle->getFont()->setName('Arial');
        $defaultStyle->getFont()->setSize(11);
        $defaultStyle->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Validation Result');

        $numFatal = $this->getNumberOfMessages(self::CR_FATAL);
        $numCritical = $this->getNumberOfMessages(self::CR_CRITICAL);
        $numErrors = $numFatal + $numCritical + $this->getNumberOfMessages(self::CR_ERROR);
        $numWarnings = $this->getNumberOfMessages(self::CR_WARNING);
        $numNotices = $this->getNumberOfMessages(self::CR_NOTICE);
        $numMessages = $numErrors + $numWarnings + $numNotices;
        $row = 1;

        if ($numFatal !== 0) {
            $result = 'The validation failed with a fatal error at ';
        } elseif ($numCritical !== 0) {
            $result = 'The validation failed with ' . ($numCritical > 1 ? 'critical errors at ' : 'a critical error at ');
        } elseif ($numErrors + $numWarnings > 0) {
            $result = 'The report did not pass the validation at ';
        } elseif ($numNotices !== 0) {
            $result = 'The report passed the validation at ';
        } else {
            $result = 'The report passed the validation at ';
        }
        $result .= $this->datetime . '.';
        if ($numMessages !== 0) {
            $worksheet->mergeCells("A{$row}:D{$row}");
        }
        $worksheet->setCellValue("A{$row}", $result);
        $row++;

        if ($numErrors === 0 && $numWarnings === 0 && $numNotices === 0) {
            $worksheet->getColumnDimension('A')->setAutoSize(true);
            return $spreadsheet;
        }

        $review = [];
        if ($numErrors > 0) {
            $review[] = "{$numErrors} error" . ($numErrors > 1 ? 's' : '');
        }
        if ($numWarnings > 0) {
            $review[] = "{$numWarnings} warning" . ($numWarnings > 1 ? 's' : '');
        }
        if ($numNotices > 0) {
            $review[] = "{$numNotices} notice" . ($numNotices > 1 ? 's' : '');
        }
        $worksheet->mergeCells("A{$row}:D{$row}");
        $worksheet->setCellValue("A{$row}", 'Please review the ' . implode(' and ', $review) . '.');
        $row += 2;

        $columns = [
            'A' => 'Level',
            'B' => 'Position',
            'C' => 'Data',
            'D' => 'Message'
        ];
        foreach ($columns as $column => $header) {
            $worksheet->setCellValue("{$column}{$row}", $header);
        }
        $worksheet->setAutoFilter("A{$row}:D{$row}");
        $row++;
        $worksheet->freezePane("A{$row}");

        foreach ($this->messages as $message) {
            if (! $this->useSummaryMessage($message, $summaryThreshold)) {
                $worksheet->setCellValue("A{$row}", $this->getLevelName($message['l']));
                $worksheet->setCellValue("B{$row}", ucfirst($this->getPositionName($message)));
                $worksheet->setCellValue("C{$row}", $message['d'] !== null ? $message['d'] : '');
                $worksheet->setCellValue(
                    "D{$row}",
                    $message['m'] . ($message['h'] !== null ? ', ' . $message['h'] : '') . '.'
                );
                $row++;
            }
        }
        if ($summaryThreshold > 0) {
            foreach ($this->summaryMessages as $level => $levelSummaryMessages) {
                foreach ($levelSummaryMessages as $summary => $numMessages) {
                    if ($numMessages >= $summaryThreshold) {
                        $worksheet->setCellValue("A{$row}", $this->getLevelName($level));
                        $worksheet->setCellValue("B{$row}", '(Summary)');
                        $worksheet->setCellValue("C{$row}", '(Summary)');
                        $worksheet->setCellValue(
                            "D{$row}",
                            $numMessages . ' ' . $this->getLevelName($level) . ($numMessages !== 1 ? 's' : '') . ': ' .
                            $summary . '.'
                        );
                        $row++;
                    }
                }
            }
        }

        foreach (array_keys($columns) as $column) {
            $worksheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    protected function addResultHeader(Spreadsheet $spreadsheet): void
    {
        if (! isset($this->header['result'])) {
            return;
        }

        $row = 1;
        $resultsheet = $spreadsheet->getActiveSheet();
        $resultsheet->insertNewRowBefore($row, count($this->header['result']) + 1);
        foreach ($this->header['result'] as $header) {
            if (array_sum($this->numberOfMessages) !== 0) {
                $resultsheet->mergeCells("A{$row}:D{$row}");
            }
            $resultsheet->setCellValue("A{$row}", $header);
            $row++;
        }
    }

    protected function addReportHeader(Spreadsheet $spreadsheet): void
    {
        if (! isset($this->header['report'])) {
            return;
        }

        $headersheet = new Worksheet();
        $headersheet->setTitle('Report Header');
        $spreadsheet->addSheet($headersheet);

        foreach ($this->header['report'] as $cell => $value) {
            $headersheet->setCellValue($cell, $value);
        }
        $headersheet->getColumnDimension('A')->setAutoSize(true);
        $headersheet->getColumnDimension('B')->setAutoSize(true);
    }

    protected function sortMessages($a, $b): int
    {
        // fatal error always at the end
        if ($a['l'] === self::CR_FATAL) {
            return 1;
        }
        if ($b['l'] === self::CR_FATAL) {
            return - 1;
        }

        // messages without position always at the top
        if ($a['p'] === null || $a['p'] === '') {
            return - 1;
        }
        if ($b['p'] === null || $b['p'] === '') {
            return 1;
        }

        $ma = [];
        $mb = [];
        if (preg_match('/^([A-Z]*)([0-9]*)$/', $a['p'], $ma) && preg_match('/^([A-Z]*)([0-9]*)$/', $b['p'], $mb)) {
            // tabular report: sort by row, column
            if ($ma[2] !== '' && $mb[2] !== '') {
                if ($ma[2] < $mb[2]) {
                    return - 1;
                }
                if ($ma[2] > $mb[2]) {
                    return 1;
                }
            } elseif ($ma[2] !== '') {
                return - 1;
            } elseif ($mb[2] !== '') {
                return 1;
            }
            if ($ma[1] !== '' && $mb[1] !== '') {
                if (strlen($ma[1]) > strlen($mb[1])) {
                    return 1;
                } elseif (strlen($ma[1]) < strlen($mb[1])) {
                    return - 1;
                }
                if ($ma[1] < $mb[1]) {
                    return - 1;
                } elseif ($ma[1] > $mb[1]) {
                    return 1;
                }
            } elseif ($ma[1] !== '') {
                return - 1;
            } elseif ($mb[1] !== '') {
                return 1;
            }
        } else {
            // json report: sort by position
            if (($cmp = strnatcmp($a['p'], $b['p'])) !== 0) {
                return $cmp;
            }
        }

        // same position: sort by level
        if ($a['l'] < $b['l']) {
            return - 1;
        } elseif ($a['l'] > $b['l']) {
            return 1;
        }

        // same level: sort by data
        if ($a['d'] < $b['d']) {
            return - 1;
        } elseif ($a['d'] > $b['d']) {
            return 1;
        }

        // same data: sort by message
        if ($a['m'] < $b['m']) {
            return - 1;
        } elseif ($a['m'] > $b['m']) {
            return 1;
        }

        return 0;
    }

    protected function useSummaryMessage(array $message, int $summaryThreshold): bool
    {
        if ($summaryThreshold === 0) {
            return false;
        }
        $summary = $message['s'];
        if ($message['h'] !== null && $message['h'] !== '') {
            $summary .= ', ' . $message['h'];
        }
        return ($this->summaryMessages[$message['l']][$summary] >= $summaryThreshold);
    }

    protected function getLevelPositionName(array $message): string
    {
        $positionName = $this->getPositionName($message);
        if ($positionName !== '') {
            $positionName = (substr($positionName, 0, 7) === 'element' ? ' at ' : ' in ') . $positionName;
        }
        return $this->getLevelName($message['l']) . $positionName;
    }

    protected function getPositionName(array $message): string
    {
        $positionName = '';
        if ($message['p'] !== null) {
            if (preg_match('/^[A-Z]+[0-9]+$/', $message['p'])) {
                $positionName = 'cell';
            } elseif (preg_match('/^[0-9]+$/', $message['p'])) {
                $positionName = 'row';
            } elseif (preg_match('/^[A-Z]+$/', $message['p'])) {
                $positionName = 'column';
            } elseif ($message['p'] !== '') {
                $positionName = 'element';
            }
            if ($positionName !== '') {
                $positionName .= ' ';
            }
            $positionName .= $message['p'];
        }
        return $positionName;
    }

    protected function getPositionForName(?string $name): string
    {
        if ($name === null) {
            return '';
        }

        $matches = [];
        if (preg_match('/^(?:cell|row|column|element) (.+)$/', $name, $matches)) {
            return $matches[1];
        } else {
            return $name;
        }
    }

    protected function getMessageAsText(array $message, int $summaryThreshold): string
    {
        if ($this->useSummaryMessage($message, $summaryThreshold)) {
            return '';
        }

        $text = $this->getLevelPositionName($message) . ': ';
        if ($message['d'] !== null && $message['d'] !== '') {
            $text .= $message['d'];
        }
        $text .= "\n  " . $message['m'];
        if ($message['h'] !== null && $message['h'] !== '') {
            $text .= ', ' . $message['h'];
        }
        $text .= ".\n";

        return $text;
    }

    protected function getSummaryMessageAsText(int $level, string $message, int $numMessages): string
    {
        return ($numMessages . ' ' . $this->getLevelName($level) . ($numMessages !== 1 ? 's' : '') . ': ' . $message .
            ".\n");
    }
}
