<?php

/**
 * JsonReportItem handles JSON COUNTER R5 Report Item list entries
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

class JsonReportItem extends ReportItem
{
    protected function parseDocument(): void
    {
        $this->setParsing();

        $elements = $this->reportHeader->getReportElements();
        $requiredElements = $this->reportHeader->getRequiredElements();
        $optionalElements = $this->reportHeader->getOptionalElements();

        $properties = $this->getObjectProperties(
            $this->position,
            'Report_Item',
            $this->document,
            $requiredElements,
            $optionalElements
        );
        foreach ($properties as $property => $value) {
            $position = "{$this->position}.{$property}";
            if (isset($elements[$property]['check'])) {
                $checkMethod = $elements[$property]['check'];
                $value = $this->$checkMethod($position, $property, $value);
                if ($value !== null) {
                    $this->setData($property, $value);
                }
            } else {
                $parseMethod = $elements[$property]['parse'];
                $this->$parseMethod($position, $property, $value);
            }
        }

        $this->setParsed();

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

        if ($this->hasDuplicatePeriods && $this->isUsable()) {
            $this->setFixed('Performance', $properties['Performance']);
        }

        $this->document = null;
    }

    protected function parseItemParent(string $position, string $property, $value): void
    {
        $originalValue = $value;

        $fixed = false;
        if (is_array($value) && count($value) === 1) {
            // handle the case that Item_Parent is an array (as initially defined in the Swagger schema)
            $message = "{$property} must be an object, found an array";
            $data = $this->formatData($property, json_encode($value));
            $this->addCriticalError($message, $message, $position, $data);
            $value = $value[0];
            $fixed = true;
        }

        if (! $this->isObject($position, $property, $value)) {
            return;
        }

        $itemParent = new JsonItemParent($this, $position, $value);
        if ($itemParent->isUsable()) {
            $this->setData($property, $this->reportItems->getStoredParent($itemParent));
            if ($fixed || $itemParent->isFixed()) {
                $this->setFixed($property, $originalValue);
            }
        } else {
            $this->setInvalid($property, $originalValue);
        }

        $itemParent = null;
    }

    protected function parseItemComponent(string $position, string $property, $value): void
    {
        if (! $this->isNonEmptyArray($position, $property, $value)) {
            return;
        }

        $itemComponentList = new JsonItemComponentList($this, $position, $value);
        if ($itemComponentList->isUsable()) {
            $this->setData($property, $itemComponentList);
            if ($itemComponentList->isFixed()) {
                $this->setFixed($property, $value);
            }
        } else {
            $this->setInvalid($property, $value);
        }
        $this->addMetricTypesPresent($itemComponentList->getMetricTypesPresent());
    }

    protected function mergeMetricTypeDateError(
        ReportItem $reportItem,
        string $metricType,
        string $date,
        array $count
    ): void {
        $firstCount = $this->performance[$metricType][$date];
        $firstOccurrence = "{$this->position}.Performance[{$firstCount['p']}].Instance[{$firstCount['i']}]";
        $message = "Multiple Report_Items for the same Item, Report Attributes, Period and Metric_Type";
        $data = "Begin_Date '{$date}-01', Metric_Type '{$metricType}', Count {$count['c']} (first occurrence at {$firstOccurrence}";
        if ($count['c'] !== $this->performance[$metricType][$date]['c']) {
            $message .= ' with different Counts';
            $data .= " with Count {$firstCount['c']}";
        } else {
            $message .= ' with identical Counts';
        }
        $message .= ", ignoring all but the first Instance";
        $data .= ')';
        $position = "{$reportItem->position}.Performance[{$count['p']}].Instance[{$count['i']}]";
        $this->addCriticalError($message, $message, $position, $data);
        $reportItem->setUnusable();
    }
}
