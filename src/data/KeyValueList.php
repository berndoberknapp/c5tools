<?php

/**
 * KeyValueList is the abstract base class for handling JSON COUNTER R5 Name-Value and Type-Value lists
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

use ubfr\c5tools\traits\CheckedDocument;
use ubfr\c5tools\traits\Checks;
use ubfr\c5tools\traits\Helpers;
use ubfr\c5tools\traits\Parsers;
use ubfr\c5tools\exceptions\UnusableCheckedDocumentException;

abstract class KeyValueList implements \ubfr\c5tools\interfaces\CheckedDocument
{
    use CheckedDocument;
    use Parsers;
    use Checks;
    use Helpers;

    protected string $property;

    protected string $keyName;

    protected string $valueName;

    protected array $permittedKeys;

    protected array $singleValueKeys;

    protected array $entries;

    protected array $values;

    protected array $indices;

    public function __construct(
        \ubfr\c5tools\interfaces\CheckedDocument $parent,
        string $position,
        $document,
        string $property,
        string $keyName,
        string $valueName,
        array $permittedKeys,
        array $singleValueKeys = []
    ) {
        $this->document = $document;
        $this->checkResult = $parent->getCheckResult();
        $this->config = $parent->getConfig();
        $this->format = $parent->getFormat();
        $this->position = $position;

        $this->property = $property;
        $this->keyName = $keyName;
        $this->valueName = $valueName;
        $this->permittedKeys = $permittedKeys;
        $this->singleValueKeys = $singleValueKeys;

        $this->entries = [];
        $this->values = [];
        $this->indices = [];
    }

    public function asJson() // no return type declaration since it differs in subclasses
    {
        if (! $this->isUsable()) {
            throw new UnusableCheckedDocumentException(get_class($this) . ' is unusable');
        }

        $keyName = $this->keyName;
        $valueName = $this->valueName;
        $json = [];
        foreach ($this->getData() as $key => $values) {
            $entry = new \stdClass();
            $entry->$keyName = $key;
            $entry->$valueName = implode('|', $values);
            $json[] = $entry;
        }

        return $json;
    }

    protected function parseDocument(): void
    {
        $requiredProperties = [
            $this->keyName,
            $this->valueName
        ];

        foreach ($this->document as $index => $keyValueJson) {
            $this->setIndex($index);
            $this->entries[$index] = $keyValueJson;
            $position = ($this->isJson() ? "{$this->position}[{$index}]" : $this->position);
            if (! $this->isArrayValueObject($position, '.', $keyValueJson)) {
                continue;
            }
            $properties = $this->getObjectProperties($position, $this->property, $keyValueJson, $requiredProperties, []);

            $key = null;
            if (isset($properties[$this->keyName])) {
                $position = ($this->isJson() ? "{$this->position}[{$index}].{$this->keyName}" : $this->position);
                $key = $this->checkedPermittedValue(
                    $position,
                    $this->keyName,
                    $properties[$this->keyName],
                    $this->permittedKeys,
                    true
                );
                if ($key !== null) {
                    if (isset($this->values[$key])) {
                        $message = "{$this->keyName} '{$key}' specified multiple times";
                        $data = $this->formatData($this->keyName, $key);
                        $hint = (in_array($key, $this->singleValueKeys) ? null : 'multiple values must be separated by a pipe character ("|") instead');
                        $this->addError($message, $message, $position, $data, $hint);
                        if ($this->isJson()) {
                            $this->setFixed('.', $keyValueJson);
                        }
                    } else {
                        $this->values[$key] = [];
                        $this->indices[$key] = [];
                    }
                }
            }

            $values = null;
            if (isset($properties[$this->valueName])) {
                $position = ($this->isJson() ? "{$this->position}[{$index}].{$this->valueName}" : $this->position);
                $values = $this->checkedPipeSeparatedValues($position, $key ?? $this->valueName, $properties[$this->valueName]);
                if ($key === null || $values === null) {
                    continue;
                }
                foreach ($values as $value) {
                    if (in_array($value, $this->values[$key])) {
                        $summary = "Value for {$this->keyName} '{$key}' specified multiple times";
                        $message = "Value '{$value}' for {$this->keyName} '{$key}' specified multiple times";
                        $data = $this->formatData($this->valueName, $value);
                        $this->addError($summary, $message, $position, $data);
                        if ($this->isJson()) {
                            $this->setFixed('.', $keyValueJson);
                        }
                    } else {
                        $this->values[$key][] = $value;
                        $this->indices[$key][] = $index;
                    }
                }
            }
        }
        $this->setIndex(null);

        $this->document = null;
    }
}
