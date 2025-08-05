<?php

/**
 * TabularReportHeader is the main class for parsing and validating tabular COUNTER Report Headers
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

use ubfr\c5tools\interfaces\CheckedDocument;
use ubfr\c5tools\traits\Parsers;
use ubfr\c5tools\traits\TabularParsers;

class TabularReportHeader extends ReportHeader
{
    use Parsers, TabularParsers {
        TabularParsers::parseIdentifierList insteadof Parsers;
        TabularParsers::parseIdentifierList51 insteadof Parsers;
    }

    protected array $filters = [];

    protected ?array $columnForColumnHeading = null;

    public function setColumnForColumnHeading(array $columnForColumnHeading): void
    {
        if ($this->columnForColumnHeading !== null) {
            throw new \LogicException("columnForColumnHeading already set");
        }

        $this->columnForColumnHeading = $columnForColumnHeading;
    }

    public function getColumnForColumnHeading(string $columnHeading): string
    {
        if ($this->columnForColumnHeading === null) {
            throw new \LogicException("columnForColumnHeading not set");
        }

        if (! isset($this->columnForColumnHeading[$columnHeading])) {
            throw new \InvalidArgumentException("column heading {$columnHeading} invalid");
        }

        return $this->columnForColumnHeading[$columnHeading];
    }

    protected function parseDocument(): void
    {
        $this->setParsing();

        foreach ($this->config->getReportHeaders($this->getFormat()) as $headerName => $headerConfig) {
            $rowNumber = $headerConfig['row'];

            // check header label in column A
            $position = 'A' . $rowNumber;
            if (! isset($this->document[$rowNumber]['A'])) {
                $message = "Header label '{$headerName}' is missing";
                $this->addCriticalError($message, $message, $position, null);
                continue;
            } else {
                $headerLabel = $this->document[$rowNumber]['A'];
                if ($headerLabel !== $headerName) {
                    if ($this->fuzzy($headerLabel) === $this->fuzzy($headerName)) {
                        $summary = 'Spelling of header label is wrong';
                        $message = "Spelling of header label '{$headerLabel}' is wrong";
                        $hint = "must be spelled '{$headerName}'";
                        $this->addError($summary, $message, $position, $headerLabel, $hint);
                        $this->setSpelling($headerLabel, $headerName);
                    } else {
                        $summary = 'Header label is invalid';
                        $message = "Header label '{$headerLabel}' is invalid";
                        $hint = "must be '{$headerName}'";
                        $this->addCriticalError($summary, $message, $position, $headerLabel, $hint);
                        continue;
                    }
                }
            }

            // check header values in column B
            $value = ($this->document[$rowNumber]['B'] ?? '');
            // TODO: type check, currently not possible because numbers formatted as text are treated as numbers
            $position = "B{$rowNumber}";
            if (isset($headerConfig['check'])) {
                $checkMethod = $headerConfig['check'];
                $value = $this->$checkMethod($position, $headerName, $value);
                if ($value !== null) {
                    $this->setData($headerName, $value);
                }
            } else {
                $parseMethod = $headerConfig['parse'];
                $this->$parseMethod($position, $headerName, $value);
            }

            // check additional columns
            if (count($this->document[$rowNumber]) > 2) {
                foreach (array_slice($this->document[$rowNumber], 2, null, true) as $columnNumber => $columnValue) {
                    if (trim($columnValue) !== '') {
                        $summary = 'Cell must be empty';
                        $message = $summary . ", found '{$columnValue}'";
                        $position = $columnNumber . $rowNumber;
                        $this->addError($summary, $message, $position, $columnValue);
                    }
                }
            }
        }

        // check row 13 (R5) or row 14 (R5.1)
        $emptyRowNumber = $this->config->getNumberOfHeaderRows() + 1;
        foreach ($this->document[$emptyRowNumber] as $columnNumber => $columnValue) {
            if (trim($columnValue) !== '') {
                $position = "{$columnNumber}{$emptyRowNumber}";
                $summary = 'Cell must be empty';
                $message = "Cell {$position} must be empty, found '{$columnValue}'";
                $release = $this->config->getRelease();
                $hint = "row {$emptyRowNumber} must be empty in tabular R{$release} reports";
                $this->addCriticalError($summary, $message, $position, $columnValue, $hint);
            }
        }

        $this->setParsed();

        // internal format is JSON, therefore the required elements have to be checked based on that format
        $requiredProperties = [];
        foreach ($this->config->getReportHeaders(CheckedDocument::FORMAT_JSON) as $property => $headerConfig) {
            if ($headerConfig['required'] && $property !== 'Customer_ID') {
                $requiredProperties[] = $property;
            }
        }
        $this->checkRequiredReportElements($requiredProperties);
        $this->checkOptionalReportElements();
        $this->checkGlobalReport();
        $this->checkException3040();
        $this->checkException3063();

        $this->checkResult->setReportHeader($this);
    }

    protected function parseExceptionList(string $position, string $property, $value): void
    {
        $separatedValues = $this->checkedSemicolonSpaceSeparatedValues($position, $property, $value);
        if (empty($separatedValues)) {
            if (trim($value) !== '') {
                $this->setInvalid($property, $value);
            }
            return;
        }

        $exceptions = $this->checkedExceptionValues($position, $property, $separatedValues);
        if (empty($exceptions)) {
            $this->setInvalid($property, $value);
            return;
        }

        $exceptionList = new ExceptionList($this, $position, $exceptions);
        if ($exceptionList->isUsable()) {
            $this->setData($property, $exceptionList);
            if ((string) $exceptionList !== $value) {
                $this->setFixed($property, $value);
            }
        } else {
            $this->setInvalid($property, $value);
        }
    }

    protected function parseMetricTypes(string $position, string $property, string $value): void
    {
        if ($this->reportId === null || ! in_array($this->reportId, $this->config->getReportIds())) {
            return;
        }

        $originalValue = $value;

        if (strpos($value, '|') !== false) {
            $message = "{$property} must be separated by semicolon-space, not a pipe character";
            $data = $this->formatData($property, $value);
            $this->addError($message, $message, $position, $data);
            $value = implode('; ', preg_split('/\s*\|\s*/', $value));
            $this->setFixed($property, $originalValue);
        }

        $separatedValues = $this->checkedSemicolonSpaceSeparatedValues($position, $property, $value);
        if (empty($separatedValues)) {
            if (trim($originalValue) !== '') {
                $this->setInvalid($property, $originalValue);
            }
            return;
        }

        $this->filters['Metric_Type'] = implode('|', $separatedValues);

        // value is parsed in parseReportFilters
    }

    protected function parseReportingPeriod(string $position, string $property, string $value): void
    {
        static $permittedNames = [
            'Begin_Date',
            'End_Date'
        ];

        if ($this->reportId === null || ! in_array($this->reportId, $this->config->getReportIds())) {
            return;
        }

        if (trim($value) === '') {
            $message = "{$property} value must not be empty";
            $data = $this->formatData($property, $value);
            $this->addCriticalError($message, $message, $position, $data);
            $this->setInvalid($property, $value);
            return;
        }

        $oldFormat = '/^\s*([0-9]{4}-[0-9]{2}(?:-[0-9]{2})?)\s+to\s+([0-9]{4}-[0-9]{2}(?:-[0-9]{2})?)\s*$/';
        $matches = [];
        if (preg_match($oldFormat, $value, $matches)) {
            $message = 'Format is wrong for Reporting_Period';
            $data = $this->formatData($property, $value);
            $hint = "format must be 'Begin_Date=yyyy-mm-dd; End_Date=yyyy-mm-dd'";
            $this->addError($message, $message, $position, $data, $hint);
            $this->setFixed($property, $value);
            $value = 'Begin_Date=' . $matches[1] . '; End_Date=' . $matches[2];
        }

        foreach ($this->checkedNameValuePairs($position, $property, $value, $permittedNames) as $name => $value) {
            $this->filters[$name] = $value;
        }

        // values are parsed in parseReportFilters
    }

    protected function parseReportAttributes(string $position, string $property, $value): void
    {
        if ($this->reportId === null || ! in_array($this->reportId, $this->config->getReportIds())) {
            return;
        }

        if ($value === '') {
            return;
        }

        if (! $this->config->isFullReport($this->reportId)) {
            $message = 'Report_Attributes is not permitted for Standard Views';
            $this->addError($message, $message, $position, $property);
            $this->setFixed($property, $value);
            return;
        }

        $reportAttributes = $this->config->getReportAttributes($this->reportId, $this->getFormat());
        $permittedAttributes = array_keys($reportAttributes);
        $attributes = $this->checkedNameValuePairs($position, $property, $value, $permittedAttributes);
        if (empty($attributes)) {
            return;
        }

        if ($this->config->getRelease() === '5') {
            $jsonAttributes = [];
            foreach ($attributes as $attributeName => $attributeValue) {
                $jsonAttributes[] = (object) [
                    'Name' => $attributeName,
                    'Value' => $attributeValue
                ];
            }
        } else {
            $jsonAttributes = new \stdClass();
            foreach ($attributes as $attributeName => $attributeValue) {
                if ($reportAttributes[$attributeName]['multi'] ?? 0) {
                    $jsonAttributes->$attributeName = explode('|', $attributeValue);
                } else {
                    $jsonAttributes->$attributeName = $attributeValue;
                }
            }
        }

        parent::parseReportAttributes($position, $property, $jsonAttributes);
    }

    protected function parseReportFilters(string $position, string $property, $value): void
    {
        static $specialTabularFilters = [
            'Begin_Date' => 'Reporting_Period',
            'End_Date' => 'Reporting_Period',
            'Metric_Type' => 'Metric_Types'
        ];

        if ($this->reportId === null || ! in_array($this->reportId, $this->config->getReportIds())) {
            return;
        }

        $reportFilters = $this->config->getReportFilters($this->reportId);
        $permittedFilters = array_keys($reportFilters);
        $filters = $this->checkedNameValuePairs($position, $property, $value, $permittedFilters);
        foreach ($filters as $filterName => $filterValue) {
            if (isset($specialTabularFilters[$filterName])) {
                $message = "{$filterName} is not permitted in Report_Filters";
                $data = $this->formatData($filterName, $filterValue);
                $hint = "in tabular reports the {$filterName} filter must be in the {$specialTabularFilters[$filterName]} header";
                if (! isset($this->filters[$filterName])) {
                    $this->filters[$filterName] = $filterValue;
                } else {
                    $hint .= ", ignoring {$filterName} filter";
                }
                $this->checkResult->addCriticalError($message, $message, $position, $data, $hint);
            } else {
                $this->filters[$filterName] = $filterValue;
            }
        }

        if ($this->config->getRelease() === '5') {
            $jsonFilters = [];
            foreach ($this->filters as $filterName => $filterValue) {
                $jsonFilters[] = (object) [
                    'Name' => $filterName,
                    'Value' => $filterValue
                ];
            }
        } else {
            $jsonFilters = new \stdClass();
            foreach ($this->filters as $filterName => $filterValue) {
                if ($reportFilters[$filterName]['multi'] ?? 0) {
                    $jsonFilters->$filterName = explode('|', $filterValue);
                } else {
                    $jsonFilters->$filterName = $filterValue;
                }
            }
        }

        parent::parseReportFilters($position, $property, $jsonFilters);
    }

    public function asCells(): array
    {
        $cells = [];
        foreach ($this->document as $rowNumber => $columns) {
            foreach ($columns as $columnNumber => $value) {
                $cells["{$columnNumber}{$rowNumber}"] = $value;
            }
        }

        return $cells;
    }
}
