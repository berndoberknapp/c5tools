<?php

/**
 * TabularComponentItemList51 handles tabular COUNTER R5.1 Component Item lists
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

class TabularComponentItemList51 extends ComponentItemList51
{
    protected function parseDocument(): void
    {
        $this->setParsing();

        $this->setIndex(0);
        $componentItem = new TabularComponentItem51($this, $this->position, 0, $this->document);
        if ($componentItem->isUsable()) {
            $hash = $componentItem->getHash();
            $this->components[$hash] = $componentItem;
            if ($componentItem->isFixed()) {
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
