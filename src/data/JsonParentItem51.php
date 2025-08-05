<?php

/**
 * JsonParentItem51 handles JSON COUNTER R5.1 Parent Item list entries
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

use ubfr\c5tools\traits\MetricTypesPresent;

class JsonParentItem51 extends JsonReportItem51 // TODO: what is the appropriate parent class?
{
    use MetricTypesPresent;

    protected function parseDocument(): void
    {
        $this->setParsing();

        $elements = $this->reportHeader->getJsonParentElements();

        $properties = array_keys(get_object_vars($this->document));
        $isNoParent = (count($properties) === 1 && $this->inArrayFuzzy('Items', $properties));
        $requiredElements = [];
        $optionalElements = [];
        foreach ($elements as $elementName => $elementConfig) {
            if ($elementConfig['required'] || ($elementName === 'Item_ID' && ! $isNoParent)) {
                $requiredElements[] = $elementName;
            } else {
                $optionalElements[] = $elementName;
            }
        }

        $properties = $this->getObjectProperties(
            $this->position,
            'Report_Item',
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

    protected function parseItemList(string $position, string $property, $value): void
    {
        if (! $this->isNonEmptyArray($position, $property, $value, false)) {
            return;
        }

        $itemList = new JsonReportItemList51($this, $position, $value);
        if ($itemList->isUsable()) {
            $this->setData($property, $itemList);
            if ($itemList->isFixed()) {
                $this->setFixed($property, $value);
            }
        } else {
            $this->setInvalid($property, $value);
            $this->setUnusable();
        }
        $this->addMetricTypesPresent($itemList->getMetricTypesPresent());
    }

    protected function checkRequiredElements(): void
    {
        if (
            (count($this->getData()) === 1 && $this->getData('Items') !== null) ||
            (empty($this->getData()) && $this->getInvalid('Items'))
        ) {
            // no parent case - nothing but items
            return;
        }

        // otherwise parent requires Item_ID and Data_Type
        if ($this->get('Item_ID') === null && $this->getInvalid('Item_ID') === null) {
            if ($this->isJson()) {
                $message = "Required property 'Item_ID' is missing";
            } else {
                $message = 'No (valid) Parent identifier present';
            }
            $data = $this->formatData('Parent', $this->get('Title') ?? '');
            $hint = "at least one identifier must be provided for each Parent";
            $this->addCriticalError($message, $message, $this->position, $data, $hint);
            if ($this->get('Title')) {
                // a missing Item_ID is acceptable if a Title is present
                $this->setUnusable();
            }
        }
        if (
            isset($this->reportHeader->getJsonParentElements()['Data_Type']) && $this->get('Data_Type') === null &&
            $this->getInvalid('Data_Type') === null
        ) {
            if ($this->isJson()) {
                $message = "Required property 'Data_Type' is missing";
            } else {
                $message = 'Parent_Data_Type value must not be empty';
            }
            $this->addCriticalError($message, $message, $this->position, 'Report_Item');
            $this->setUnusable();
        }
    }

    public function getHash(): string
    {
        // hash everything but Items
        // TODO: Should Data_Type be excluded?
        $data = $this->getData();
        if (isset($data['Items'])) {
            unset($data['Items']);
        }

        $hashContext = hash_init('sha256');
        $this->updateHash($hashContext, $data, false, false);
        return hash_final($hashContext);
    }

    public function merge(ReportItem $parentItem): void // TODO: base class?
    {
        if ($parentItem->get('Items') !== null) {
            if ($this->get('Items') !== null) {
                $this->get('Items')->merge($parentItem->get('Items'));
                if (! $parentItem->get('Items')->isUsable()) {
                    $parentItem->setUnusable();
                }
            } else {
                $this->setData('Items', $parentItem->get('Items'));
            }

            $this->addMetricTypesPresent($parentItem->getMetricTypesPresent());
        }
    }

    public function storeData(): void
    {
        if (($itemList = $this->get('Items')) !== null) {
            $itemList->storeData();
            if (! $itemList->isUsable()) {
                $this->setUnusable();
            }
        }
    }
}
