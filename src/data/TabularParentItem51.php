<?php

/**
 * TabularParentItem51 handles tabular COUNTER R5.1 Parent Item list entries
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

use ubfr\c5tools\traits\TabularParsers;

class TabularParentItem51 extends JsonParentItem51
{
    use TabularParsers;

    protected function parseDocument(): void
    {
        $this->setParsing();

        foreach ($this->document as $property => $value) {
            $this->setData($property, $value);
        }
        $this->createItemId51();

        $this->setParsed();

        $this->checkRequiredElements();

        $this->document = null;
    }
}
