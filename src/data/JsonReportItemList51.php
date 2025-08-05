<?php

/**
 * JsonReportItemList51 is the main class for parsing and validating JSON COUNTER R5.1 Report Item lists
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

class JsonReportItemList51 extends JsonReportItemList // TODO: base class
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
            $reportItem = new JsonReportItem51($this, $position, $index, $reportItemJson);
            if ($reportItem->isUsable()) {
                $hash = $reportItem->getHash();
                if (isset($this->items[$hash])) {
                    $message = 'Multiple Report_Item objects for the same Attribute_Performance';
                    $data = 'Report_Item (first occurence at ' . $this->items[$hash]->getPosition() . ')';
                    $hint = 'all Attribute_Performance for the same item must be in a single Report_Item object';
                    $this->addError($message, $message, $position, $data, $hint);

                    $this->items[$hash]->merge($reportItem);
                    if ($reportItem->isUsable()) {
                        $this->setFixed('.', $reportItemJson);
                    } else {
                        $this->setInvalid('.', $reportItemJson);
                        $this->setUnusable();
                        $hasUnusableReportItems = true;
                    }
                } else {
                    $this->items[$hash] = $reportItem;
                    if ($reportItem->isFixed()) {
                        $this->setFixed('.', $reportItemJson);
                    }
                }
            } else {
                $this->setInvalid('.', $reportItemJson);
                $this->setUnusable();
                $hasUnusableReportItems = true;
            }
            $this->addMetricTypesPresent($reportItem->getMetricTypesPresent());
        }
        if ($hasUnusableReportItems && ! $this->config->isItemReport($this->reportHeader->getReportId())) {
            $message = 'Due to errors in Report_Items some checks were skipped';
            $position = '.Report_Items';
            $data = 'Report_Items';
            $this->addNotice($message, $message, $position, $data);
        }

        $this->setParsed();

        // TODO: enforce filters - Author, Database, Item_ID, Country_Code, Subdivision_Code

        if (! $this->config->isItemReport($this->reportHeader->getReportId())) {
            // only call these methods there are no parents, otherwise this is done by the parent
            $this->checkDataTypes();
            $this->checkMetricRelations();
            $this->storeData();
        }

        $this->document = null;
    }

    // TODO: same method on TabularParentReportItemList51
    public function checkParent(bool $parentIsNoParent, ?string $parentDataType): void
    {
        if ($this->items === null) {
            throw new \LogicException("ReportItemList51 already cleaned up");
        }

        foreach ($this->items as $reportItem) {
            $reportItem->checkParent($parentIsNoParent, $parentDataType);
        }
    }

    public function checkDataTypes(): void
    {
        if ($this->items === null) {
            throw new \LogicException("ReportItemList51 already cleaned up");
        }

        foreach ($this->items as $reportItem) {
            $reportItem->checkDataTypes();
        }
    }

    public function checkMetricRelations(): void
    {
        if ($this->items === null) {
            throw new \LogicException("ReportItemList51 already cleaned up");
        }

        foreach ($this->items as $reportItem) {
            $reportItem->checkMetricRelations();
        }
    }

    public function merge(ReportItemList $reportItemList): void // TODO: base class
    {
        if ($this->items === null) {
            throw new \LogicException("ReportItemList51 already cleaned up");
        }

        foreach ($reportItemList->items as $hash => $reportItem) {
            if (isset($this->items[$hash])) {
                $this->items[$hash]->merge($reportItem);
            } else {
                $this->items[$hash] = $reportItem;
            }
        }

        $this->addMetricTypesPresent($reportItemList->getMetricTypesPresent());
    }

    public function storeData(): void
    {
        if ($this->items === null) {
            throw new \LogicException("ReportItemList already cleaned up");
        }

        foreach ($this->items as $reportItem) {
            $reportItem->storeData();
            $this->setIndex($reportItem->getIndexFromPosition());
            if ($reportItem->isUsable()) {
                $this->setData('.', $reportItem);
            } else {
                $this->setUnusable();
            }
        }
        $this->setIndex(null);

        $this->items = null;
    }
}
