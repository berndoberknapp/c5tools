<?php

/**
 * TypeValueList handles JSON COUNTER R5 Type-Value lists
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

class TypeValueList extends KeyValueList implements \Countable, \IteratorAggregate
{
    protected array $typesConfig;

    protected bool $skipChecks;

    protected array $singleValueTypes;

    public function __construct(
        \ubfr\c5tools\interfaces\CheckedDocument $parent,
        string $position,
        array $document,
        string $property,
        array $typesConfig,
        bool $skipChecks = false
    ) {
        // format: Type => [ multi => bool, check => method name ]
        $this->typesConfig = $typesConfig;

        $this->skipChecks = $skipChecks;

        $this->singleValueTypes = [];
        foreach ($this->typesConfig as $type => $typeConfig) {
            if (! isset($typeConfig['multi']) || ! $typeConfig['multi']) {
                $this->singleValueTypes[] = $type;
            }
        }

        parent::__construct(
            $parent,
            $position,
            $document,
            $property,
            'Type',
            'Value',
            array_keys($typesConfig),
            $this->singleValueTypes
        );
    }

    public function __toString(): string
    {
        $typeValues = [];
        foreach ($this as $type => $values) {
            sort($values);
            $typeValues[] = ($type === 'Proprietary' ? '' : "{$type}:") . implode('|', $values);
        }
        return implode('; ', $typeValues);
    }

    public function asArray(): array
    {
        $typeValues = [];
        foreach ($this as $type => $values) {
            sort($values);
            $typeValues[$type] = implode('|', $values);
        }
        return $typeValues;
    }

    public function count(): int
    {
        if (! $this->isParsed()) {
            $this->parseDocument();
        }
        return ($this->isUsable() ? count($this->getData()) : 0);
    }

    public function getIterator(): \ArrayIterator
    {
        if (! $this->isParsed()) {
            $this->parseDocument();
        }
        return new \ArrayIterator($this->isUsable() ? $this->getData() : []);
    }

    protected function parseDocument(): void
    {
        $this->setParsing();

        parent::parseDocument();

        foreach ($this->values as $type => $values) {
            $typeConfig = $this->typesConfig[$type];
            $checkMethod = $typeConfig['check'];
            $isSingleValued = in_array($type, $this->singleValueTypes);
            foreach ($values as $i => $value) {
                $index = $this->indices[$type][$i];
                $this->setIndex($index);
                $position = ($this->isJson() ? "{$this->position}[{$index}].Value" : $this->position);
                $originalValue = $value;
                if (! $this->skipChecks) {
                    $value = $this->$checkMethod($position, 'Value', $value);
                }
                if ($value === null) {
                    unset($this->values[$type][$i]);
                    unset($this->indices[$type][$i]);
                    continue;
                }
                if ($i > 0 && ($isSingleValued || $this->isTabular())) {
                    $message = "Multiple values specified for Type '{$type}'";
                    $data = $this->formatData('Value', $value);
                    if ($isSingleValued) {
                        $hint = 'only one value is permitted, ignoring value';
                    } else {
                        $hint = 'in tabular reports only one value is permitted, ignoring value';
                    }
                    $this->addError($message, $message, $position, $data, $hint);
                    if ($this->isJson()) {
                        $this->setFixed('.', $this->entries[$index]);
                    }
                    unset($this->values[$type][$i]);
                    unset($this->indices[$type][$i]);
                    continue;
                }
                if ($value !== $originalValue) {
                    $this->values[$type][$i] = $value;
                }
            }
        }
        $this->setIndex(null);

        foreach ($this->values as $type => $values) {
            if (! empty($values)) {
                $this->setData($type === 'Proprietary_ID' ? 'Proprietary' : $type, $values);
            }
        }

        $this->setParsed();

        if (empty($this->getData())) {
            $this->setUnusable();
        }
    }
}
