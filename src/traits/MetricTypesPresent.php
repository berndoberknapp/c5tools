<?php

/**
 * MetricTypesPresent is used for compiling a list of Metric_Types used in a COUNTER Report or Standard View
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\traits;

trait MetricTypesPresent
{
    protected array $metricTypesPresent = [];

    public function getMetricTypesPresent(): array
    {
        if (! $this->isParsed()) {
            $this->parseDocument();
        }

        return $this->metricTypesPresent;
    }

    protected function addMetricTypesPresent(array $metricTypesPresent): void
    {
        $this->metricTypesPresent = array_unique(array_merge($this->metricTypesPresent, $metricTypesPresent));
    }
}
