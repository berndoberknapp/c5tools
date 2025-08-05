<?php

/**
 * ReportItemList51 is the abstract base class for handling tabular COUNTER R5.1 Report Item lists
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

abstract class ReportItemList51 extends BaseItemList51
{
    protected ?array $items = [];

    public function getItemForHash(string $hash): ?TabularReportItem51
    {
        if ($this->items === null) {
            throw new \LogicException("ReportItemList51 already cleaned up");
        }

        return ($this->items[$hash] ?? null);
    }

    public function merge(ReportItemList51 $reportItemList): void
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
}
