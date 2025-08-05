<?php

/**
 * Performance handles the COUNTER R5 Report Items and Item Coponents Performance
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\traits;

use ubfr\c5tools\data\ReportHeader;
use ubfr\c5tools\exceptions\ConfigException;
use ubfr\c5tools\exceptions\UnusableCheckedDocumentException;

trait Performance
{
    use MetricTypesPresent;

    protected ReportHeader $reportHeader;

    protected bool $hasDuplicatePeriods = false;

    /**
     * Performance for this item or component.
     * For JSON reports the data is stored indexed by metric type and begin date (as in R5.1) and position and count are
     * stored as an array ['c' => <count>, 'i' => <instance index>, 'p' => <performance index>], because this requires
     * less memory than using objects. The positions are removed when all items/components have been processed.
     *
     * @var array
     */
    protected ?array $performance = [];

    public function getReportHeader(): ReportHeader
    {
        return $this->reportHeader;
    }

    public function getMetricTypesPresent(): array
    {
        if (! $this->isParsed()) {
            $this->parseDocument();
        }

        if (($itemComponent = $this->get('Item_Component')) !== null) {
            return array_unique(array_merge($this->metricTypesPresent, $itemComponent->getMetricTypesPresent()));
        } else {
            return $this->metricTypesPresent;
        }
    }

    protected function getElementName(): string
    {
        return (strpos(self::class, 'ReportItem') !== false ? 'Report_Item' : 'Item_Component');
    }

    public function asJson(): \stdClass
    {
        if (! $this->isUsable()) {
            throw new UnusableCheckedDocumentException(get_class($this) . ' is unusable');
        }

        $json = new \stdClass();
        $attributePerformance = null;
        $items = null;
        foreach ($this->getData() as $key => $value) {
            if ($key === 'Performance') {
                $performances = [];
                foreach ($this->getPerformanceByDate() as $date => $metricTypeCount) {
                    $performance = new \stdClass();
                    $datetime = \DateTime::createFromFormat('!Y-m', $date);
                    $period = new \stdClass();
                    $period->Begin_Date = "{$date}-01";
                    $period->End_Date = $datetime->format('Y-m-t');
                    $instances = [];
                    foreach ($metricTypeCount as $metricType => $count) {
                        $instance = new \stdClass();
                        $instance->Metric_Type = $metricType;
                        $instance->Count = $count;
                        $instances[] = $instance;
                    }
                    $performance->Period = $period;
                    $performance->Instance = $instances;
                    $performances[] = $performance;
                }
                $json->Performance = $performances;
            } elseif ($key === 'Attribute_Performance') {
                $attributePerformance = $value->asJson();
            } elseif ($key === 'Items') {
                $items = $value->asJson();
            } else {
                $json->$key = (is_object($value) ? $value->asJson() : $value);
            }
        }
        if ($attributePerformance !== null) {
            $json->Attribute_Performance = $attributePerformance;
        }
        if ($items !== null) {
            $json->Items = $items;
        }

        return $json;
    }

    public function metricTypeMatch(string $metricTypeRegex): bool
    {
        if ($this->performance === null) {
            $elementName = $this->getElementName();
            throw new \LogicException("{$elementName} already has been cleaned up");
        }

        foreach (array_keys($this->performance) as $metricType) {
            if (preg_match($metricTypeRegex, $metricType)) {
                return true;
            }
        }
        return false;
    }

    public function getMetricTypes(): array
    {
        $performance = ($this->performance ?? $this->get('Performance'));
        if ($performance === null) {
            return [];
        } else {
            return array_keys($performance);
        }
    }

    public function getPerformanceByDate(): array
    {
        $performanceByDate = [];
        foreach (($this->performance ?? $this->get('Performance')) as $metricType => $dateCounts) {
            foreach ($dateCounts as $date => $count) {
                if (is_array($count)) {
                    $count = $count['c'];
                }
                if ($count > 0) {
                    if (! isset($performanceByDate[$date])) {
                        $performanceByDate[$date] = [
                            $metricType => $count
                        ];
                    } else {
                        $performanceByDate[$date][$metricType] = $count;
                    }
                }
            }
        }

        return $performanceByDate;
    }

    protected function parsePerformance(string $position, string $property, $value): void
    {
        if (! $this->isNonEmptyArray($position, $property, $value, false)) {
            $this->setUnusable();
            return;
        }

        $requiredElements = [
            'Period',
            'Instance'
        ];
        foreach ($value as $index => $periodInstance) {
            $properties = $this->getObjectProperties(
                "{$position}[{$index}]",
                $property,
                $periodInstance,
                $requiredElements
            );
            if (
                ! isset($properties['Period']) ||
                ! $this->isObject("{$this->position}.Period", 'Period', $properties['Period'])
            ) {
                $this->setUnusable();
                continue;
            }
            $beginYearMonth = $this->parsePeriod($position, $index, $properties['Period']);
            if (
                ! isset($properties['Instance']) ||
                ! $this->isNonEmptyArray("{$this->position}.Instance", 'Instance', $properties['Instance'], false)
            ) {
                $this->setUnusable();
                continue;
            }
            $this->parseInstance($position, $index, $properties['Instance'], $beginYearMonth);
        }
    }

    protected function parsePeriod(string $performancePosition, string $performanceIndex, $value): ?string
    {
        $requiredElements = [
            'Begin_Date',
            'End_Date'
        ];
        $position = "{$performancePosition}[{$performanceIndex}].Period";
        $properties = $this->getObjectProperties($position, 'Period', $value, $requiredElements);
        if (isset($properties['Begin_Date'])) {
            $beginDate = $this->checkedDate("{$position}.Begin_Date", 'Begin_Date', $properties['Begin_Date']);
        } else {
            $beginDate = null;
        }
        if (isset($properties['End_Date'])) {
            $endDate = $this->checkedDate("{$position}.End_Date", 'End_Date', $properties['End_Date']);
        } else {
            $endDate = null;
        }
        if ($beginDate === null || $endDate === null) {
            $this->setUnusable();
            return null;
        }

        switch ($this->reportHeader->getGranularity()) {
            case 'Month':
                if ($beginDate < $this->reportHeader->getBeginDate() || $this->reportHeader->getEndDate() < $beginDate) {
                    $summary = 'Begin_Date value is outside the reporting period';
                    $message = "Begin_Date value '{$beginDate}' is outside the reporting period ('" .
                        $this->reportHeader->getBeginDate() . "' to '" . $this->reportHeader->getEndDate() . "')";
                    $data = $this->formatData('Begin_Date', $beginDate);
                    $this->addCriticalError($summary, $message, "{$position}.Begin_Date", $data);
                    $beginDate = null;
                }
                if ($endDate < $this->reportHeader->getBeginDate() || $this->reportHeader->getEndDate() < $endDate) {
                    $summary = 'End_Date value is outside the reporting period';
                    $message = "End_Date value '{$endDate}' is outside the reporting period ('" .
                        $this->reportHeader->getBeginDate() . "' to '" . $this->reportHeader->getEndDate() . "')";
                    $data = $this->formatData('End_Date', $endDate);
                    $this->addCriticalError($summary, $message, "{$position}.End_Date", $data);
                    $endDate = null;
                }
                if ($beginDate !== null && $endDate !== null) {
                    if ($beginDate > $endDate) {
                        $summary = 'Period is invalid, End_Date is before Begin_Date';
                        $message = "Period '{$beginDate}' to '{$endDate}' is invalid, End_Date is before Begin_Date";
                        $data = $this->formatData('Period', "{$beginDate}' to '{$endDate}");
                        $this->addCriticalError($summary, $message, $position, $data);
                        $beginDate = null;
                        $endDate = null;
                    } else {
                        $dt = \DateTime::createFromFormat('!Y-m-d', $beginDate);
                        $expectedEndDate = $dt->format('Y-m-t');
                        if ($endDate !== $expectedEndDate) {
                            $summary = 'Period is invalid';
                            $message = "Period '{$beginDate}' to '{$endDate}' is invalid";
                            $data = $this->formatData('Period', "{$beginDate}' to '{$endDate}");
                            $hint = 'for Granularity Month the Performance.Period must be exactly one month';
                            $this->addCriticalError($summary, $message, $position, $data, $hint);
                            $beginDate = null;
                            $endDate = null;
                        }
                    }
                }
                break;
            case 'Totals':
                if ($beginDate !== $this->reportHeader->getBeginDate()) {
                    $summary = 'Begin_Date value is invalid';
                    $message = "Begin_Date value '{$beginDate}' is invalid (expected '" .
                        $this->reportHeader->getBeginDate() . "')";
                    $data = $this->formatData('Begin_Date', $beginDate);
                    $hint = 'for Granularity Totals the Performance.Period.Begin_Date must be identical with the reporting period Begin_Date';
                    $this->addCriticalError($summary, $message, "{$position}.Begin_Date", $data, $hint);
                    $beginDate = null;
                }
                if ($endDate !== $this->reportHeader->getEndDate()) {
                    $summary = 'End_Date value is invalid';
                    $message = "End_Date value '{$endDate}' is invalid (expected '" . $this->reportHeader->getEndDate() .
                        "')";
                    $data = $this->formatData('End_Date', $endDate);
                    $hint = 'for Granularity Totals the Performance.Period.End_Date must be identical with the reporting period End_Date';
                    $this->addCriticalError($summary, $message, "{$position}.End_Date", $data, $hint);
                    $endDate = null;
                }
                break;
            default:
                throw new ConfigException("granularity '" . $this->reportHeader->getGranularity() . "' unknown");
                break;
        }

        if ($beginDate === null || $endDate === null) {
            $this->setUnusable();
            return null;
        }

        $dt = \DateTime::createFromFormat('!Y-m-d', $beginDate);
        $beginYearMonth = $dt->format('Y-m');

        $occurrences = [];
        foreach ($this->performance as $yearMonthCount) {
            if (isset($yearMonthCount[$beginYearMonth])) {
                $occurrences[] = 'Performance[' . $yearMonthCount[$beginYearMonth]['p'] . ']';
            }
        }
        if (! empty($occurrences)) {
            $message = 'Multiple Instances for the same Period';
            $data = $this->formatData('Period', "{$beginDate}' to '{$endDate}") . ' (other occurrence(s): ' .
                implode(', ', array_unique($occurrences)) . ')';
            $hint = 'it is recommended to include all Metric_Types in a single Instance to reduce the size of the report and to make it easier to use the report';
            $this->addNotice($message, $message, $position, $data, $hint);
            $this->hasDuplicatePeriods = true;
        }

        return $beginYearMonth;
    }

    protected function parseInstance(
        string $performancePosition,
        string $performanceIndex,
        $value,
        ?string $beginYearMonth
    ): void {
        $requiredElements = [
            'Metric_Type',
            'Count'
        ];
        foreach ($value as $instanceIndex => $metricTypeCount) {
            $position = "{$performancePosition}[{$performanceIndex}].Instance[{$instanceIndex}]";
            if (! $this->isObject($position, 'Instance', $metricTypeCount)) {
                $this->setUnusable();
                continue;
            }
            $properties = $this->getObjectProperties($position, 'Instance', $metricTypeCount, $requiredElements);

            if (isset($properties['Metric_Type'])) {
                $metricType = $this->checkedEnumeratedValue(
                    "{$position}.Metric_Type",
                    'Metric_Type',
                    $properties['Metric_Type']
                );
            } else {
                $metricType = null;
            }
            if ($metricType === null) {
                $this->setUnusable();
            } else {
                $this->metricTypesPresent[] = $metricType;
            }

            if (! isset($properties['Count'])) {
                $this->setUnusable();
                continue;
            }

            $positionCount = "{$position}.Count";
            if (! is_scalar($properties['Count'])) {
                $type = (is_object($properties['Count']) ? 'an object' : 'an array');
                $message = "Count must be a scalar, found {$type}";
                $this->addCriticalError($message, $message, $positionCount, 'Count');
                $this->setUnusable();
                continue;
            }

            $count = $properties['Count'];
            $data = $this->formatData('Count', $count);
            if (trim($count) === '') {
                $message = 'Count must not be empty';
                $hint = 'if there was no usage in the period the Instance must be omitted';
                $this->addCriticalError($message, $message, $positionCount, $data, $hint);
                $this->setUnusable();
                continue;
            }
            if (! is_numeric($count)) {
                $message = 'Count must be an integer';
                $this->addCriticalError($message, $message, $positionCount, $data);
                $this->setUnusable();
                continue;
            }
            if (! is_int($count)) {
                $message = 'Count must be an integer';
                $this->addError($message, $message, $positionCount, $data);
                $count = (int) $count;
            }
            if ($count < 0) {
                $message = 'Negative Count value is invalid';
                $this->addCriticalError($message, $message, $positionCount, $data);
                $this->setUnusable();
                continue;
            }
            if ($count === 0) {
                $message = 'Count must not be zero';
                $hint = 'if there was no usage in the period the Instance must be omitted';
                $this->addError($message, $message, $positionCount, $data, $hint);
                continue;
            }

            if ($metricType !== null && $beginYearMonth !== null) {
                if (! isset($this->performance[$metricType])) {
                    $this->performance[$metricType] = [];
                }
                if (! isset($this->performance[$metricType][$beginYearMonth])) {
                    $this->performance[$metricType][$beginYearMonth] = [
                        'c' => $count,
                        'i' => $instanceIndex,
                        'p' => $performanceIndex
                    ];
                } else {
                    $message = 'Multiple Instances for the same Period and Metric_Type';
                    $firstCount = $this->performance[$metricType][$beginYearMonth];
                    $data = "Begin_Date '{$beginYearMonth}-01', Metric_Type '{$metricType}', Count {$count}" .
                        " (first occurrence at Instance[{$firstCount['i']}]";
                    if ($count !== $firstCount['c']) {
                        $message .= ' with different Counts';
                        $data .= " with Count {$firstCount['c']}";
                    } else {
                        $message .= ' with identical Counts';
                    }
                    $message .= ', ignoring all but the first Instance';
                    $data .= ')';
                    $this->addCriticalError($message, $message, $position, $data);
                    $this->setUnusable();
                }
            }
        }
    }

    public function cleanupPerformance(): void
    {
        $elementName = $this->getElementName();
        if ($this->performance === null) {
            throw new \LogicException("{$elementName} already has been cleaned up");
        }

        foreach ($this->performance as $metricType => $dateCounts) {
            foreach ($dateCounts as $date => $countPosition) {
                if ($countPosition['c'] > 0) {
                    $this->performance[$metricType][$date] = $countPosition['c'];
                } else {
                    unset($this->performance[$metricType][$date]);
                }
            }
            if (empty($this->performance[$metricType])) {
                unset($this->performance[$metricType]);
            }
        }

        $this->setData('Performance', $this->performance);
        $this->performance = null;
    }
}
