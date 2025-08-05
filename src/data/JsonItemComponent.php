<?php

/**
 * JsonItemComponent handles JSON COUNTER R5 Item Component list entries
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

class JsonItemComponent extends ItemComponent
{
    protected function parseDocument(): void
    {
        $this->setParsing();

        $elements = $this->reportHeader->getJsonComponentElements();
        $requiredElements = [];
        $optionalElements = [];
        foreach ($elements as $elementName => $elementConfig) {
            if ($elementConfig['required']) {
                $requiredElements[] = $elementName;
            } else {
                $optionalElements[] = $elementName;
            }
        }

        $properties = $this->getObjectProperties(
            $this->position,
            'Item_Component',
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

        $this->checkRequiredElements();
        $this->checkMetricTypes();

        if ($this->hasDuplicatePeriods && $this->isUsable()) {
            $this->setFixed('Performance', $properties['Performance']);
        }

        $this->document = null;
    }

    protected function checkRequiredElements(): void
    {
        if ($this->get('Item_ID') === null && $this->get('Item_Name') === null) {
            // Item_ID is required, but the parent is only unusable when Item_ID and Item_Name are missing
            $this->setUnusable();
            return;
        }
        if (empty($this->performance)) {
            $this->setUnusable();
            return;
        }
    }

    protected function mergeMetricTypeDateError(
        ItemComponent $itemComponent,
        string $metricType,
        string $date,
        array $count
    ): void {
        $firstCount = $this->performance[$metricType][$date];
        $firstOccurrence = "{$this->position}.Performance[{$firstCount['p']}].Instance[{$firstCount['i']}]";
        $message = "Multiple Item_Components for the same Component, Data_Type, Period and Metric_Type";
        $data = "Begin_Date '{$date}-01', Metric_Type '{$metricType}', Count {$count['c']} (first occurrence at {$firstOccurrence}";
        if ($count['c'] !== $this->performance[$metricType][$date]['c']) {
            $message .= ' with different Counts';
            $data .= " with Count {$firstCount['c']}";
        } else {
            $message .= ' with identical Counts';
        }
        $message .= ", ignoring all but the first Instance";
        $data .= ')';
        $position = "{$itemComponent->position}.Performance[{$count['p']}].Instance[{$count['i']}]";
        $this->addCriticalError($message, $message, $position, $data);
        $itemComponent->setUnusable();
    }
}
