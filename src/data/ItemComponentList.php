<?php

/**
 * ItemComponentList is the abstract base class for handling COUNTER R5 Component Item lists
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

abstract class ItemComponentList implements \ubfr\c5tools\interfaces\CheckedDocument, \Countable, \IteratorAggregate
{
    use CheckedDocument;
    use Parsers;
    use Checks;
    use Helpers;
    use MetricTypesPresent;

    protected ReportHeader $reportHeader;

    protected ReportItem $reportItem;

    protected ?array $components;

    abstract protected function parseDocument(): void;

    public function __construct(\ubfr\c5tools\interfaces\CheckedDocument $parent, string $position, $document)
    {
        $this->document = $document;
        $this->checkResult = $parent->getCheckResult();
        $this->config = $parent->getConfig();
        $this->format = $parent->getFormat();
        $this->position = $position;

        $this->reportHeader = $parent->getReportHeader();
        $this->reportItem = $parent;

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

    public function getReportItem(): ReportItem
    {
        return $this->reportItem;
    }

    // TODO: is a special asJson method necessary?
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

    public function getPerformanceByDate(): array
    {
        if ($this->components === null) {
            throw new \LogicException("ItemComponent already cleaned up");
        }

        $aggregatedPerformance = [];
        foreach ($this->components as $itemComponent) {
            $this->aggregatePerformance($aggregatedPerformance, $itemComponent->getPerformanceByDate());
        }

        return $aggregatedPerformance;
    }

    public function getMetricTypes(): array
    {
        $metricTypes = [];
        foreach (($this->components ?? $this->get('.')) as $itemComponent) {
            $metricTypes = array_merge($metricTypes, $itemComponent->getMetricTypes());
        }

        return $metricTypes;
    }

    public function merge(ItemComponentList $itemComponentList): void
    {
        if ($this->components === null) {
            throw new \LogicException("ItemComponentList already cleaned up");
        }

        foreach ($itemComponentList->components as $hash => $itemComponent) {
            if (isset($this->components[$hash])) {
                $this->components[$hash]->merge($itemComponent);
            } else {
                $this->components[$hash] = $itemComponent;
            }
        }

        $this->addMetricTypesPresent($itemComponentList->getMetricTypesPresent());
    }

    // TODO: identical with ReportItemList::aggregatePerformance, move to trait?
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

    public function cleanupAndStorePerformance(): void
    {
        if ($this->components === null) {
            throw new \LogicException("ItemComponentList already cleaned up");
        }

        foreach ($this->components as $itemComponent) {
            $itemComponent->cleanupPerformance();
            if (! empty($itemComponent->get('Performance'))) {
                $this->setIndex($itemComponent->getFirstItemComponentPosition());
                $this->setData('.', $itemComponent);
            }
        }

        $this->components = null;
    }
}
