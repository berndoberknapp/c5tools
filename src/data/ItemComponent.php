<?php

/**
 * ItemComponent is the abstract base class for handling COUNTER R5 Item Component list entries
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
use ubfr\c5tools\traits\Performance;

abstract class ItemComponent implements \ubfr\c5tools\interfaces\CheckedDocument
{
    use CheckedDocument, Parsers, Checks, Helpers, Performance {
        Performance::asJson insteadof CheckedDocument;
    }

    protected ReportHeader $reportHeader;

    protected ReportItem $reportItem;

    /**
     * Positions of merged ItemComponents with the same metadata and attributes.
     *
     * @var array
     */
    protected array $itemComponentPositions;

    abstract protected function parseDocument(): void;

    abstract protected function mergeMetricTypeDateError(
        ItemComponent $itemComponent,
        string $metricType,
        string $date,
        array $count
    ): void;

    public function __construct(
        \ubfr\c5tools\interfaces\CheckedDocument $parent,
        string $position,
        int $index,
        $document
    ) {
        $this->document = $document;
        $this->checkResult = $parent->getCheckResult();
        $this->config = $parent->getConfig();
        $this->format = $parent->getFormat();
        $this->position = $position;

        $this->reportHeader = $parent->getReportHeader();
        $this->reportItem = $parent->getReportItem();
        $this->itemComponentPositions = [
            $index
        ];
        $this->performance = [];
    }

    public function getReportHeader(): ReportHeader
    {
        return $this->reportHeader;
    }

    public function getItemComponentPositions(): array
    {
        return $this->itemComponentPositions;
    }

    public function getFirstItemComponentPosition(): int
    {
        return $this->itemComponentPositions[0];
    }

    public function getHash(bool $ignoreFormat = false, bool $ignoreSectionType = false): string
    {
        $hashContext = hash_init('sha256');
        $this->updateHash($hashContext, $this->data);
        return hash_final($hashContext);
    }

    protected function updateHash(object $hashContext, array $elements, ?string $position = null)
    {
        ksort($elements);
        foreach ($elements as $element => $value) {
            $element = (string) $element;
            if (is_object($value)) {
                $value = $value->getData();
            }
            if (is_array($value)) {
                $this->updateHash($hashContext, $value, ($position === null ? $element : $position . '.' . $element));
            } else {
                $string = ($position === null ? $element : $position . '.' . $element) . ' => ' . $value;
                hash_update($hashContext, mb_strtolower($string));
            }
        }
    }

    public function merge(ItemComponent $itemComponent): void
    {
        // direct access to data instead of get() to avoid parsing loop
        $itemName = $this->get('Item_Name') ?? '(Item_Name missing)';
        $data = "Item_Name '{$itemName}' (first occurrence " . ($this->isJson() ? 'at' : 'in row') .
            " {$this->position})";

        if ($this->isJson()) {
            $message = 'Multiple Item_Components for the same Component and Data_Type';
            $hint = 'it is recommended to include all Periods and Metric_Types in a single Item_Component ' .
                'to reduce the size of the report and to make it easier to use the report';
            $this->addNotice($message, $message, $itemComponent->position, $data, $hint);
        }

        // merge performance
        foreach ($itemComponent->performance as $metricType => $dateCounts) {
            if (! isset($this->performance[$metricType])) {
                $this->performance[$metricType] = $dateCounts;
            } else {
                foreach ($dateCounts as $date => $count) {
                    if (! isset($this->performance[$metricType][$date])) {
                        $this->performance[$metricType][$date] = $count;
                    } else {
                        $this->mergeMetricTypeDateError($itemComponent, $metricType, $date, $count);
                        $itemComponent->setUnusable();
                    }
                }
            }
        }

        $this->addMetricTypesPresent($itemComponent->getMetricTypesPresent());

        $this->itemComponentPositions = array_merge(
            $this->itemComponentPositions,
            $itemComponent->itemComponentPositions
        );
    }

    protected function checkMetricTypes(): void
    {
        foreach ($this->performance as $metricType => $dateCounts) {
            if (! preg_match('/Unique_Item_/', $metricType)) {
                continue;
            }
            $message = 'Unique_Item metrics cannot be broken down by Component';
            $hint = 'they must be reported at the Item level';
            $data = $this->formatData('Metric_Type', $metricType);
            foreach ($dateCounts as $count) {
                if ($this->isJson()) {
                    $position = "{$this->position}.Performance[{$count['p']}].Instance[{$count['i']}].Metric_Type";
                } else {
                    $position = $count['p'];
                }
                $this->addCriticalError($message, $message, $position, $data, $hint);
            }
            $this->setUnusable();
        }
    }
}
