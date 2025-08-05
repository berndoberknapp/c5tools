<?php

/**
 * TabularAttributePerformanceList51 handles tabular COUNTER R5.1 Attribute Performance lists
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

class TabularAttributePerformanceList51 extends AttributePerformanceList51
{
    protected string $context;

    public function __construct(
        \ubfr\c5tools\interfaces\CheckedDocument $parent,
        string $position,
        $document,
        string $context,
        array $metricTypesPresent
    ) {
        parent::__construct($parent, $position, $document);

        $this->context = $context;
        $this->metricTypesPresent = $metricTypesPresent;
    }

    protected function parseDocument(): void
    {
        $this->setParsing();

        $this->setIndex(0);
        $attributePerformance = new TabularAttributePerformance51(
            $this,
            $this->position,
            0,
            $this->document,
            $this->context
        );
        if ($attributePerformance->isUsable()) {
            $hash = $attributePerformance->getHash();
            $this->attributePerformances[$hash] = $attributePerformance;
            if ($attributePerformance->isFixed()) {
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
