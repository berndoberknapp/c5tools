<?php

/**
 * ReportAttributeList51 handles JSON COUNTER R5.1 Report Attribute lists
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

class ReportAttributeList51 extends ReportAttributeList
{
    public function asJson() // no return type declaration since it differs in parent class
    {
        $json = new \stdClass();
        foreach ($this->getData() as $property => $value) {
            if ($this->attributesConfig[$property]['multi'] ?? false) {
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
            array_keys($this->attributesConfig)
        );
        foreach ($properties as $property => $attributes) {
            if ($this->attributesConfig[$property]['multi'] ?? false) {
                if (! is_array($attributes)) {
                    $type = (is_object($attributes) ? 'an object' : 'a scalar');
                    $message = "{$property} must be an array, found {$type}";
                    $position = ($this->isJson() ? "{$this->position}.{$property}" : $this->position);
                    $data = $this->formatData($property, $attributes);
                    $this->addCriticalError($message, $message, $position, $data);
                    $this->setInvalid($property, $attributes);
                    continue;
                }
                if (empty($attributes)) {
                    $message = "{$property} must not be empty";
                    $position = ($this->isJson() ? "{$this->position}.{$property}" : $this->position);
                    $data = $this->formatData($property, $attributes);
                    $hint = 'optional elements without a value must be omitted';
                    $this->addError($message, $message, $position, $data, $hint);
                    $this->setInvalid($property, $attributes);
                    continue;
                }
                $this->values[$property] = [];
                $this->indices[$property] = [];
                foreach ($attributes as $index => $value) {
                    $this->setIndex($index);
                    $position = ($this->isJson() ? "{$this->position}.{$property}[{$index}]" : $this->position);
                    $value = $this->checkedPermittedValue(
                        $position,
                        $property,
                        $value,
                        $this->attributesConfig[$property]['values'],
                        true
                    );
                    if ($value !== null) {
                        $this->values[$property][] = $value;
                        $this->indices[$property][] = $index;
                    }
                }
                $this->setIndex(null);
            } else {
                $position = ($this->isJson() ? "{$this->position}.{$property}" : $this->position);
                if (! is_scalar($attributes)) {
                    $type = (is_object($attributes) ? 'an object' : 'an array');
                    $message = "{$property} must be a scalar, found {$type}";
                    $data = $this->formatData($property, $attributes);
                    $this->addCriticalError($message, $message, $position, $data);
                    $this->setInvalid($property, $attributes);
                    continue;
                }
                $value = $this->checkedPermittedValue(
                    $position,
                    $property,
                    $attributes,
                    $this->attributesConfig[$property]['values'],
                    true
                );
                if ($value !== null) {
                    $this->values[$property] = [
                        $value
                    ];
                    $this->indices[$property] = [
                        0 => 0
                    ];
                }
            }
        }

        $this->checkDefaultValues();

        foreach ($this->values as $name => $values) {
            if (! empty($values)) {
                $this->setData($name, $values);
            }
        }

        $this->setParsed();

        if (empty($this->getData())) {
            $this->setUnusable();
        }
    }
}
