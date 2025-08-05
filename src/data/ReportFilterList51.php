<?php

/**
 * ReportFilterList51 handles JSON COUNTER R5.1 Report Filter lists
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

class ReportFilterList51 extends ReportFilterList
{
    public function asJson() // no return type declaration since it differs in parent class
    {
        $json = new \stdClass();
        foreach ($this->getData() as $property => $value) {
            if ($this->filtersConfig[$property]['multi'] ?? false) {
                $json->$property = $value;
            } else {
                $json->$property = $value[0];
            }
        }

        return $json;
    }

    protected function parseDocument(): void
    {
        $this->setParsing();

        $properties = $this->getObjectProperties(
            $this->position,
            $this->property,
            $this->document,
            [],
            array_keys($this->filtersConfig)
        );
        foreach ($properties as $property => $filters) {
            if ($this->filtersConfig[$property]['multi'] ?? false) {
                if (! is_array($filters)) {
                    $type = (is_object($filters) ? 'an object' : 'a scalar');
                    $message = "{$property} must be an array, found {$type}";
                    $position = ($this->isJson() ? "{$this->position}.{$property}" : $this->position);
                    $data = $this->formatData($property, $filters);
                    $this->addCriticalError($message, $message, $position, $data);
                    $this->setInvalid($property, $filters);
                    continue;
                }
                if (empty($filters)) {
                    $message = "{$property} must not be empty";
                    $position = ($this->isJson() ? "{$this->position}.{$property}" : $this->position);
                    $data = $this->formatData($property, $filters);
                    $hint = 'optional elements without a value must be omitted';
                    $this->addError($message, $message, $position, $data, $hint);
                    $this->setInvalid($property, $filters);
                    continue;
                }
                $this->values[$property] = [];
                $this->indices[$property] = [];
                foreach ($filters as $index => $value) {
                    $this->setIndex($index);
                    if ($property !== 'YOP') {
                        $position = ($this->isJson() ? "{$this->position}.{$property}[{$index}]" : $this->position);
                        $value = $this->checkedPermittedValue(
                            $position,
                            $property,
                            $value,
                            $this->filtersConfig[$property]['values'],
                            true
                        );
                    }
                    if ($value !== null) {
                        $this->values[$property][] = $value;
                        $this->indices[$property][] = $index;
                    }
                }
                $this->setIndex(null);
            } else {
                if (! is_scalar($filters)) {
                    $type = (is_object($filters) ? 'an object' : 'an array');
                    $message = "{$property} must be a scalar, found {$type}";
                    $position = ($this->isJson() ? "{$this->position}.{$property}" : $this->position);
                    $data = $this->formatData($property, $filters);
                    $this->addCriticalError($message, $message, $position, $data);
                    $this->setInvalid($property, $filters);
                    continue;
                }
                $this->values[$property] = [
                    $filters
                ];
                $this->indices[$property] = [
                    0 => 0
                ];
            }
        }

        $this->checkDefaultValues();
        $this->checkYopFilters();
        $this->checkRequiredReportFilters();
        $this->checkDateFilters();
        $this->checkOtherFilters();

        foreach ($this->values as $name => $filters) {
            if (! empty($filters)) {
                $this->setData($name, $filters);
            }
        }

        $this->setParsed();

        if ($this->get('Begin_Date') === null || $this->get('End_Date') === null) {
            $message = ($this->isJson() ? "{$this->position}." : 'Reporting_Period ') . "Begin_Date/End_Date is " .
                (($this->getInvalid('Begin_Date') !== null || $this->getInvalid('End_Date') !== null) ? 'invalid' : 'missing');
            $this->addFatalError($message, $message);
            $this->setUnusable();
        }
    }

    protected function checkOtherFilters(): void
    {
        static $otherFilters = [
            'Author' => 'checkedOptionalNonEmptyString',
            'Country_Code' => 'checkedCountryCode',
            'Database' => 'checkedOptionalNonEmptyString',
            'Item_ID' => 'checkedOptionalNonEmptyString',
            'Platform' => 'checkedOptionalNonEmptyString',
            'Subdivision_Code' => 'checkedSubdivisionCode'
        ];

        $this->setIndex(0);
        foreach ($otherFilters as $name => $checkMethod) {
            if (isset($this->values[$name])) {
                $position = ($this->isJson() ? "{$this->position}.{$name}" : $this->position);
                $value = $this->$checkMethod($position, $name, $this->values[$name][0]);
                if ($value === null) {
                    $this->setInvalid($name, $this->values[$name][0]);
                    unset($this->values[$name]);
                } elseif ($value !== $this->values[$name][0]) {
                    $this->values[$name][0] = $value;
                }
            }
        }
        $this->setIndex(null);
    }
}
