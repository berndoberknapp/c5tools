<?php

/**
 * JsonComponentItemList51 handles JSON COUNTER R5.1 Component Item lists
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

class JsonComponentItemList51 extends ComponentItemList51
{
    protected function parseDocument(): void
    {
        $this->setParsing();

        // json is an array, already checked in JsonAttributePerformance51::parseDocument
        foreach ($this->document as $index => $componentItemJson) {
            $this->setIndex($index);
            $position = "{$this->position}[{$index}]";
            if (! $this->isArrayValueObject($position, '.', $componentItemJson)) {
                continue;
            }
            $componentItem = new JsonComponentItem51($this, $position, $index, $componentItemJson);
            if ($componentItem->isUsable()) {
                $hash = $componentItem->getHash();
                if (isset($this->components[$hash])) {
                    $message = 'Multiple Component_Item objects for the same component';
                    $data = 'Component_Item (first occurence at ' . $this->components[$hash]->getPosition() . ')';
                    $hint = 'all Attribute_Performance for the same component must be in a single Component_Item object';
                    $this->addError($message, $message, $position, $data, $hint);

                    $this->components[$hash]->merge($componentItem);
                    if ($componentItem->isUsable()) {
                        $this->setFixed('.', $componentItemJson);
                    } else {
                        $this->setInvalid('.', $componentItemJson);
                        $this->setUnusable();
                    }
                } else {
                    $this->components[$hash] = $componentItem;
                    if ($componentItem->isFixed()) {
                        $this->setFixed('.', $componentItemJson);
                    }
                }
            } else {
                $this->setInvalid('.', $componentItemJson);
                $this->setUnusable();
            }
            $this->addMetricTypesPresent($componentItem->getMetricTypesPresent());
        }

        $this->setParsed();

        if (! empty($this->components)) {
            // $this->checkItemMetrics();
            // $this->checkTitleMetrics();
            // check Items for uniqueness across Parents
        }

        $this->document = null;
    }
}
