<?php

/**
 * ComponentItemList51 is the abstract base class for handling COUNTER R5.1 Component Item lists
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

abstract class ComponentItemList51 extends BaseItemList51
{
    protected ?array $components = [];

    public function aggregatePerformance(array &$aggregatedPerformance): void
    {
        if ($this->components === null) {
            throw new \LogicException("ComponentItemList51 already cleaned up");
        }

        foreach ($this->components as $component) {
            $component->aggregatePerformance($aggregatedPerformance);
        }
    }

    public function merge(ComponentItemList51 $componentItemList): void
    {
        if ($this->components === null) {
            throw new \LogicException("ComponentItemList51 already cleaned up");
        }

        foreach ($componentItemList->components as $hash => $componentItem) {
            if (isset($this->components[$hash])) {
                $this->components[$hash]->merge($componentItem);
            } else {
                $this->components[$hash] = $componentItem;
            }
        }

        $this->addMetricTypesPresent($componentItemList->getMetricTypesPresent());
    }

    public function storeData(): void
    {
        if ($this->components === null) {
            throw new \LogicException("ComponentItemList already cleaned up");
        }

        foreach ($this->components as $componentItem) {
            $componentItem->storeData();
            $this->setIndex($componentItem->getIndexFromPosition());
            if ($componentItem->isUsable()) {
                $this->setData('.', $componentItem);
            } else {
                $this->setUnusable();
            }
        }
        $this->setIndex(null);

        $this->components = null;
    }
}
