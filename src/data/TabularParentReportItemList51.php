<?php

/**
 * TabularParentReportItemList51 handles tabular COUNTER R5.1 Parent Item lists
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

class TabularParentReportItemList51 extends ReportItemList51
{
    protected function parseDocument(): void
    {
        $this->setParsing();

        $this->setIndex(0);
        $reportItem = $this->document;
        if ($reportItem->isUsable()) {
            $hash = $reportItem->getHash();
            $this->items[$hash] = $reportItem;
            if ($reportItem->isFixed()) {
                $this->setFixed('.', $this->document);
            }
        } else {
            $this->setInvalid('.', $this->document);
            $this->setUnusable();
        }

        $this->setParsed();

        $this->document = null;
    }

    // TODO: same methods in JsonReportItemList51
    public function checkDataTypes(): void
    {
        if ($this->items === null) {
            throw new \LogicException("TabularParentReportItemList51 already cleaned up");
        }

        foreach ($this->items as $reportItem) {
            $reportItem->checkDataTypes();
        }
    }

    // TODO: same methods in JsonReportItemList51
    public function checkMetricRelations(): void
    {
        if ($this->items === null) {
            throw new \LogicException("TabularParentReportItemList51 already cleaned up");
        }

        foreach ($this->items as $reportItem) {
            $reportItem->checkMetricRelations();
        }
    }

    public function merge(ReportItemList51 $reportItemList): void // TODO: base class
    {
        if ($this->items === null) {
            throw new \LogicException("TabularParentReportItemList51 already cleaned up");
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

    // TODO: same method on JsonReportItemList51
    public function checkParent(bool $parentIsNoParent, ?string $parentDataType): void
    {
        if ($this->items === null) {
            throw new \LogicException("ReportItemList51 already cleaned up");
        }

        foreach ($this->items as $reportItem) {
            $reportItem->checkParent($parentIsNoParent, $parentDataType);
        }
    }

    public function storeData(): void
    {
        if ($this->items === null) {
            throw new \LogicException("TabularParentReportItemList already cleaned up");
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
