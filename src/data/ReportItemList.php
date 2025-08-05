<?php

/**
 * ReportItemList is the abstract base class for parsing and validating COUNTER Report Item lists
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

use ubfr\c5tools\Report;
use ubfr\c5tools\traits\CheckedDocument;
use ubfr\c5tools\traits\Checks;
use ubfr\c5tools\traits\Helpers;
use ubfr\c5tools\traits\Parsers;
use ubfr\c5tools\traits\MetricTypesPresent;
use ubfr\c5tools\exceptions\UnusableCheckedDocumentException;

abstract class ReportItemList implements \ubfr\c5tools\interfaces\CheckedDocument, \Countable, \IteratorAggregate
{
    use CheckedDocument;
    use Parsers;
    use Checks;
    use Helpers;
    use MetricTypesPresent;

    protected ReportHeader $reportHeader;

    protected ?array $items;

    protected ?array $parents;

    protected ?array $components;

    abstract protected function parseDocument(): void;

    public function __construct(\ubfr\c5tools\interfaces\CheckedDocument $parent, string $position, $document)
    {
        $this->document = $document;
        $this->checkResult = $parent->getCheckResult();
        $this->config = $parent->getConfig();
        $this->format = $parent->getFormat();
        $this->position = $position;

        $this->reportHeader = ($parent instanceof Report ? $parent->get('Report_Header') : $parent->getReportHeader());

        $this->items = [];
        $this->parents = [];
        $this->components = [];
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

    public function asJson(): array
    {
        if (! $this->isUsable()) {
            throw new UnusableCheckedDocumentException(get_class($this) . ' is unusable');
        }

        $json = [];
        if ($this->get('.') !== null) {
            foreach ($this->get('.') as $value) {
                $json[] = $value->asJson();
            }
        }
        return $json;
    }

    public function getStoredParent(ItemParent $itemParent): ItemParent
    {
        $hash = $itemParent->getHash();
        if (! isset($this->components[$hash])) {
            $this->components[$hash] = $itemParent;
        }

        return $this->components[$hash];
    }

    protected function checkItemMetrics(): void
    {
        if ($this->items === null) {
            throw new \LogicException("ReportItem already cleaned up");
        }

        static $compareItemMetrics = [
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

        foreach ($this->relatedItems() as $items) {
            $aggregatedPerformance = $this->aggregatedItemPerformance($items);
            if (empty($aggregatedPerformance)) {
                continue;
            }

            $positions = [];
            foreach ($items as $item) {
                $positions = array_merge($positions, $item->getReportItemPositions());
            }
            sort($positions);

            $lastPosition = end($positions);
            $position = ($this->isJson() ? ".Report_Items[{$lastPosition}]" : $lastPosition);
            $itemNameElement = $this->getItemNameElement('Item');
            $data = "{$itemNameElement} '" . $item->get($itemNameElement) . "'";
            if (count($positions) > 1) {
                $data .= ' (occurrences: ';
                if ($this->isJson()) {
                    $data .= '.Report_Items[' . implode('], .Report_Items[', $positions) . '])';
                } else {
                    $data .= 'rows ' . implode(', ', $positions) . ')';
                }
            }
            $permittedMetricTypes = $this->reportHeader->getReportValues()['Metric_Type'];
            foreach ($compareItemMetrics as $metrics) {
                if (in_array($metrics[0], $permittedMetricTypes) && in_array($metrics[1], $permittedMetricTypes)) {
                    $this->compareMetrics($aggregatedPerformance, $metrics[0], $metrics[1], $position, $data);
                }
            }
        }
    }

    protected function checkTitleMetrics()
    {
        if ($this->items === null) {
            throw new \LogicException("ReporItem already cleaned up");
        }

        static $compareTitleMetrics = [
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

        $reportValues = $this->reportHeader->getReportValues();
        if (
            (isset($reportValues['Data_Type']) && ! in_array('Book', $reportValues['Data_Type'])) ||
            ! (in_array('Unique_Title_Investigations', $reportValues['Metric_Type']) ||
            in_array('Unique_Title_Requests', $reportValues['Metric_Type']))
        ) {
            // if the report doesn't include Books and Unique_Title metrics there is nothing to check
            return;
        }
        // TODO: Does this work for TR_B*?
        $isAllBookReport = (isset($reportValues['Data_Type']) && count($reportValues['Data_Type']) === 1);

        foreach ($this->relatedItems(true) as $items) {
            $aggregatedPerformance = $this->aggregatedTitlePerformance($items);
            if (empty($aggregatedPerformance)) {
                continue;
            }

            $positions = [];
            foreach ($items as $item) {
                $positions = array_merge($positions, $item->getReportItemPositions());
            }
            sort($positions);

            $lastPosition = end($positions);
            $position = ($this->isJson() ? ".Report_Items[{$lastPosition}]" : $lastPosition);
            $itemNameElement = $this->getItemNameElement('Item');
            $data = "{$itemNameElement} '" . $item->get($itemNameElement) . "'";
            if (count($positions) > 1) {
                $data .= ' (occurrences: ';
                if ($this->isJson()) {
                    $data .= '.Report_Items[' . implode('], .Report_Items[', $positions) . '])';
                } else {
                    $data .= 'rows ' . implode(', ', $positions) . ')';
                }
            }
            $dataType = ($isAllBookReport ? 'Book' : $item->get('Data_Type'));
            $permittedMetricTypes = $this->reportHeader->getReportValues()['Metric_Type'];
            foreach ($compareTitleMetrics as $metrics) {
                if (in_array($metrics[0], $permittedMetricTypes) && in_array($metrics[1], $permittedMetricTypes)) {
                    $this->compareMetrics(
                        $aggregatedPerformance,
                        $metrics[0],
                        $metrics[1],
                        $position,
                        $data,
                        $dataType
                    );
                }
            }
        }
    }

    /**
     * Group items with identical metadata and attributes except for Format and optionally Section_Type.
     *
     * When comparing metrics this has to be done across multiple Report_Items if Format and/or Section_Type are
     * involved. This method groups Report_Items with identical metadata and attributes except for Format
     * (relevant for Total_Item_Request) or Format and Section_Type (relevant for Unique_Title metric) by hashes
     * computed over metadata and attributes except for Format and optionally Section_Type.
     *
     * @param bool $ignoreSectionType
     * @return array
     */
    protected function relatedItems(bool $ignoreSectionType = false): array
    {
        $relatedItems = [];
        foreach ($this->items as $item) {
            $hash = ($ignoreSectionType ? $item->getBaseHash() : $item->getNoFormatHash());
            if (! isset($relatedItems[$hash])) {
                $relatedItems[$hash] = [];
            }
            $relatedItems[$hash][] = $item;
        }

        return $relatedItems;
    }

    protected function aggregatedItemPerformance(array $items): array
    {
        $aggregatedPerformance = [];
        foreach ($items as $item) {
            if (count($items) === 1 && ! $item->getReportHeader()->includesComponentDetails()) {
                // optimization, avoid aggregatePerformance call for a single item without components
                return $item->getPerformanceByDate();
            }
            $this->aggregatePerformance($aggregatedPerformance, $item->getPerformanceByDate());
            if (($itemComponentList = $item->get('Item_Component')) !== null) {
                $this->aggregatePerformance($aggregatedPerformance, $itemComponentList->getPerformanceByDate());
            }
        }

        return $aggregatedPerformance;
    }

    protected function aggregatedTitlePerformance(array $items): array
    {
        $aggregatedPerformance = [];
        foreach ($items as $item) {
            if (count($items) === 1) {
                return $item->getPerformanceByDate();
            }
            $this->aggregatePerformance($aggregatedPerformance, $item->getPerformanceByDate());
        }

        return $aggregatedPerformance;
    }

    // TODO: identical with ItemComponentList::aggregatePerformance, move to trait?
    protected function aggregatePerformance(array &$aggregatedPerformance, array $performance): void
    {
        foreach ($performance as $date => $metricCounts) {
            if (! isset($aggregatedPerformance[$date])) {
                $aggregatedPerformance[$date] = $metricCounts;
            } else {
                foreach ($metricCounts as $metric => $count) {
                    if (! isset($aggregatedPerformance[$date][$metric])) {
                        $aggregatedPerformance[$date][$metric] = $count;
                    } else {
                        $aggregatedPerformance[$date][$metric] += $count;
                    }
                }
            }
        }
    }

    protected function compareMetrics($performance, $metric1, $metric2, $position, $data, $dataType = null)
    {
        foreach ($performance as $date => $metricCounts) {
            if (! isset($metricCounts[$metric1]) && ! isset($metricCounts[$metric2])) {
                // both metrics not present for this date, nothing to check
                continue;
            }
            $dataWithDate = $data . ", Month {$date}";
            if (! isset($metricCounts[$metric1])) {
                // when metric2 is present, metric1 also must be present
                $message = "{$metric1} is missing while {$metric2} is present";
                $this->addCriticalError($message, $message, $position, $dataWithDate);
                $this->setUnusable();
            } elseif (! isset($metricCounts[$metric2])) {
                // when metric2 is missing, the situation is more complex...
                if ($this->endsWith('_Investigations', $metric1) && $this->endsWith('_Requests', $metric2)) {
                    // when comparing Investigations with Requests
                    // there is no way to determine whether Requests must be present
                    continue;
                }
                if (
                    strpos($metric1, '_Item_') !== false &&
                    strpos($metric2, '_Title_') !== false &&
                    $dataType !== 'Book'
                ) {
                    // when comparing an Item metric with a Title metric (both Investigations or both Requests)
                    // there is no way to determine whether the Title metric must be present
                    // unless we know that the item is a Book
                    continue;
                }
                $message = "{$metric2} is missing while {$metric1} is present";
                $this->addCriticalError($message, $message, $position, $dataWithDate);
                $this->setUnusable();
            } elseif ($metricCounts[$metric1] < $metricCounts[$metric2]) {
                $message = "Less {$metric1} than {$metric2}";
                $this->addCriticalError(
                    $message,
                    $message,
                    $position,
                    $dataWithDate . ", {$metricCounts[$metric1]} {$metric1} < {$metricCounts[$metric2]} {$metric2}"
                );
                $this->setUnusable();
            }
        }
    }

    protected function cleanupAndStorePerformance(): void
    {
        if ($this->items === null) {
            throw new \LogicException("ReporItem already cleaned up");
        }

        foreach ($this->items as $reportItem) {
            $reportItem->cleanupPerformance();
            if (($itemComponentList = $reportItem->get('Item_Component')) !== null) {
                $itemComponentList->cleanupAndStorePerformance();
            }
            $this->setIndex($reportItem->getFirstReportItemPosition());
            $this->setData('.', $reportItem);
        }

        $this->items = null;
    }
}
