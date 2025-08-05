<?php

/**
 * TabularItem51 is the main class for parsing and validating tabular COUNTER R5.1 Report Item list entries
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

use ubfr\c5tools\traits\Parsers;
use ubfr\c5tools\traits\TabularParsers;

class TabularItem51 extends JsonReportItem51
{
    use Parsers, TabularParsers {
        TabularParsers::parseIdentifierList insteadof Parsers;
        TabularParsers::parseIdentifierList51 insteadof Parsers;
    }

    protected array $counts = [];

    protected ?int $reportingPeriodTotal = null;

    protected ?TabularParentItem51 $parentItem = null;

    protected ?TabularReportItem51 $reportItem = null;

    public function getParentItem(): ?TabularParentItem51
    {
        return $this->parentItem;
    }

    public function getReportItem(): ?TabularReportItem51
    {
        return $this->reportItem;
    }

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

        $this->createComponents();
        $this->createReportItem();
        $this->createParentItem();

        $this->document = null;
    }

    protected function parseAuthorList51(string $position, string $property, $value): void
    {
        $authors = [];
        foreach ($this->checkedSemicolonSpaceSeparatedValues($position, $property, $value) as $authorString) {
            $author = new \stdClass();
            $matches = [];
            if (preg_match('/^(.+) \(([^:]+):([^)]+)\)$/', $authorString, $matches)) {
                $author->Name = $matches[1];
                $identifier = $matches[2];
                $author->$identifier = $matches[3];
            } else {
                $author->Name = $authorString;
            }
            $authors[] = $author;
        }

        if (! empty($authors)) {
            parent::parseAuthorList51($position, $property, $authors);
        }
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

        $this->counts[$this->getDateForMonthlyColumnHeading($property)] = $value;
    }

    protected function checkReportingPeriodTotal(): void
    {
        if ($this->reportingPeriodTotal === null || $this->reportHeader->excludesMonthlyDetails()) {
            return;
        }

        $sumOfCounts = array_sum($this->counts);
        if ($this->reportingPeriodTotal !== $sumOfCounts) {
            $property = 'Reporting_Period_Total';
            $summary = "{$property} differs from sum of monthly counts";
            $message = "{$summary} ({$this->reportingPeriodTotal} vs. {$sumOfCounts})";
            $position = $this->reportHeader->getColumnForColumnHeading($property) . $this->position;
            $data = $this->formatData($property, $this->reportingPeriodTotal);
            $this->addCriticalError($summary, $message, $position, $data);
            $this->setUnusable();
        }
    }

    protected function getPerformance(): array
    {
        if (! isset($this->data['Metric_Type'])) {
            return [];
        }

        if ($this->reportHeader->excludesMonthlyDetails()) {
            $this->counts[substr($this->reportHeader->getBeginDate(), 0, 7)] = $this->reportingPeriodTotal;
        }

        $performance = [
            $this->data['Metric_Type'] => $this->counts
        ];

        unset($this->data['Metric_Type']);
        unset($this->counts);

        return $performance;
    }

    protected function getAttributePerformanceList(
        array $attributes,
        string $context
    ): ?TabularAttributePerformanceList51 {
        $attributes['Performance'] = $this->getPerformance();
        $attributePerformanceList = new TabularAttributePerformanceList51(
            $this,
            $this->position,
            $attributes,
            $context,
            $this->metricTypesPresent
        );
        if ($attributePerformanceList->isUsable()) {
            return $attributePerformanceList;
        } else {
            $this->setUnusable();
            return null;
        }
    }

    protected function createComponents(): void
    {
        if (! $this->reportHeader->includesComponentDetails()) {
            return;
        }

        $attributes = [];
        $metadata = [];
        foreach ($this->data as $property => $value) {
            if (substr($property, 0, 10) === 'Component_') {
                if ($property === 'Component_Data_Type') {
                    $attributes['Data_Type'] = $value;
                } elseif ($property === 'Component_Title') {
                    $metadata['Item'] = $value;
                } else {
                    $metadata[substr($property, 10)] = $value;
                }
                unset($this->data[$property]);
            }
        }
        if (empty($attributes) && empty($metadata)) {
            return;
        }

        $attributePerformanceList = $this->getAttributePerformanceList($attributes, 'component');
        if ($attributePerformanceList === null) {
            $this->setUnusable();
            return;
        }
        $metadata['Attribute_Performance'] = $attributePerformanceList;

        $componentItemList = new TabularComponentItemList51($this, $this->position, $metadata);
        if ($componentItemList->isUsable()) {
            $this->setData('Components', $componentItemList);
        } else {
            $this->setUnusable();
        }
    }

    protected function createReportItem(): void
    {
        $attributes = [];
        $metadata = [];
        foreach ($this->reportHeader->getReportElements() as $property => $propertyConfig) {
            if (substr($property, 0, 7) === 'Parent_' || ! isset($this->data[$property])) {
                continue;
            }
            if ($propertyConfig['attribute']) {
                $attributes[$property] = $this->data[$property];
                unset($this->data[$property]);
            } elseif ($propertyConfig['metadata']) {
                $metadata[$property] = $this->data[$property];
                unset($this->data[$property]);
            }
        }

        if (isset($this->data['Components'])) {
            $attributes['Components'] = $this->data['Components'];
            unset($this->data['Components']);
        }

        $attributePerformanceList = $this->getAttributePerformanceList($attributes, 'item');
        if ($attributePerformanceList === null) {
            $this->setUnusable();
            return;
        }
        $metadata['Attribute_Performance'] = $attributePerformanceList;

        $reportItem = new TabularReportItem51($this->reportItems, $this->position, 0, $metadata);
        if ($reportItem->isUsable()) {
            $this->reportItem = $reportItem;
        } else {
            $this->setUnusable();
        }
    }

    protected function createParentItem(): void
    {
        if ($this->reportItem === null) {
            // don't try to create the parent item if creating the report item failed
            return;
        }

        $metadata = [];
        foreach ($this->data as $property => $value) {
            if (substr($property, 0, 7) !== 'Parent_') {
                throw new \LogicException("property {$property} invalid");
            }
            $metadata[substr($property, 7)] = $value;
            unset($this->data[$property]);
        }

        $parentReportItemList = new TabularParentReportItemList51(
            $this->reportItems,
            $this->position,
            $this->reportItem
        );
        if (! $parentReportItemList->isUsable()) {
            $this->setUnusable();
            return;
        }
        $metadata['Items'] = $parentReportItemList;

        $parentItem = new TabularParentItem51($this->reportItems, $this->position, 0, $metadata);
        if ($parentItem->isUsable()) {
            $this->parentItem = $parentItem;
        } else {
            $this->setUnusable();
        }
    }
}
