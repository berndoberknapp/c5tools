<?php

/**
 * TabularReportItemList is the main class for parsing and validating tabular COUNTER R5 Report Item lists
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

use PhpOffice\PhpSpreadsheet\Worksheet\Row;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TabularReportItemList extends ReportItemList
{
    protected array $reportColumns;

    protected array $invalidColumns;

    protected array $missingColumns;

    protected int $bodyRowsParsed = 0;

    // TODO: move to ReportHeader?
    public function getReportColumns(): array
    {
        return $this->reportColumns;
    }

    // TODO: move to ReportHeader?
    public function getInvalidColumns(): array
    {
        return $this->invalidColumns;
    }

    public function getBodyRowsParsed(): int
    {
        if (! $this->isParsed()) {
            $this->parseDocument();
        }

        return $this->bodyRowsParsed;
    }

    protected function getActiveSheet(): Worksheet
    {
        return $this->document->getDocument()->getActiveSheet();
    }

    protected function parseDocument(): void
    {
        $this->setParsing();

        $sheet = $this->getActiveSheet();
        $headingsRowNumber = $this->config->getNumberOfHeaderRows() + 2;
        $this->parseColumnHeadings($sheet->getRowIterator($headingsRowNumber, $headingsRowNumber)
            ->current());

        if (empty($this->reportColumns)) {
            $message = 'Due to errors in the column headings the report body was not checked';
            $position = $headingsRowNumber + 1;
            $data = 'Report Body';
            $this->addNotice($message, $message, $position, $data);
            $this->setParsed();
            return;
        }
        if ($sheet->getHighestRow() < $headingsRowNumber + 1) {
            $this->setParsed();
            return;
        }

        $hasUnusableReportItems = false;
        $index = 0;
        foreach ($sheet->getRowIterator($headingsRowNumber + 1) as $position => $row) {
            $this->setIndex($index);
            $rowValues = $this->getRowValues($row);
            $reportItem = new TabularReportItem($this, $position, $index, $rowValues);
            $index++;
            $this->bodyRowsParsed++;
            if ($reportItem->isUsable()) {
                $fullHash = $reportItem->getFullHash();
                if (isset($this->items[$fullHash])) {
                    $this->items[$fullHash]->merge($reportItem);
                } else {
                    $this->items[$fullHash] = $reportItem;
                }
                if ($reportItem->isFixed()) {
                    $this->setFixed('.', $rowValues);
                }
            } else {
                $this->setInvalid('.', $rowValues);
            }
            $this->addMetricTypesPresent($reportItem->getMetricTypesPresent());

            if (! $reportItem->isUsable()) {
                $hasUnusableReportItems = true;
                $this->setUnusable();
            }
        }
        if ($hasUnusableReportItems) {
            $message = 'Due to errors in the report body some checks were skipped';
            $position = $sheet->getHighestRow() + 1;
            $data = 'Report Body';
            $this->addNotice($message, $message, $position, $data);
        }

        $this->setParsed();

        // TODO: check filters - Database, Item_ID, Item_Contributor
        if (! empty($this->items)) {
            $this->checkItemMetrics();
            $this->checkTitleMetrics();
        }

        $this->cleanupAndStorePerformance();

        $this->document = null;
    }

    protected function parseColumnHeadings(Row $headingsRow): void
    {
        $headingsRowNumber = $headingsRow->getRowIndex();
        $reportElements = $this->reportHeader->getReportElements();
        $expectedColumnHeadings = array_keys($reportElements);
        $reportColumns = [];
        $this->reportColumns = [];
        $this->invalidColumns = [];
        $this->missingColumns = [];

        // check if the columns are valid and spelled correctly
        foreach ($this->getRowValues($headingsRow) as $columnNumber => $columnHeading) {
            $position = "{$columnNumber}{$headingsRowNumber}";
            $data = $this->formatData('Column heading', $columnHeading);
            if (! isset($reportElements[$columnHeading])) {
                if (($correctColumnHeading = $this->inArrayFuzzy($columnHeading, $expectedColumnHeadings)) !== null) {
                    $summary = "Spelling of column heading is wrong";
                    $message = "Spelling of column heading '{$columnHeading}' is wrong";
                    $hint = "must be spelled '{$correctColumnHeading}'";
                    $this->addError($summary, $message, $position, $data, $hint);
                    $columnHeading = $correctColumnHeading;
                } elseif (
                    ($correctColumnHeading = $this->inArrayFuzzyMonthly($columnHeading, $expectedColumnHeadings)) !==
                    null
                ) {
                    if ($this->isExcelDate($columnHeading)) {
                        $message = "Could not check the date format used for the column heading for monthly counts";
                        $hint = "using date formats is not recommended because the result might depend " .
                            "on the operating system and spreadsheet application settings";
                        $this->addWarning($message, $message, $position, $data, $hint);
                    } else {
                        $message = "Format is wrong for column heading";
                        $hint = 'the column headings for the monthly counts must be in Mmm-yyyy format';
                        $this->addError($message, $message, $position, $data, $hint);
                    }
                    $columnHeading = $correctColumnHeading;
                } else {
                    if (
                        $this->config->isAttributesToShow(
                            $this->reportHeader->getReportId(),
                            $this->getFormat(),
                            $columnHeading
                        )
                    ) {
                        $error = 'is not included in Attributes_To_Show and therefore invalid for this report, ignoring column';
                        $addLevel = 'addError';
                    } else {
                        $error = 'is invalid for this report, ignoring column';
                        $addLevel = 'addCriticalError';
                    }
                    $summary = "Column heading {$error}";
                    $message = "Column heading '{$columnHeading}' {$error} {$columnNumber}";
                    $this->$addLevel($summary, $message, $position, $data);
                    $this->invalidColumns[$columnNumber] = $columnHeading;
                    continue;
                }
            }

            if (isset($reportColumns[$columnHeading])) {
                $summary = 'Duplicate column heading, ignoring column';
                $message = "Duplicate column heading {$columnHeading}, ignoring column {$columnNumber}";
                $this->addCriticalError($summary, $message, $position, $data);
                $this->invalidColumns[$columnNumber] = $columnHeading;
                continue;
            }

            $method = (isset($reportElements[$columnHeading]['check']) ? 'check' : 'parse');
            $reportColumns[$columnHeading] = [
                'columnNumber' => $columnNumber,
                $method => $reportElements[$columnHeading][$method]
            ];
        }

        // check for missing columns or wrong column order
        $foundColumnHeadings = [];
        $missingColumnHeadings = [];
        foreach ($expectedColumnHeadings as $columnHeading) {
            if (isset($reportColumns[$columnHeading])) {
                $foundColumnHeadings[] = $columnHeading;
            } else {
                $missingColumnHeadings[] = $columnHeading;
            }
        }
        if (! empty($missingColumnHeadings)) {
            $summary = 'Columns required for this report are missing';
            $message = "{$summary}: '" . implode("', '", $missingColumnHeadings) . "'";
            $this->addCriticalError($summary, $message, $headingsRowNumber, '');
            $this->setUnusable();
            $this->missingColumns = $missingColumnHeadings;
        }
        $columnMapHeadings = array_keys($reportColumns);
        if ($foundColumnHeadings !== $columnMapHeadings) {
            foreach ($columnMapHeadings as $index => $columnMapHeading) {
                if ($columnMapHeading === $foundColumnHeadings[$index]) {
                    unset($columnMapHeadings[$index]);
                    unset($foundColumnHeadings[$index]);
                }
            }
            $summary = 'Order of columns is wrong';
            $message = "Order of columns '" . implode("', '", $columnMapHeadings) . "' is wrong, expecting order '" .
                implode("', '", $foundColumnHeadings) . "'";
            $this->addCriticalError($summary, $message, $headingsRowNumber, '');
        }
        if (! empty($this->missingColumns)) {
            return;
        }

        // create final colum map with all correct(ed) columns
        $columnForColumnHeading = [];
        foreach ($reportColumns as $columnHeading => $columnConfig) {
            $method = (isset($columnConfig['check']) ? 'check' : 'parse');
            $this->reportColumns[$columnConfig['columnNumber']] = [
                'columnHeading' => $columnHeading,
                $method => $columnConfig[$method]
            ];
            $columnForColumnHeading[$columnHeading] = $columnConfig['columnNumber'];
        }
        $this->reportHeader->setColumnForColumnHeading($columnForColumnHeading);
    }

    protected function inArrayFuzzyMonthly(string $columnHeading, array $expectedColumnHeadings): ?string
    {
        $columnHeading = trim($columnHeading);
        $format = null;
        // the current day is automatically added which might result in a wrong month, so we have to add a valid day
        if (preg_match('/^[a-zA-Z]{3}-[0-9]{2}$/', $columnHeading)) {
            $format = 'd-M-y'; // [Mm]mm-yy
            $columnHeading = '01-' . $columnHeading;
        } elseif (preg_match('/^[0-9]{4}-[0-9]{2}$/', $columnHeading)) {
            $format = 'Y-m-d'; // yyyy-mm
            $columnHeading .= '-01';
        } elseif (preg_match('/^[0-9]{4}-[0-9]$/', $columnHeading)) {
            $format = 'Y-n-d'; // yyyy-m
            $columnHeading .= '-01';
        } elseif ($this->isExcelDate($columnHeading)) {
            // Excel
            $columnHeading = ($columnHeading - 25569) * 86400;
            $format = 'U';
        }
        if ($format !== null) {
            $datetime = \DateTime::createFromFormat("!{$format}", $columnHeading);
            if ($datetime !== false) {
                $correctColumnHeading = $datetime->format('M-Y');
                if (in_array($correctColumnHeading, $expectedColumnHeadings)) {
                    return $correctColumnHeading;
                }
            }
        }
        return null;
    }

    protected function isExcelDate(string $string): bool
    {
        if ($this->document->isCsvTsv()) {
            return false;
        }
        return (preg_match('/^[0-9]+$/', $string) && $string > 25569);
    }
}
