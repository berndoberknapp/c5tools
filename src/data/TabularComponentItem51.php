<?php

/**
 * TabularComponentItem51 handles tabular COUNTER R5.1 Component Item list entries
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

use ubfr\c5tools\traits\TabularParsers;

class TabularComponentItem51 extends ComponentItem51
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

    protected function checkRequiredElements(): void
    {
        if ($this->get('Item_ID') === null) {
            $message = 'No (valid) Component identifier present';
            $data = $this->formatData('Component', $this->get('Item') ?? '');
            $hint = "at least one identifier must be provided for each Component";
            $this->addCriticalError($message, $message, $this->position, $data, $hint);
            if (! isset($this->data['Item'])) {
                // Item_ID is required, but the component is only unusable when Item_ID and Item are missing
                $this->setUnusable();
            }
        }
    }
}
