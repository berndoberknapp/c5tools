<?php

/**
 * JsonAttributePerformanceList51 handles JSON COUNTER R5.1 Attribute Performance lists
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

class JsonAttributePerformanceList51 extends AttributePerformanceList51
{
    protected string $context;

    public function __construct(
        \ubfr\c5tools\interfaces\CheckedDocument $parent,
        string $position,
        $document,
        string $context
    ) {
        parent::__construct($parent, $position, $document);

        $this->context = $context;
    }

    protected function parseDocument(): void
    {
        $this->setParsing();

        // json is an array, already checked in JsonReport::parseDocument
        foreach ($this->document as $index => $attributePerformanceJson) {
            $this->setIndex($index);
            $position = "{$this->position}[{$index}]";
            if (! $this->isArrayValueObject($position, '.', $attributePerformanceJson)) {
                continue;
            }
            $attributePerformance = new JsonAttributePerformance51(
                $this,
                $position,
                $index,
                $attributePerformanceJson,
                $this->context
            );
            if ($attributePerformance->isUsable()) {
                $hash = $attributePerformance->getHash();
                if (isset($this->attributePerformances[$hash])) {
                    $message = 'Multiple Attribute_Performance objects for the same attribute set';
                    $data = 'Attribute_Performance (first occurence at ' .
                        $this->attributePerformances[$hash]->getPosition() . ')';
                    $hint = 'all Performance for the same attribute set must be in a single Attribute_Performance object';
                    $this->addError($message, $message, $position, $data, $hint);

                    $this->attributePerformances[$hash]->merge($attributePerformance);
                    if ($attributePerformance->isUsable()) {
                        $this->setFixed('.', $attributePerformanceJson);
                    } else {
                        $this->setInvalid('.', $attributePerformanceJson);
                        $this->setUnusable();
                    }
                } else {
                    $this->attributePerformances[$hash] = $attributePerformance;
                    if ($attributePerformance->isFixed()) {
                        $this->setFixed('.', $attributePerformanceJson);
                    }
                }
            } else {
                $this->setInvalid('.', $attributePerformanceJson);
                $this->setUnusable();
            }
            $this->addMetricTypesPresent($attributePerformance->getMetricTypesPresent());
        }

        $this->setParsed();

        $this->document = null;
    }
}
