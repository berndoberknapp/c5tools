<?php

/**
 * AttributePerformanceList51 is the abstract base class for handling COUNTER R5.1 Attribute Performance lists
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

abstract class AttributePerformanceList51 implements
    \ubfr\c5tools\interfaces\CheckedDocument,
    \Countable,
    \IteratorAggregate
{
    use CheckedDocument;
    use Parsers;
    use Checks;
    use Helpers;
    use MetricTypesPresent;

    // permitted Data_Types for databases according to Table 3.p of the Code of Practice Release 5.1.0.1
    protected static array $databaseDataTypes = [
        'Database_Aggregated' => [
            'Audiovisual',
            'Book',
            'Conference',
            'Database_Aggregated',
            'Image',
            'Interactive_Resource',
            'Journal',
            'Multimedia',
            'Newspaper_or_Newsletter',
            'Other',
            'Patent',
            'Reference_Work',
            'Report',
            'Sound',
            'Standard',
            'Thesis_or_Dissertation',
            'Unspecified'
        ],
        'Database_AI' => [
            'Audiovisual',
            'Book',
            'Conference',
            'Database_AI',
            'Image',
            'Interactive_Resource',
            'Journal',
            'Multimedia',
            'Newspaper_or_Newsletter',
            'Other',
            'Patent',
            'Reference_Work',
            'Report',
            'Sound',
            'Standard',
            'Thesis_or_Dissertation',
            'Unspecified'
        ],
        'Database_Full' => [
            'Audiovisual',
            'Database_Full',
            'Database_Full_Item',
            'Image',
            'Interactive_Resource',
            'Multimedia',
            'Other',
            'Patent',
            'Report',
            'Sound',
            'Standard',
            'Thesis_or_Dissertation',
            'Unspecified'
        ]
    ];

    protected ReportHeader $reportHeader;

    protected bool $hasComponents;

    protected ?array $attributePerformances;

    abstract protected function parseDocument(): void;

    public function __construct(\ubfr\c5tools\interfaces\CheckedDocument $parent, string $position, $document)
    {
        $this->document = $document;
        $this->checkResult = $parent->getCheckResult();
        $this->config = $parent->getConfig();
        $this->format = $parent->getFormat();
        $this->position = $position;

        $this->reportHeader = $parent->getReportHeader();
        $this->hasComponents = ($parent instanceof ReportItem) && $this->reportHeader->includesComponentDetails();
        $this->attributePerformances = [];
    }

    public function count(): int
    {
        if (! $this->isParsed()) {
            $this->parseDocument();
        }
        return ($this->isUsable() ? count($this->get('.')) : 0);
    }

    public function getIterator(): \ArrayIterator
    {
        if (! $this->isParsed()) {
            $this->parseDocument();
        }
        return new \ArrayIterator($this->isUsable() ? $this->get('.') : []);
    }

    public function getReportHeader(): ReportHeader
    {
        return $this->reportHeader;
    }

    public function hasComponents(): bool
    {
        return $this->hasComponents;
    }

    public function aggregatePerformance(array &$aggregatedPerformance): void
    {
        if ($this->attributePerformances === null) {
            throw new \LogicException("AttributePerformanceList51 already cleaned up");
        }

        foreach ($this->attributePerformances as $attributePerformance) {
            $attributePerformance->aggregatePerformance($aggregatedPerformance);
        }
    }

    public function checkParent(bool $parentIsNoParent, ?string $parentDataType): void
    {
        if ($this->attributePerformances === null) {
            throw new \LogicException("AttributePerformanceList51 already cleaned up");
        }

        foreach ($this->attributePerformances as $attributePerformance) {
            $attributePerformance->checkParent($parentIsNoParent, $parentDataType);
        }
    }

    public function checkDataTypes(): void
    {
        if ($this->attributePerformances === null) {
            throw new \LogicException("AttributePerformanceList51 already cleaned up");
        }

        if ($this->config->isPlatformReport($this->reportHeader->getReportId())) {
            // for platform reports there is nothing to check since any combination of Data_Types is valid
            return;
        }

        $dataTypes = [];
        foreach ($this->attributePerformances as $attributePerformance) {
            $dataType = $attributePerformance->get('Data_Type');
            if (! in_array($dataType, $dataTypes)) {
                $dataTypes[] = $dataType;
            }
        }

        if ($this->config->isDatabaseReport($this->reportHeader->getReportId())) {
            $this->checkDatabaseDataTypes($dataTypes);
        } elseif (count($dataTypes) > 1) {
            $message = 'Different Data_Types for the same Report_Item';
            $data = $this->formatData('Data_Types', implode('/', $dataTypes));
            $this->addCriticalError($message, $message, $this->position, $data);
            $this->setUnusable();
        }

        // TODO: Anything to check for IR with components?
    }

    protected function checkDatabaseDataTypes(array $dataTypes): void
    {
        $databaseDataTypes = array_intersect($dataTypes, array_keys(self::$databaseDataTypes));
        if (count($databaseDataTypes) === 0) {
            return;
        }

        if (count($databaseDataTypes) > 1) {
            $message = 'Different database Data_Types for the same Report_Item';
            $data = $this->formatData('Data_Types', implode('/', $databaseDataTypes));
            $this->addCriticalError($message, $message, $this->position, $data);
            $this->setUnusable();
            return;
        }

        $databaseDataType = reset($databaseDataTypes);
        $invalidDataTypes = array_diff($dataTypes, self::$databaseDataTypes[$databaseDataType]);
        if (! empty($invalidDataTypes)) {
            $message = "Invalid Data_Type(s) for {$databaseDataType}";
            $data = $this->formatData(
                'Data_Type' . (count($invalidDataTypes) > 1 ? 's' : ''),
                implode('/', $invalidDataTypes)
            );
            $hint = 'see Section 3.3.2 of the Code of Practice for details';
            $this->addCriticalError($message, $message, $this->position, $data, $hint);
            $this->setUnusable();
        }

        if ($databaseDataType === 'Database_AI') {
            $requestMetricTypes = array_filter(
                $this->metricTypesPresent,
                function (string $metricType): bool {
                    return $this->endsWith('_Requests', $metricType);
                }
            );
            if (! empty($requestMetricTypes)) {
                $message = 'Requests are not permitted for Database_AI';
                $data = $this->formatData(
                    'Metric_Type' . (count($requestMetricTypes) > 1 ? 's' : ''),
                    implode('/', $requestMetricTypes)
                );
                $this->addCriticalError($message, $message, $this->position, $data);
                $this->setUnusable();
            }
        }
    }

    public function checkMetricRelations(): void
    {
        if ($this->attributePerformances === null) {
            throw new \LogicException("AttributePerformanceList51 already cleaned up");
        }

        foreach ($this->attributePerformances as $attributePerformance) {
            $attributePerformance->checkMetricRelations();
        }
    }

    public function merge(AttributePerformanceList51 $attributePerformanceList): void
    {
        if ($this->attributePerformances === null) {
            throw new \LogicException("AttributePerformanceList51 already cleaned up");
        }

        foreach ($attributePerformanceList->attributePerformances as $hash => $attributePerformance) {
            if (isset($this->attributePerformances[$hash])) {
                $this->attributePerformances[$hash]->merge($attributePerformance);
            } else {
                $this->attributePerformances[$hash] = $attributePerformance;
            }
        }

        $this->addMetricTypesPresent($attributePerformanceList->getMetricTypesPresent());
    }

    public function storeData(): void
    {
        if ($this->attributePerformances === null) {
            throw new \LogicException("AttributePerformanceList already cleaned up");
        }

        foreach ($this->attributePerformances as $attributePerformance) {
            $attributePerformance->storeData();
            $this->setIndex($attributePerformance->getIndexFromPosition());
            if ($attributePerformance->isUsable()) {
                $this->setData('.', $attributePerformance);
            } else {
                $this->setUnusable();
            }
        }
        $this->setIndex(null);

        $this->attributePerformances = null;
    }
}
