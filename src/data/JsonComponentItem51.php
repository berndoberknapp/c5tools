<?php

/**
 * JsonComponentItem51 handles JSON COUNTER R5.1 Component Item list entries
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

class JsonComponentItem51 extends ComponentItem51
{
    protected function parseDocument(): void
    {
        $this->setParsing();

        $elements = $this->reportHeader->getJsonComponentElements();
        $requiredElements = [];
        $optionalElements = [];
        foreach ($elements as $elementName => $elementConfig) {
            if ($elementConfig['attribute']) {
                continue;
            }
            if ($elementConfig['required']) {
                $requiredElements[] = $elementName;
            } else {
                $optionalElements[] = $elementName;
            }
        }

        $properties = $this->getObjectProperties(
            $this->position,
            'Components',
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

    // TODO: same function in JsonReportItem51
    protected function parseAttributePerformance(string $position, string $property, $value, string $context): void
    {
        if (! $this->isNonEmptyArray($position, $property, $value, false)) {
            return;
        }

        $attributePerformanceList = new JsonAttributePerformanceList51($this, $position, $value, $context);
        if ($attributePerformanceList->isUsable()) {
            $this->setData($property, $attributePerformanceList);
            if ($attributePerformanceList->isFixed()) {
                $this->setFixed($property, $value);
            }
        } else {
            $this->setInvalid($property, $value);
            $this->setUnusable();
        }
        $this->addMetricTypesPresent($attributePerformanceList->getMetricTypesPresent());
    }

    protected function parseComponentAttributePerformance(string $position, string $property, $value): void
    {
        $this->parseAttributePerformance($position, $property, $value, 'component');
    }

    protected function checkRequiredElements(): void
    {
        if ($this->get('Item_ID') === null && $this->get('Item_Name') === null) {
            // Item_ID is required, but the parent is only unusable when Item_ID and Item_Name are missing
            $this->setUnusable();
            return;
        }

        if ($this->get('Attribute_Performance') === null) {
            $this->setUnusable();
            return;
        }
    }
}
