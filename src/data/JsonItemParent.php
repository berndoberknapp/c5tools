<?php

/**
 * JsonItemParent handles JSON COUNTER R5 Item Parents
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

class JsonItemParent extends ItemParent
{
    protected function parseDocument(): void
    {
        $this->setParsing();

        $elements = $this->reportHeader->getJsonParentElements();
        $requiredElements = [];
        $optionalElements = [];
        foreach ($elements as $elementName => $elementConfig) {
            if ($elementConfig['required']) {
                $requiredElements[] = $elementName;
            } else {
                $optionalElements[] = $elementName;
            }
        }

        $properties = $this->getObjectProperties(
            $this->position,
            'Item_Parent',
            $this->document,
            $requiredElements,
            $optionalElements
        );
        foreach ($properties as $property => $value) {
            $position = "{$this->position}.{$property}";
            if (isset($elements[$property]['check'])) {
                $checkMethod = $elements[$property]['check'];
                $value = $this->$checkMethod($position, $property, $value);
                if ($value !== null) {
                    $this->setData($property, $value);
                }
            } else {
                $parseMethod = $elements[$property]['parse'];
                $this->$parseMethod($position, $property, $value);
            }
        }

        $this->setParsed();

        $this->checkRequiredElements();

        $this->document = null;
    }

    protected function checkRequiredElements(): void
    {
        if ($this->get('Item_ID') === null && $this->get('Item_Name') === null) {
            // Item_ID is required, but the parent is only unusable when Item_ID and Item_Name are missing
            $this->setUnusable();
            return;
        }
    }
}
