<?php

/**
 * JsonAttributePerformance51 handles JSON COUNTER R5.1 Attribute Performance list entries
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

class JsonAttributePerformance51 extends AttributePerformance51
{
    protected static array $performanceConfig = [
        'parse' => 'parsePerformance',
        'attribute' => false,
        'metadata' => false,
        'required' => true
    ];

    protected static array $componentsConfig = [
        'parse' => 'parseComponents',
        'attribute' => false,
        'metadata' => false,
        'required' => true
    ];

    protected function parseDocument(): void
    {
        $this->setParsing();

        $elements = $this->reportHeader->getReportElements();
        $elements['Performance'] = self::$performanceConfig;
        $elements['Components'] = self::$componentsConfig;

        $requiredElements = $this->getRequiredElements();

        $properties = $this->getObjectProperties(
            $this->position,
            'Attribute_Performance',
            $this->document,
            $requiredElements,
            []
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

        $this->checkRequiredElements($requiredElements);
        if ($this->context === 'item') {
            $this->checkItemWithComponentsMetricTypes();
            $this->checkPlatformDatabaseMetricTypes();
            $this->checkDataTypeUniqueTitleMetrics();
        } else {
            $this->checkComponentMetricTypes();
        }

        $this->document = null;
    }

    protected function parsePerformance(string $position, string $property, $value): void
    {
        if (! $this->isObject($position, $property, $value)) {
            return;
        }

        $invalidPerformance = 0;
        $metricTypes = $this->reportHeader->getReportValues()['Metric_Type'];
        $properties = $this->getObjectProperties($position, 'Metric_Type', $value, [], $metricTypes);
        foreach ($properties as $metricType => $counts) {
            $this->metricTypesPresent[] = $metricType;
            $counts = $this->parseCounts("{$position}.{$metricType}", $metricType, $counts);
            if ($counts !== null) {
                $this->performance[$metricType] = $counts;
            } else {
                $invalidPerformance++;
            }
        }

        if (
            empty($this->performance) &&
            $invalidPerformance === 0 &&
            (! $this->hasComponents || $this->context === 'component')
        ) {
            $message = "{$property} must not be empty";
            $data = $this->formatData($property, $value);
            $hint = 'if there was no usage for the attribute combination the attribute performance must be omitted';
            $this->addError($message, $message, $position, $data, $hint);
            $this->setInvalid($property, $value);
            $this->setUnusable();
        }
    }

    protected function parseCounts(string $position, string $metricType, $value): ?array
    {
        if (! $this->isObject($position, $metricType, $value)) {
            $this->setUnusable();
            return null;
        }

        if ($this->isEmptyObject($value)) {
            $message = "{$metricType} must not be empty";
            $data = $this->formatData($metricType, $value);
            $hint = 'if there was no usage the Metric_Type must be omitted';
            $this->addError($message, $message, $position, $data, $hint);
            $this->setInvalid($metricType, $value);
            $this->setUnusable();
            return null;
        }

        $counts = [];
        $performanceDates = $this->reportHeader->getPerformanceDates();
        $properties = $this->getObjectProperties($position, 'Month', $value, [], $performanceDates);
        foreach ($properties as $date => $count) {
            $checkedCount = $this->checkedCount("{$position}.{$date}", $metricType, $date, $count);
            if ($checkedCount !== null) {
                $counts[$date] = $checkedCount;
            }
        }
        if (empty($counts)) {
            return null;
        }

        return $counts;
    }

    protected function checkedCount(string $position, string $property, string $date, $value): ?int
    {
        if (! is_scalar($value)) {
            $type = (is_object($value) ? 'an object' : 'an array');
            $message = "Count must be an integer, found {$type}";
            $data = $this->formatData('Count', $value);
            $this->addCriticalError($message, $message, $position, $data);
            $this->setInvalid("{$property}.{$date}", $value);
            $this->setUnusable();
            return null;
        }
        if (trim($value) === '') {
            $message = 'Count must not be empty';
            $data = $this->formatData('Count', $value);
            $hint = 'if there was no usage the month must be omitted';
            $this->addCriticalError($message, $message, $position, $data, $hint);
            $this->setInvalid("{$property}.{$date}", $value);
            $this->setUnusable();
            return null;
        }
        if (! is_numeric($value)) {
            $message = 'Count must be an integer';
            $data = $this->formatData('Count', $value);
            $this->addCriticalError($message, $message, $position, $data);
            $this->setInvalid("{$property}.{$date}", $value);
            $this->setUnusable();
            return null;
        }
        if (! is_int($value)) {
            $message = 'Count must be an integer';
            $data = $this->formatData('Count', $value);
            $this->addError($message, $message, $position, $data);
            $value = (int) $value;
        }
        if ($value < 0) {
            $message = 'Negative Count value is invalid';
            $data = $this->formatData('Count', $value);
            $this->addCriticalError($message, $message, $position, $data);
            $this->setInvalid("{$property}.{$date}", $value);
            $this->setUnusable();
            return null;
        }
        if ($value === 0) {
            $message = 'Count must not be zero';
            $data = $this->formatData('Count', $value);
            $hint = 'if there was no usage the month must be omitted';
            $this->addError($message, $message, $position, $data, $hint);
            $this->setInvalid("{$property}.{$date}", $value);
            // keep zeros so that multiple values for the same item/component, attribute set, metric
            // type and date can be detected, the zeros are later removed when the usage is stored
        }

        return $value;
    }

    protected function parseComponents(string $position, string $property, $value): void
    {
        if (! $this->isNonEmptyArray($position, $property, $value, false)) {
            return;
        }

        $componentItemList = new JsonComponentItemList51($this, $position, $value);
        if ($componentItemList->isUsable()) {
            $this->setData($property, $componentItemList);
            if ($componentItemList->isFixed()) {
                $this->setFixed($property, $value);
            }
        } else {
            $this->setInvalid($property, $value);
            $this->setUnusable();
        }
    }
}
