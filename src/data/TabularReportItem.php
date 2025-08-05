<?php

/**
 * TabularReportItem handles tabular COUNTER R5 Report Item list entries
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

use ubfr\c5tools\traits\Parsers;
use ubfr\c5tools\traits\TabularParsers;

class TabularReportItem extends ReportItem
{
    use Parsers, TabularParsers {
        TabularParsers::parseIdentifierList insteadof Parsers;
        TabularParsers::parseIdentifierList51 insteadof Parsers;
    }

    protected $counts = [];

    protected $reportingPeriodTotal = null;

    protected function parseDocument(): void
    {
        $this->setParsing();

        // parse valid columns
        $requiredElements = $this->reportHeader->getRequiredElements();
        $reportColumns = $this->reportItems->getReportColumns();
        foreach ($reportColumns as $columnNumber => $columnConfig) {
            $columnHeading = $columnConfig['columnHeading'];
            $position = "{$columnNumber}{$this->position}";
            $value = $this->document[$columnNumber] ?? '';
            if ($value === '' && ! in_array($columnHeading, $requiredElements)) {
                continue;
            }
            if (isset($columnConfig['check'])) {
                $checkMethod = $columnConfig['check'];
                $value = $this->$checkMethod($position, $columnHeading, $value);
                if ($value !== null) {
                    $this->setData($columnHeading, $value);
                    if ($columnHeading === 'Metric_Type') {
                        $this->metricTypesPresent[] = $value;
                    }
                }
            } else {
                $parseMethod = $columnConfig['parse'];
                $this->$parseMethod($position, $columnHeading, $value);
            }
        }

        // check if there are additional column that should not be present
        $invalidColumns = $this->reportItems->getInvalidColumns();
        foreach ($this->document as $columnNumber => $columnValue) {
            if (
                ! isset($reportColumns[$columnNumber]) && ! isset($invalidColumns[$columnNumber]) &&
                trim($columnValue) !== ''
            ) {
                $summary = 'Cell must be empty';
                $position = "{$columnNumber}{$this->position}";
                $message = "Cell {$position} must be empty, found '{$columnValue}'";
                $data = $this->formatData('value', $columnValue);
                $this->addError($summary, $message, $position, $data);
            }
        }

        $this->setParsed();

        $this->checkReportingPeriodTotal();

        $this->createPerformance();
        $this->createItemId();
        $this->createItemParent();
        $this->createItemComponent();

        // checks which require correlation between different elements of the Report_Items
        $this->checkItemNameAndIdentifiers();
        $this->checkPublisher();
        $this->checkMetricTypes();
        $this->checkDataTypeSearchesMetrics();
        $this->checkDataTypeUniqueTitleMetrics();
        $this->checkSectionTypeDataType();
        $this->checkSectionTypeMetricType();
        $this->checkFormatMetricType();

        $this->checkRequiredElements();

        $this->document = null;
    }

    protected function parseAuthors(string $position, string $property, $value): void
    {
        $itemContributors = [];
        foreach ($this->checkedSemicolonSpaceSeparatedValues($position, $property, $value) as $author) {
            $itemContributor = [
                'Type' => 'Author'
            ];
            $matches = [];
            if (preg_match('/^(.+) \(([^:]+:[^)]+)\)$/', $author, $matches)) {
                $itemContributor['Name'] = $matches[1];
                $itemContributor['Identifier'] = $matches[2];
            } else {
                $itemContributor['Name'] = $author;
            }
            $itemContributors[] = (object) $itemContributor;
        }

        if (! empty($itemContributors)) {
            parent::parseItemContributors($position, 'Item_Contributors', $itemContributors);
        }
    }

    protected function parsePublicationDate(string $position, string $property, $value): void
    {
        $itemDates = [
            (object) [
                'Type' => 'Publication_Date',
                'Value' => $value
            ]
        ];

        parent::parseItemDates($position, 'Item_Dates', $itemDates);
    }

    protected function parseArticleVersion(string $position, string $property, $value): void
    {
        $itemAttributes = [
            (object) [
                'Type' => 'Article_Version',
                'Value' => $value
            ]
        ];

        parent::parseItemAttributes($position, 'Item_Attributes', $itemAttributes);
    }

    protected function parseReportingPeriodTotal(string $position, string $property, $value): void
    {
        if (trim($value) !== $value) {
            $summary = "{$property} value includes whitespace";
            $message = "{$property} value '{$value}' includes whitespace";
            $data = $this->formatData($property, $value);
            $this->addError($summary, $message, $position, $data);
            $value = trim($value);
        }
        if ($value === '') {
            $message = "{$property} value must not be empty";
            $data = $this->formatData($property, $value);
            $this->addCriticalError($message, $message, $position, $data);
            // might be just a missing zero, therefore no setUnsuable() here
            return;
        }
        if (! is_numeric($value)) {
            $message = "{$property} value is invalid";
            $data = $this->formatData($property, $value);
            $hint = "value must be an integer";
            $this->addCriticalError($message, $message, $position, $data, $hint);
            $this->setUnusable();
            return;
        }
        $value = (int) $value;
        if ($value < 0) {
            $message = "Negative {$property} value is invalid";
            $data = $this->formatData($property, $value);
            $this->addCriticalError($message, $message, $position, $data);
            $this->setUnusable();
            return;
        }
        if ($value === 0) {
            $message = "{$property} value '0' is invalid";
            $data = $this->formatData($property, $value);
            $hint = "rows with zero {$property} must be omitted";
            $this->addCriticalError($message, $message, $position, $data, $hint);
        }

        $this->reportingPeriodTotal = $value;
    }

    protected function parseMonthlyData(string $position, string $property, $value): void
    {
        if (trim($value) !== $value) {
            $summary = "{$property} value includes whitespace";
            $message = "{$property} value '{$value}' includes whitespace";
            $data = $this->formatData("{$property} value", $value);
            $this->addError($summary, $message, $position, $data);
            $value = trim($value);
        }
        if ($value === '') {
            $message = "{$property} value must not be empty";
            $data = $this->formatData("{$property} value", $value);
            $hint = "set the cell value to 0 if there was no usage in {$property}";
            $this->addError($message, $message, $position, $data, $hint);
            // assume this was just a missing zero
            $value = 0;
        }
        if (! is_numeric($value)) {
            $message = "{$property} value is invalid";
            $data = $this->formatData("{$property} value", $value);
            $hint = "value must be an integer";
            $this->addCriticalError($message, $message, $position, $data, $hint);
            $this->setUnusable();
            return;
        }
        $value = (int) $value;
        if ($value < 0) {
            $message = "Negative {$property} value is invalid";
            $data = $this->formatData("{$property} value", $value);
            $this->addCriticalError($message, $message, $position, $data);
            $this->setUnusable();
            return;
        }

        $this->counts[$this->getDateForMonthlyColumnHeading($property)] = [
            'c' => $value,
            'p' => $position
        ];
    }

    protected function checkReportingPeriodTotal(): void
    {
        if ($this->reportingPeriodTotal === null || $this->reportHeader->excludesMonthlyDetails()) {
            return;
        }

        $sumOfCounts = 0;
        foreach ($this->counts as $countPosition) {
            $sumOfCounts += $countPosition['c'];
        }

        $property = 'Reporting_Period_Total';
        if ($this->reportingPeriodTotal !== $sumOfCounts) {
            $summary = "{$property} differs from sum of monthly counts";
            $message = "{$summary} ({$this->reportingPeriodTotal} vs. {$sumOfCounts})";
            $position = $this->reportHeader->getColumnForColumnHeading($property) . $this->position;
            $data = $this->formatData($property, $this->reportingPeriodTotal);
            $this->addCriticalError($summary, $message, $position, $data);
            $this->setUnusable();
        }
    }

    protected function createPerformance(): void
    {
        if (! isset($this->data['Metric_Type'])) {
            $this->setUnusable();
            return;
        }

        if ($this->reportHeader->excludesMonthlyDetails()) {
            $position = $this->reportHeader->getColumnForColumnHeading('Reporting_Period_Total') . $this->position;
            $this->counts[substr($this->reportHeader->getBeginDate(), 0, 7)] = [
                'c' => $this->reportingPeriodTotal,
                'p' => $position
            ];
        }
        $this->performance[$this->data['Metric_Type']] = $this->counts;

        unset($this->data['Metric_Type']);
        unset($this->counts);
    }

    protected function createItemParent(): void
    {
        $reportId = $this->reportHeader->getReportId();
        if ($reportId !== 'IR_A1' && ! $this->reportHeader->includesParentDetails()) {
            return;
        }

        $parentData = [];
        foreach ($this->data as $property => $value) {
            if (substr($property, 0, 7) === 'Parent_') {
                $parentData[$property === 'Parent_Title' ? 'Item_Name' : substr($property, 7)] = $value;
                unset($this->data[$property]);
            }
        }
        if ($reportId !== 'IR_A1' && empty($parentData)) {
            return;
        }

        $itemParent = new TabularItemParent($this, $this->position, $parentData);
        if ($itemParent->isUsable()) {
            $this->setData('Item_Parent', $this->reportItems->getStoredParent($itemParent));
            if ($itemParent->isFixed()) {
                $this->setFixed('Item_Parent', $parentData);
            }
        } else {
            $this->setInvalid('Item_Parent', $parentData);
            if ($reportId === 'IR_A1') {
                $this->setUnusable();
            }
        }
    }

    protected function createItemComponent(): void
    {
        if (! $this->reportHeader->includesComponentDetails()) {
            return;
        }

        $componentData = [];
        foreach ($this->data as $property => $value) {
            if (substr($property, 0, 10) === 'Component_') {
                $componentData[$property === 'Component_Title' ? 'Item_Name' : substr($property, 10)] = $value;
                unset($this->data[$property]);
            }
        }
        if (empty($componentData)) {
            return;
        }

        $componentData['Performance'] = $this->performance;
        $this->performance = [];

        $itemComponentList = new TabularItemComponentList($this, $this->position, $componentData);
        if ($itemComponentList->isUsable()) {
            $this->setData('Item_Component', $itemComponentList);
            if ($itemComponentList->isFixed()) {
                $this->setFixed('Item_Component', $componentData);
            }
        } else {
            $this->setInvalid('Item_Component', $componentData);
        }
    }

    protected function mergeMetricTypeDateError(
        ReportItem $reportItem,
        string $metricType,
        string $date,
        array $count
    ): void {
        $firstCount = $this->performance[$metricType][$date];
        $firstOccurrence = "cell {$firstCount['p']}";
        $message = "Multiple rows for the same Item, Report Attributes, Metric_Type and month";
        $data = "Month '{$date}', Metric_Type '{$metricType}', Count {$count['c']} (first occurrence in {$firstOccurrence}";
        if ($count['c'] !== $this->performance[$metricType][$date]['c']) {
            $message .= ' with different Counts';
            $data .= " with Count {$firstCount['c']}";
        } else {
            $message .= ' with identical Counts';
        }
        $message .= ", ignoring all but the first row";
        $data .= ')';
        $position = $count['p'];
        $this->addCriticalError($message, $message, $position, $data);
        $reportItem->setUnusable();
    }
}
