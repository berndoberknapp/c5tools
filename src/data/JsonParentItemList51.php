<?php

/**
 * JsonParentItemList51 is the main class for parsing and validating JSON COUNTER R5.1 Parent Item lists
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

use ubfr\c5tools\traits\MetricTypesPresent;

class JsonParentItemList51 extends JsonReportItemList51
{
    use MetricTypesPresent;

    // TODO: base class with hashedItems instead of items/parents/components?
    protected function parseDocument(): void
    {
        $this->setParsing();

        // json is an array, already checked in JsonReport::parseDocument
        $hasUnusableParentItems = false;
        foreach ($this->document as $index => $parentItemJson) {
            $this->setIndex($index);
            $position = "{$this->position}[{$index}]";
            if (! $this->isArrayValueObject($position, '.', $parentItemJson)) {
                continue;
            }
            $parentItem = new JsonParentItem51($this, $position, $index, $parentItemJson);
            if ($parentItem->isUsable()) {
                $hash = $parentItem->getHash();
                if (isset($this->parents[$hash])) {
                    $message = 'Multiple Parent_Item objects for the same parent';
                    $data = 'Parent_Item (first occurence at ' . $this->parents[$hash]->getPosition() . ')';
                    $hint = 'all Items with the same parent must be in a single Parent_Item object';
                    $this->addError($message, $message, $position, $data, $hint);

                    $this->parents[$hash]->merge($parentItem);
                    if ($parentItem->isUsable()) {
                        $this->setFixed('.', $parentItemJson);
                    } else {
                        $this->setInvalid('.', $parentItemJson);
                        $this->setUnusable();
                        $hasUnusableParentItems = true;
                    }
                } else {
                    $this->parents[$hash] = $parentItem;
                    if ($parentItem->isFixed()) {
                        $this->setFixed('.', $parentItemJson);
                    }
                }
            } else {
                $this->setInvalid('.', $parentItemJson);
                $this->setUnusable();
                $hasUnusableParentItems = true;
            }
            $this->addMetricTypesPresent($parentItem->getMetricTypesPresent());
        }
        if ($hasUnusableParentItems) {
            $message = 'Due to errors in Report_Items some checks were skipped';
            $position = '.Report_Items';
            $data = 'Report_Items';
            $this->addNotice($message, $message, $position, $data);
        }

        $this->setParsed();

        $this->checkItemsParent();
        // TODO: check Items for uniqueness across Parents

        $this->storeData();

        $this->document = null;
    }

    public function storeData(): void
    {
        if ($this->parents === null) {
            throw new \LogicException("ParentItemList already cleaned up");
        }

        foreach ($this->parents as $parentItem) {
            $parentItem->storeData();
            $this->setIndex($parentItem->getIndexFromPosition());
            if ($parentItem->isUsable()) {
                $this->setData('.', $parentItem);
            } else {
                $this->setUnusable();
            }
        }
        $this->setIndex(null);

        $this->parents = null;
    }
}
