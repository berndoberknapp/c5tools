<?php

/**
 * TabularItemParent handles tabular COUNTER R5 Item Parents
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

use ubfr\c5tools\traits\TabularParsers;

class TabularItemParent extends ItemParent
{
    use TabularParsers;

    protected function parseDocument(): void
    {
        $this->setParsing();

        foreach ($this->document as $property => $value) {
            $this->setData($property, $value);
        }
        $this->createItemId();

        $this->setParsed();

        $this->checkRequiredElements();

        $this->document = null;
    }

    protected function checkRequiredElements(): void
    {
        if ($this->get('Item_ID') === null) {
            $message = 'No (valid) Parent identifier present';
            $data = $this->formatData('Item', $this->reportItem->get('Item') ?? '');
            $hint = "at least one identifier must be provided for each Parent";
            $this->addCriticalError($message, $message, $this->position, $data, $hint);
            if (! isset($this->data['Item_Name'])) {
                // Item_ID is required, but the parent is only unusable when Item_ID and Item_Name are missing
                $this->setUnusable();
            }
        }

        $property = 'Parent_Data_Type';
        $reportElements = $this->reportHeader->getReportElements();
        if (isset($reportElements[$property]) && $this->get('Data_Type') === null) {
            $message = "{$property} value must not be empty";
            $columnName = $this->reportHeader->getColumnForColumnHeading($property);
            $position = "{$columnName}{$this->position}";
            $data = $this->formatData($property, '');
            $this->addCriticalError($message, $message, $position, $data);
            $this->setUnusable();
        }
    }
}
