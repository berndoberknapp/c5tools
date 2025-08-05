<?php

/**
 * JsonReportItem51 handles JSON COUNTER R5.1 Report Item list entries
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

use ubfr\c5tools\traits\MetricTypesPresent;

class JsonReportItem51 extends JsonReportItem // TODO: ReportItem51 as base class
{
    use MetricTypesPresent;

    protected function parseDocument(): void
    {
        $this->setParsing();

        $elements = $this->reportHeader->getReportElements();
        $requiredElements = $this->reportHeader->getJsonItemRequiredElements();
        $optionalElements = $this->reportHeader->getJsonItemOptionalElements();

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

        $this->checkItemNameAndIdentifiers();
        $this->checkPublisher();

        // TODO: Item included in method name to avoid incompatible redeclaration of ReportItem::checkRequiredElements
        $this->checkRequiredItemElements($requiredElements);

        $this->document = null;
    }

    // TODO: same function in JsonComponentItem51
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

    protected function parseItemAttributePerformance(string $position, string $property, $value): void
    {
        $this->parseAttributePerformance($position, $property, $value, 'item');
    }

    protected function checkRequiredItemElements(array $requiredElements): void
    {
        foreach ($requiredElements as $requiredElement) {
            if ($this->get($requiredElement) === null) {
                $this->setUnusable();
                return;
            }
        }
    }

    public function checkParent(bool $parentIsNoParent, ?string $parentDataType): void
    {
        if ($this->reportHeader->getReportId() === 'IR_A1') {
            // IR_A1 has no Data_Types, so the only thing that can be checked is whether parent details are present
            if ($parentIsNoParent) {
                $message = 'Parent details are missing';
                $position = $this->position;
                $data = $this->formatData('Item', $this->get('Item') ?? '');
                $hint = 'every Item in IR_A1 should have Parent details';
                $this->addWarning($message, $message, $position, $data, $hint);
            }
        } elseif ($this->reportHeader->includesParentDetails()) {
            // more detailed checks base on Data_Type for IR with parent details
            $this->get('Attribute_Performance')->checkParent($parentIsNoParent, $parentDataType);
        }
        // IR_M1 has no parent details, so there is nothing to check
    }

    public function checkDataTypes(): void
    {
        $this->get('Attribute_Performance')->checkDataTypes();
    }

    public function checkMetricRelations(): void
    {
        $this->get('Attribute_Performance')->checkMetricRelations();
    }

    public function getHash(): string
    {
        $hashContext = hash_init('sha256');
        $this->updateHash($hashContext, $this->getJsonMetadata(), false, false);
        return hash_final($hashContext);
    }

    // TODO: similar function in ComponentItem51
    public function merge(ReportItem $reportItem): void // TODO: ReportItem51
    {
        if ($reportItem->get('Attribute_Performance') !== null) {
            if ($this->get('Attribute_Performance') !== null) {
                $this->get('Attribute_Performance')->merge($reportItem->get('Attribute_Performance'));
                if (! $reportItem->get('Attribute_Performance')->isUsable()) {
                    $reportItem->setUnusable();
                }
            } else {
                $this->setData('Attribute_Performance', $reportItem->get('Attribute_Performance'));
            }

            $this->addMetricTypesPresent($reportItem->getMetricTypesPresent());
        }
    }

    // TODO: same function in JsonComponentItem51
    public function storeData(): void
    {
        if (($attributePerformanceList = $this->get('Attribute_Performance')) !== null) {
            $attributePerformanceList->storeData();
            if (! $attributePerformanceList->isUsable()) {
                $this->setUnusable();
            }
        }
    }
}
