<?php

/**
 * TabularAttributePerformance51 handles tabular COUNTER R5.1 Attribute Performance list entries
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

class TabularAttributePerformance51 extends AttributePerformance51
{
    protected function parseDocument(): void
    {
        $this->setParsing();

        foreach ($this->document as $property => $value) {
            if ($property === 'Performance') {
                $this->performance = $value;
                $this->metricTypeRows[array_key_first($this->performance)] = $this->position;
            } else {
                $this->setData($property, $value);
            }
        }

        $this->setParsed();

        $this->checkRequiredElements($this->getRequiredElements());
        if ($this->context === 'item') {
            $this->checkItemWithComponentsMetricTypes();
            $this->checkPlatformDatabaseMetricTypes();
            $this->checkDataTypeUniqueTitleMetrics();
        } else {
            $this->checkComponentMetricTypes();
        }

        $this->document = null;
    }
}
