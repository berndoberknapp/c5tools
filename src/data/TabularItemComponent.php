<?php

/**
 * TabularItemComponent handles tabular COUNTER R5 Item Component list entries
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

use ubfr\c5tools\traits\TabularParsers;

class TabularItemComponent extends ItemComponent
{
    use TabularParsers;

    protected function parseDocument(): void
    {
        $this->setParsing();

        foreach ($this->document as $property => $value) {
            if ($property === 'Performance') {
                $this->performance = $value;
            } else {
                $this->setData($property, $value);
            }
        }
        $this->createItemId();

        $this->setParsed();

        $this->checkRequiredElements();
        $this->checkMetricTypes();

        $this->document = null;
    }

    protected function checkRequiredElements(): void
    {
        if ($this->get('Item_ID') === null) {
            $message = 'No (valid) Component identifier present';
            $data = $this->formatData('Item', $this->reportItem->get('Item') ?? '');
            $hint = "at least one identifier must be provided for each Component";
            $this->addCriticalError($message, $message, $this->position, $data, $hint);
            if (! isset($this->data['Item_Name'])) {
                // Item_ID is required, but the component is only unusable when Item_ID and Item_Name are missing
                $this->setUnusable();
            }
        }

        $property = 'Component_Data_Type';
        $reportElements = $this->reportHeader->getReportElements();
        if (isset($reportElements[$property]) && $this->get('Data_Type') === null) {
            $message = "{$property} value must not be empty";
            $columnName = $this->reportHeader->getColumnForColumnHeading($property);
            $position = "{$columnName}{$this->position}";
            $data = $this->formatData($property, '');
            $this->addCriticalError($message, $message, $position, $data);
            $this->setUnusable();
        }
    }

    protected function mergeMetricTypeDateError(
        ItemComponent $itemComponent,
        string $metricType,
        string $date,
        array $count
    ): void {
        $firstCount = $this->performance[$metricType][$date];
        $firstOccurrence = "cell {$firstCount['p']}";
        $message = "Multiple rows for the same Component, Data_Type, Metric_Type and month";
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
        $itemComponent->setUnusable();
    }
}
