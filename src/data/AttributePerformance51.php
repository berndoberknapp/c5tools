<?php

/**
 * AttributePerformance51 is the abstract base class for handling COUNTER R5.1 Attribute Performances list entries
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

use ubfr\c5tools\traits\CheckedDocument;
use ubfr\c5tools\traits\Checks;
use ubfr\c5tools\traits\Helpers;
use ubfr\c5tools\traits\Parsers;
use ubfr\c5tools\traits\MetricTypesPresent;
use ubfr\c5tools\exceptions\UnusableCheckedDocumentException;

abstract class AttributePerformance51 implements \ubfr\c5tools\interfaces\CheckedDocument
{
    use CheckedDocument;
    use Parsers;
    use Checks;
    use Helpers;
    use MetricTypesPresent;

    protected static array $itemMetricRelations = [
        [
            'Total_Item_Investigations',
            'Unique_Item_Investigations'
        ],
        [
            'Total_Item_Investigations',
            'Total_Item_Requests'
        ],
        [
            'Total_Item_Investigations',
            'Unique_Item_Requests'
        ],
        [
            'Unique_Item_Investigations',
            'Unique_Item_Requests'
        ],
        [
            'Total_Item_Requests',
            'Unique_Item_Requests'
        ]
    ];

    protected static array $titleMetricRelations = [
        [
            'Total_Item_Investigations',
            'Unique_Title_Investigations'
        ],
        [
            'Total_Item_Investigations',
            'Unique_Title_Requests'
        ],
        [
            'Unique_Item_Investigations',
            'Unique_Title_Investigations'
        ],
        [
            'Unique_Item_Investigations',
            'Unique_Title_Requests'
        ],
        [
            'Unique_Title_Investigations',
            'Unique_Title_Requests'
        ],
        [
            'Total_Item_Requests',
            'Unique_Title_Requests'
        ],
        [
            'Unique_Item_Requests',
            'Unique_Title_Requests'
        ]
    ];

    protected ReportHeader $reportHeader;

    protected bool $hasComponents;

    protected string $context;

    protected ?array $performance;

    protected array $metricTypeRows;

    abstract protected function parseDocument(): void;

    public function __construct(
        \ubfr\c5tools\interfaces\CheckedDocument $parent,
        string $position,
        int $index,
        $document,
        string $context
    ) {
        $this->document = $document;
        $this->checkResult = $parent->getCheckResult();
        $this->config = $parent->getConfig();
        $this->format = $parent->getFormat();
        $this->position = $position;

        $this->reportHeader = $parent->getReportHeader();
        $this->hasComponents = $parent->hasComponents();
        $this->context = $context;

        $this->performance = [];
        $this->metricTypeRows = [];
    }

    public function getReportHeader(): ReportHeader
    {
        return $this->reportHeader;
    }

    public function getMetricTypesPresent(): array
    {
        if (($components = $this->get('Components')) !== null) {
            return array_unique(array_merge($this->metricTypesPresent, $components->getMetricTypesPresent()));
        } else {
            return $this->metricTypesPresent;
        }
    }

    public function getHash(): string
    {
        $hashContext = hash_init('sha256');
        $this->updateHash($hashContext, $this->getJsonAttributes());
        return hash_final($hashContext);
    }

    protected function updateHash(object $hashContext, array $elements, ?string $position = null)
    {
        ksort($elements);
        foreach ($elements as $element => $value) {
            if (is_object($value) || is_array($value)) {
                continue;
            } else {
                $string = ($position === null ? $element : $position . '.' . $element) . ' => ' . $value;
                hash_update($hashContext, mb_strtolower($string));
            }
        }
    }

    public function getJsonAttributes(): array
    {
        // getJsonItemAttributeElements() also covers the component context case with just Data_Type
        $attributes = [];
        foreach ($this->reportHeader->getJsonItemAttributeElements() as $elementName) {
            $elementValue = $this->get($elementName);
            if ($elementValue !== null) {
                $attributes[$elementName] = $elementValue;
            }
        }

        return $attributes;
    }

    public function asJson(): \stdClass
    {
        if (! $this->isUsable()) {
            throw new UnusableCheckedDocumentException(get_class($this) . ' is unusable');
        }

        $json = new \stdClass();
        foreach ($this->getData() as $key => $value) {
            if ($key === 'Performance') {
                $json->$key = new \stdClass();
                foreach ($value as $metricType => $dateCounts) {
                    $json->$key->$metricType = (object) $dateCounts;
                }
            } else {
                $json->$key = (is_object($value) ? $value->asJson() : $value);
            }
        }

        return $json;
    }

    protected function getRequiredElements(): array
    {
        if ($this->context === 'item') {
            $requiredElements = $this->reportHeader->getJsonItemAttributeElements();
            $requiredElements[] = 'Performance';
            if ($this->isJson() && $this->hasComponents) {
                // for tabular reports rows with Unique_Title metrics don't have Components
                $requiredElements[] = 'Components';
            }
            return $requiredElements;
        } else {
            return [
                'Data_Type',
                'Performance'
            ];
        }
    }

    protected function checkRequiredElements(array $requiredElements): void
    {
        // any missing element makes the Attribute_Performance unusable, Performance is skipped
        // since it does not exist yet and is checked separatedly in parsePerformance
        foreach ($requiredElements as $requiredElement) {
            if ($requiredElement !== 'Performance' && $this->get($requiredElement) === null) {
                $this->setUnusable();
                return;
            }
        }

        // Comonents that should not be present also make the Attribute_Performance unusable
        if (! in_array('Components', $requiredElements) && $this->getInvalid('Components') !== null) {
            $this->setUnusable();
            return;
        }
    }

    protected function checkComponentMetricTypes(): void
    {
        if ($this->context !== 'component') {
            return;
        }

        foreach (array_keys($this->performance) as $metricType) {
            if (! preg_match('/Unique_Item_/', $metricType)) {
                continue;
            }
            $message = 'Unique_Item metrics cannot be broken down by Component';
            $hint = 'they must be reported at the Item level';
            $data = $this->formatData('Metric_Type', $metricType);
            if ($this->isJson()) {
                $position = "{$this->position}.Performance.{$metricType}";
            } else {
                $position = $this->reportHeader->getColumnForColumnHeading('Metric_Type') .
                    $this->metricTypeRows[$metricType];
            }
            $this->addCriticalError($message, $message, $position, $data, $hint);

            $this->setUnusable();
        }
    }

    protected function checkItemWithComponentsMetricTypes(): void
    {
        if (! $this->hasComponents) {
            return;
        }

        foreach (array_keys($this->performance) as $metricType) {
            if (preg_match('/^Unique_Item_/', $metricType)) {
                continue;
            }
            if (preg_match('/^Total_Item_/', $metricType)) {
                $message = 'Totel_Item';
                $hint = 'if the Item itself was used';
            } else {
                $message = 'Access Denied';
                $hint = 'if access to the Item itself was denied';
            }
            $message .= ' metrics must be reported at the Component level when Components are included in the report';
            $hint .= ' for a Component with the same metadata as the Item';
            $data = $this->formatData('Metric_Type', $metricType);
            if ($this->isJson()) {
                $position = "{$this->position}.Performance.{$metricType}";
            } else {
                $position = $this->reportHeader->getColumnForColumnHeading('Metric_Type') .
                    $this->metricTypeRows[$metricType];
            }
            $this->addCriticalError($message, $message, $position, $data, $hint);

            $this->setUnusable();
        }
    }

    // TODO: same function in traits/Performance
    protected function metricTypeMatch(string $metricTypeRegex): bool
    {
        if ($this->performance === null) {
            throw new \LogicException("Attribute_Performance already has been cleaned up");
        }

        foreach (array_keys($this->performance) as $metricType) {
            if (preg_match($metricTypeRegex, $metricType)) {
                return true;
            }
        }
        return false;
    }

    protected function checkPlatformDatabaseMetricTypes(): void
    {
        if (
            ! $this->config->isPlatformReport($this->reportHeader->getReportId()) &&
            ! $this->config->isDatabaseReport($this->reportHeader->getReportId())
        ) {
            return;
        }

        $dataType = $this->get('Data_Type');
        if ($dataType === null || $this->getInvalid('Metric_Type') !== null) {
            return;
        }

        if ($this->isJson()) {
            $position = $this->position;
        } else {
            // TODO: fix position with metricTypeRows
            $position = $this->reportHeader->getColumnForColumnHeading('Data_Type') . $this->position;
        }
        $data = $this->formatData('Data_Type', $dataType);

        if ($dataType !== 'Platform') {
            if ($this->metricTypeMatch('/^Searches_Platform$/')) {
                $message = "Data_Type must be 'Platform' for Metric_Type 'Searches_Platform'";
                $this->addCriticalError($message, $message, $position, $data);
                $this->setUnusable();
            }
        } else {
            if (
                ! empty($this->performance) &&
                (! $this->metricTypeMatch('/^Searches_Platform$/') || count($this->performance) > 1)
            ) {
                $message = "Data_Type 'Platform' is only applicable for Metric_Type 'Searches_Platform'";
                $this->addCriticalError($message, $message, $position, $data);
                $this->setUnusable();
            }
        }

        $databaseDataTypes = $this->config->getDatabaseDataTypes();
        if (! in_array($dataType, $databaseDataTypes)) {
            foreach (
                [
                'Searches_Automated',
                'Searches_Federated',
                'Searches_Regular',
                'Limit_Exceeded',
                'No_License'
                ] as $metricType
            ) {
                if ($this->metricTypeMatch("/^{$metricType}$/")) {
                    $message = 'Data_Type must be ' . (count($databaseDataTypes) > 1 ? 'one of ' : '') . "'" .
                        implode("', '", $databaseDataTypes) . "' for Metric_Type '{$metricType}'";
                    $this->addCriticalError($message, $message, $position, $data);
                    $this->setUnusable();
                }
            }
        } else {
            if ($this->metricTypeMatch('/_(Investigations|Requests)$/')) {
                $message = "Data_Type '{$dataType}' is only applicable for Searches and Access Denied Metric_Types";
                $hint = 'see Section 3.3.2 of the Code of Practice for details';
                $this->addCriticalError($message, $message, $position, $data, $hint);
                $this->setUnusable();
            }
        }
    }

    // TODO: same function in ReportItem
    protected function checkDataTypeUniqueTitleMetrics(): void
    {
        $dataType = $this->get('Data_Type');
        if ($dataType === null) {
            return;
        }

        foreach (array_keys($this->performance) as $metricType) {
            $uniqueTitleDataTypes = $this->config->getUniqueTitleDataTypes();
            if (preg_match('/^Unique_Title_/', $metricType) && ! in_array($dataType, $uniqueTitleDataTypes)) {
                $message = "Metric_Type '{$metricType}' is only applicable for Data_Type" .
                    (count($uniqueTitleDataTypes) > 1 ? 's' : '') . " '" . implode("', '", $uniqueTitleDataTypes) . "'";
                $data = $this->formatData('Data_Type/Metric_Type', "{$dataType}/{$metricType}");
                $this->addCriticalError($message, $message, $this->position, $data);
                $this->setUnusable();
            }
        }
    }

    public function checkMetricRelations(): void
    {
        $aggregatedPerformanceByDate = $this->getAggregatedPerformanceByDate();
        $permittedMetricTypes = $this->reportHeader->getReportValues()['Metric_Type'];

        foreach (self::$itemMetricRelations as $metrics) {
            if (in_array($metrics[0], $permittedMetricTypes) && in_array($metrics[1], $permittedMetricTypes)) {
                $this->compareMetrics($aggregatedPerformanceByDate, $metrics[0], $metrics[1]);
            }
        }

        if ($this->reportHeader->reportHasTitleMetrics()) {
            // for R5.1 all reports with Title metrics also include the Data_Type
            $isBook = in_array($this->get('Data_Type'), $this->config->getUniqueTitleDataTypes());
            foreach (self::$titleMetricRelations as $metrics) {
                if (in_array($metrics[0], $permittedMetricTypes) && in_array($metrics[1], $permittedMetricTypes)) {
                    $this->compareMetrics($aggregatedPerformanceByDate, $metrics[0], $metrics[1], $isBook);
                }
            }
        }
    }

    protected function getAggregatedPerformanceByDate(): array
    {
        if ($this->performance === null) {
            throw new \LogicException("Attribute_Performance already has been cleaned up");
        }

        $aggregatedPerformance = $this->performance;
        if (($components = $this->get('Components')) !== null) {
            $components->aggregatePerformance($aggregatedPerformance);
        }

        return $this->getPerformanceByDate($aggregatedPerformance);
    }

    public function aggregatePerformance(array &$aggregatedPerformance): void
    {
        if ($this->performance === null) {
            throw new \LogicException("Attribute_Performance already has been cleaned up");
        }

        foreach ($this->performance as $metricType => $dateCounts) {
            if (isset($aggregatedPerformance[$metricType])) {
                foreach ($dateCounts as $date => $count) {
                    if (isset($aggregatedPerformance[$metricType][$date])) {
                        $aggregatedPerformance[$metricType][$date] += $count;
                    } else {
                        $aggregatedPerformance[$metricType][$date] = $count;
                    }
                }
            } else {
                $aggregatedPerformance[$metricType] = $dateCounts;
            }
        }
    }

    protected function getPerformanceByDate(array $performance): array
    {
        $performanceByDate = [];
        foreach ($performance as $metricType => $dateCounts) {
            foreach ($dateCounts as $date => $count) {
                if ($count > 0) {
                    if (isset($performanceByDate[$date])) {
                        $performanceByDate[$date][$metricType] = $count;
                    } else {
                        $performanceByDate[$date] = [
                            $metricType => $count
                        ];
                    }
                }
            }
        }

        return $performanceByDate;
    }

    protected function compareMetrics(
        array $performanceByDate,
        string $metric1,
        string $metric2,
        ?bool $isBook = false
    ): void {
        foreach ($performanceByDate as $date => $metricCounts) {
            if (! isset($metricCounts[$metric1]) && ! isset($metricCounts[$metric2])) {
                // both metrics not present for this date, nothing to check
                continue;
            }
            if (! isset($metricCounts[$metric1])) {
                // when metric2 is present, metric1 also must be present
                $message = "{$metric1} is missing while {$metric2} is present";
                $data = $this->formatData('Metric_Type', $metric2);
                if ($this->isJson()) {
                    $position = "{$this->position}.Performance.{$metric2}.{$date}";
                } else {
                    // TODO: fix position by using metricTypeRows
                    $position = $this->metricTypeRows[$metric2];
                    $data .= ", Month {$date}";
                }
                $this->addCriticalError($message, $message, $position, $data);
                $this->setUnusable();
            } elseif (! isset($metricCounts[$metric2])) {
                // when metric2 is missing, the situation is more complex...
                if ($this->endsWith('_Investigations', $metric1) && $this->endsWith('_Requests', $metric2)) {
                    // when comparing Investigations with Requests
                    // there is no way to determine whether Requests must be present
                    continue;
                }
                if (strpos($metric1, '_Item_') !== false && strpos($metric2, '_Title_') !== false && ! $isBook) {
                    // when comparing an Item metric with a Title metric (both Investigations or both Requests)
                    // there is no way to determine whether the Title metric must be present
                    // unless we know that the item is a Book
                    continue;
                }
                $message = "{$metric2} is missing while {$metric1} is present";
                $data = $this->formatData('Metric_Type', $metric1);
                if ($this->isJson()) {
                    $position = "{$this->position}.Performance.{$metric1}.{$date}";
                } else {
                    // TODO: fix position by using metricTypeRows
                    $position = $this->position;
                    $data .= ", Month {$date}";
                }
                $this->addCriticalError($message, $message, $position, $data);
                $this->setUnusable();
            } elseif ($metricCounts[$metric1] < $metricCounts[$metric2]) {
                $message = "Less {$metric1} than {$metric2}";
                $data = $this->formatData('Metric_Type', $metric1);
                if ($this->isJson()) {
                    $position = "{$this->position}.Performance.{$metric1}.{$date}";
                } else {
                    // TODO: fix position by using metricTypeRows
                    $position = $this->position;
                    $data .= ", Month {$date}";
                }
                $data .= ", {$metricCounts[$metric1]} {$metric1} < {$metricCounts[$metric2]} {$metric2}";
                $this->addCriticalError($message, $message, $position, $data);
                $this->setUnusable();
            }
        }
    }

    public function checkParent(bool $parentIsNoParent, ?string $parentDataType): void
    {
        static $parentDataTypes = [
            'Article' => 'Journal',
            'Audiovisual' => [
                'Database_Aggregated',
                'Database_Full'
            ],
            'Book_Segment' => 'Book',
            'Conference_Item' => 'Conference',
            'Database_Full_Item' => 'Database_Full',
            'Image' => [
                'Database_Aggregated',
                'Database_Full'
            ],
            'Interactive_Resource' => [
                'Database_Aggregated',
                'Database_Full'
            ],
            'Multimedia' => [
                'Database_Aggregated',
                'Database_Full'
            ],
            'News_Item' => 'Newspaper_or_Newsletter',
            'Other' => [
                'Database_Full'
            ],
            'Patent' => [
                'Database_Full'
            ],
            'Reference_Item' => 'Reference_Work',
            'Report' => [
                'Database_Full'
            ],
            'Sound' => [
                'Database_Aggregated',
                'Database_Full'
            ],
            'Standard' => [
                'Database_Full'
            ],
            'Thesis_or_Dissertation' => [
                'Database_Full'
            ],
            'Unspecified' => [
                'Database_Full'
            ]
        ];

        // checks for IR with parent details, IR_A1 already has been checked on the item level
        $dataType = $this->get('Data_Type');
        if (! isset($parentDataTypes[$dataType])) {
            // only Items with a Data_Type that has a Parent Data_Type must have parent details
            if (! $parentIsNoParent) {
                $message = "An Item with Data_Type '{$dataType}' must not have a Parent";
                $position = $this->position;
                if ($this->isJson()) {
                    $position .= '.Data_Type';
                }
                $data = $this->formatData('Data_Type/Parent Data_Type', "{$dataType}/{$parentDataType}");
                $this->addCriticalError($message, $message, $position, $data);
                $this->setUnusable();
            }
        } elseif ($parentIsNoParent) {
            // cases with an array of parentDataTypes also allow no parent
            if (! is_array($parentDataTypes[$dataType])) {
                $message = 'Parent details are missing';
                $position = $this->position;
                if ($this->isJson()) {
                    $position .= '.Data_Type';
                }
                $data = $this->formatData('Data_Type', $dataType);
                $hint = "every Item with Data_Type '{$dataType}' should have Parent details";
                $this->addWarning($message, $message, $position, $data, $hint);
            }
        } elseif (is_array($parentDataTypes[$dataType]) && ! in_array($parentDataType, $parentDataTypes[$dataType])) {
            $permittedDataTypes = "'" . implode("', '", $parentDataTypes[$dataType]) . "' or no Parent";
            $message = "An Item with Data_Type '{$dataType}' must have a Parent with Data_Type {$permittedDataTypes}";
            $position = $this->position;
            if ($this->isJson()) {
                $position .= '.Data_Type';
            }
            $data = $this->formatData('Data_Type/Parent Data_Type', "{$dataType}/{$parentDataType}");
            $this->addCriticalError($message, $message, $position, $data);
            $this->setUnusable();
        } elseif (! is_array($parentDataTypes[$dataType]) && $parentDataType !== $parentDataTypes[$dataType]) {
            $message = "An Item with Data_Type '{$dataType}' must have a Parent with Data_Type '{$parentDataTypes[$dataType]}'";
            $position = $this->position;
            if ($this->isJson()) {
                $position .= '.Data_Type';
            }
            $data = $this->formatData('Data_Type/Parent Data_Type', "{$dataType}/{$parentDataType}");
            $this->addCriticalError($message, $message, $position, $data);
            $this->setUnusable();
        }
    }

    public function merge(AttributePerformance51 $attributePerformance): void
    {
        if ($this->performance === null) {
            throw new \LogicException("AttributePerformance51 already cleaned up");
        }

        $this->mergePerformance($attributePerformance);
        $this->mergeComponents($attributePerformance);

        $this->addMetricTypesPresent($attributePerformance->getMetricTypesPresent());
    }

    protected function mergePerformance(AttributePerformance51 $attributePerformance): void
    {
        foreach ($attributePerformance->performance as $metricType => $dateCounts) {
            if (isset($this->performance[$metricType])) {
                foreach ($dateCounts as $date => $count) {
                    if (isset($this->performance[$metricType][$date])) {
                        $position = $attributePerformance->getPosition() . ($this->isJson() ? '.Performance' : '');
                        $message = 'Multiple Counts for the same attribute set, Metric_Type and month with ';
                        $data = "Metric_Type '{$metricType}', month '{$date}', count {$count} (first occurence at " .
                            ($this->isJson() ? "{$this->position}.Performance" : "row {$this->position}");
                        if ($this->performance[$metricType][$date] === $count) {
                            $message .= 'identical';
                        } else {
                            $message .= 'different';
                            $data .= ' with count ' . $this->performance[$metricType][$date];
                        }
                        $message .= ' counts';
                        $data .= ')';
                        $this->addCriticalError($message, $message, $position, $data);
                        $attributePerformance->setUnusable();
                    } else {
                        $this->performance[$metricType][$date] = $count;
                    }
                }
            } else {
                $this->performance[$metricType] = $dateCounts;
            }
        }
        if ($attributePerformance->isUsable()) {
            $this->metricTypeRows = array_merge($this->metricTypeRows, $attributePerformance->metricTypeRows);
        }
    }

    protected function mergeComponents(AttributePerformance51 $attributePerformance): void
    {
        if ($attributePerformance->get('Components') !== null) {
            if ($this->get('Components') !== null) {
                $this->get('Components')->merge($attributePerformance->get('Components'));
                if (! $attributePerformance->get('Components')->isUsable()) {
                    $attributePerformance->setUnusable();
                }
            } else {
                $this->setData('Components', $attributePerformance->get('Components'));
            }
        }
    }

    public function storeData(): void
    {
        if ($this->performance === null) {
            throw new \LogicException("AttributePerformance already cleaned up");
        }

        // store components
        if (($componentItemList = $this->get('Components')) !== null) {
            $componentItemList->storeData();
            if (! $componentItemList->isUsable()) {
                $this->setUnusable();
            }
        }

        // remove zeros stored for checks from performance
        foreach ($this->performance as $metricType => $dateCounts) {
            foreach ($dateCounts as $date => $count) {
                if ($count === 0) {
                    unset($this->performance[$metricType][$date]);
                }
            }
            if (empty($this->performance[$metricType])) {
                unset($this->performance[$metricType]);
            }
        }

        // performance must not be empty unless components (with only access denied metrics) are present
        if (empty($this->performance) && $this->get('Components') === null) {
            $this->setUnusable();
        } else {
            $this->setData('Performance', $this->performance);
        }

        $this->performance = null;
    }
}
