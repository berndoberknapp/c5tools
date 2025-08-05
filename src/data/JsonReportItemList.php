<?php

/**
 * JsonReportItemList is the main class for parsing and validating JSON COUNTER R5 Report Item lists
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

class JsonReportItemList extends ReportItemList
{
    protected function parseDocument(): void
    {
        $this->setParsing();

        // json is an array, already checked in JsonReport::parseDocument
        $hasUnusableReportItems = false;
        foreach ($this->document as $index => $reportItemJson) {
            $this->setIndex($index);
            $position = "{$this->position}[{$index}]";
            if (! $this->isArrayValueObject($position, '.', $reportItemJson)) {
                continue;
            }
            $reportItem = new JsonReportItem($this, $position, $index, $reportItemJson);
            if ($reportItem->isUsable()) {
                $fullHash = $reportItem->getFullHash();
                if (isset($this->items[$fullHash])) {
                    $this->items[$fullHash]->merge($reportItem);
                    if ($reportItem->isUsable()) {
                        $this->setFixed('.', $reportItemJson);
                    }
                } else {
                    $this->items[$fullHash] = $reportItem;
                    if ($reportItem->isFixed()) {
                        $this->setFixed('.', $reportItemJson);
                    }
                }
            } else {
                $this->setInvalid('.', $reportItemJson);
            }
            $this->addMetricTypesPresent($reportItem->getMetricTypesPresent());

            if (! $reportItem->isUsable()) {
                $this->setUnusable();
                $hasUnusableReportItems = true;
            }
        }
        if ($hasUnusableReportItems) {
            $message = 'Due to errors in Report_Items some checks were skipped';
            $position = '.Report_Items';
            $data = 'Report_Items';
            $this->addNotice($message, $message, $position, $data);
        }

        $this->setParsed();

        // TODO: check filters - Database, Item_ID, Item_Contributor
        if (! empty($this->items)) {
            $this->checkItemMetrics();
            $this->checkTitleMetrics();
        }

        $this->cleanupAndStorePerformance();

        $this->document = null;
    }

    // TODO: wrong class, use trait for method instead?
    public function checkItemsParent(): void
    {
        if ($this->parents === null) {
            throw new \LogicException("ParentItemList already cleaned up");
        }

        foreach ($this->parents as $parentItem) {
            if ($parentItem->get('Items') === null) {
                continue;
            }
            $parentIsNoParent = (count($parentItem->getData()) === 1);
            $parentItem->get('Items')->checkParent(
                $parentIsNoParent,
                $parentItem->get('Data_Type') ?? $parentItem->getInvalid('Data_Type')
            );
        }
    }
}
