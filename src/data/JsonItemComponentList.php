<?php

/**
 * JsonItemComponentList handles JSON COUNTER R5 Item Component lists
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

class JsonItemComponentList extends ItemComponentList
{
    protected function parseDocument(): void
    {
        $this->setParsing();

        // json is an array, already checked in JsonReport::parseDocument
        foreach ($this->document as $index => $itemComponentJson) {
            $this->setIndex($index);
            $position = "{$this->position}[{$index}]";
            if (! $this->isArrayValueObject($position, '.', $itemComponentJson)) {
                continue;
            }
            $itemComponent = new JsonItemComponent($this, $position, $index, $itemComponentJson);
            if ($itemComponent->isUsable()) {
                $hash = $itemComponent->getHash();
                if (isset($this->components[$hash])) {
                    $this->components[$hash]->merge($itemComponent);
                    if ($this->isJson() && $itemComponent->isUsable()) {
                        $this->setFixed('.', $itemComponentJson);
                    }
                } else {
                    $this->components[$hash] = $itemComponent;
                    if ($itemComponent->isFixed()) {
                        $this->setFixed('.', $itemComponentJson);
                    }
                }
            } else {
                $this->setInvalid('.', $itemComponentJson);
            }
            $this->addMetricTypesPresent($itemComponent->getMetricTypesPresent());
            if (! $itemComponent->isUsable()) {
                $this->setUnusable();
            }
        }

        $this->setParsed();

        $this->document = null;
    }
}
