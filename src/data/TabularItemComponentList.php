<?php

/**
 * TabularItemComponentList handles tabular COUNTER R5 Item Component lists
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

class TabularItemComponentList extends ItemComponentList
{
    protected function parseDocument(): void
    {
        $this->setParsing();

        $this->setIndex(0);
        $itemComponent = new TabularItemComponent($this, $this->position, 0, $this->document);
        if ($itemComponent->isUsable()) {
            $hash = $itemComponent->getHash();
            $this->components[$hash] = $itemComponent;
            if ($itemComponent->isFixed()) {
                $this->setFixed('.', $this->document);
            }
        } else {
            $this->setInvalid('.', $this->document);
            $this->setUnusable();
        }

        $this->setParsed();

        $this->document = null;
    }
}
