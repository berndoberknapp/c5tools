<?php

/**
 * TabularParsers is a collection of parse methods for tabular elements used by various classes
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\traits;

use ubfr\c5tools\data\IdentifierList51;
use ubfr\c5tools\data\TypeValueList;

trait TabularParsers
{
    protected function parseIdentifierList(string $position, string $property, $value): void
    {
        $separatedValues = $this->checkedSemicolonSpaceSeparatedValues($position, $property, $value);
        if (empty($separatedValues)) {
            if (trim($value) !== '') {
                $this->setInvalid($property, $value);
            }
            return;
        }

        $identifierType = substr($property, 0, - 3);
        $identifierConfig = $this->config->getIdentifiers($identifierType, $this->getFormat());
        $permittedNamespaces = array_keys($identifierConfig);
        $typeValues = $this->checkedNamespacedValues($position, $property, $separatedValues, $permittedNamespaces);
        if (empty($typeValues)) {
            $this->setInvalid($property, $value);
            return;
        }

        parent::parseIdentifierList($position, $property, $typeValues);
    }

    protected function parseIdentifierList51(string $position, string $property, $value): void
    {
        $separatedValues = $this->checkedSemicolonSpaceSeparatedValues($position, $property, $value);
        if (empty($separatedValues)) {
            if (trim($value) !== '') {
                $this->setInvalid($property, $value);
            }
            if ($property === 'Institution_ID') {
                $message = "{$property} must not be empty";
                $data = $this->formatData($property, $value);
                $hint = 'at least one Institution_ID must be provided';
                $this->addCriticalError($message, $message, $position, $data, $hint);
                // $this->setUnusable();
            }
            return;
        }

        $identifierType = substr($property, 0, - 3);
        $identifierConfig = $this->config->getIdentifiers($identifierType, $this->getFormat());
        $permittedNamespaces = array_keys($identifierConfig);
        $identifierList = new \stdClass();
        foreach ($separatedValues as $namespacedValue) {
            $typeValue = $this->checkedNamespacedValue($position, $property, $namespacedValue, $permittedNamespaces);
            if ($typeValue === null) {
                continue;
            }
            $namespace = $typeValue->Type;
            if (isset($identifierList->$namespace)) {
                $message = "Multiple values specified with namespace '{$namespace}'";
                $data = $this->formatData($property, $value);
                if ($this->isTabular()) {
                    $hint = 'in tabular reports only one value per namespace is permitted, ignoring all values but the first one';
                } elseif ($identifierConfig[$property]['multi'] ?? 0) {
                    $hint = 'multiple values must be separated by a pipe character ("|") instead';
                    $identifierList->$namespace = array_merge($identifierList->$namespace, explode('|', $value));
                } else {
                    $hint = 'only one value is allowed, ignoring value';
                }
                $this->addError($message, $message, $position, $data, $hint);
            } else {
                if ($identifierConfig[$namespace]['multi'] ?? 0) {
                    $identifierList->$namespace = explode('|', $typeValue->Value);
                } else {
                    $identifierList->$namespace = $typeValue->Value;
                }
            }
        }

        if ($this->isEmptyObject($identifierList)) {
            $this->setInvalid($property, $value);
            return;
        }

        parent::parseIdentifierList51($position, $property, $identifierList);
    }

    protected function createItemId(): void
    {
        static $itemIdentifiers = [];

        $cacheKey = $this->config->getRelease() . $this->getFormat();
        if (! isset($itemIdentifiers[$cacheKey])) {
            $itemIdentifiers[$cacheKey] = $this->config->getIdentifiers('Item', $this->getFormat());
        }

        $itemIds = [];
        foreach (array_keys($itemIdentifiers[$cacheKey]) as $itemIdentifier) {
            if (isset($this->data[$itemIdentifier])) {
                $itemIds[] = (object) [
                    'Type' => $itemIdentifier,
                    'Value' => $this->data[$itemIdentifier]
                ];
                unset($this->data[$itemIdentifier]);
            }
        }
        if (! empty($itemIds)) {
            $this->data['Item_ID'] = new TypeValueList(
                $this,
                $this->position,
                $itemIds,
                'Item_ID',
                $itemIdentifiers[$cacheKey],
                true
            );
        }
    }

    protected function createItemId51(): void
    {
        static $itemIdentifiers = [];

        $cacheKey = $this->config->getRelease() . $this->getFormat();
        if (! isset($itemIdentifiers[$cacheKey])) {
            $itemIdentifiers[$cacheKey] = $this->config->getIdentifiers('Item', $this->getFormat());
        }

        $itemIds = new \stdClass();
        $hasItemIds = false;
        foreach (array_keys($itemIdentifiers[$cacheKey]) as $itemIdentifier) {
            if (isset($this->data[$itemIdentifier])) {
                $itemIds->$itemIdentifier = $this->data[$itemIdentifier];
                unset($this->data[$itemIdentifier]);
                $hasItemIds = true;
            }
        }
        if ($hasItemIds) {
            $this->data['Item_ID'] = new IdentifierList51(
                $this,
                $this->position,
                $itemIds,
                'Item_ID',
                $itemIdentifiers[$cacheKey]
            );
        }
    }
}
