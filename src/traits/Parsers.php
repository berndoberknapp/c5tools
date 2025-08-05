<?php

/**
 * Parsers is a collection of parse methods for JSON elements used by various classes
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\traits;

use ubfr\c5tools\data\AuthorList51;
use ubfr\c5tools\data\IdentifierList51;
use ubfr\c5tools\data\ItemContributorList;
use ubfr\c5tools\data\JsonParentItem51;
use ubfr\c5tools\data\TypeValueList;

trait Parsers
{
    protected function fixObjectProperties(
        string $position,
        string $element,
        object $object,
        array $fixProperties
    ): void {
        foreach ($fixProperties as $property => $fixProperty) {
            if (! $this->hasProperty($object, $property)) {
                continue;
            }
            $message = "Property '{$property}' is wrong";
            if ($this->isJson()) {
                $position = "{$position}.{$property}";
            }
            $data = $this->formatData($element, $property);
            if ($this->hasProperty($object, $fixProperty)) {
                $message .= ' and cannot be fixed';
                $this->addCriticalError(
                    $message,
                    $message,
                    $position,
                    $data,
                    "correct property {$fixProperty} also is present"
                );
            } else {
                $this->addError($message, $message, $position, $data, "must be '{$fixProperty}'");
                $object->$fixProperty = $this->getProperty($object, $property);
                $this->setSpelling($property, $fixProperty);
                $this->unsetProperty($object, $property);
            }
        }
    }

    protected function getObjectProperties(
        string $position,
        string $element,
        object $object,
        array $requiredProperties,
        array $optionalProperties = []
    ): array {
        $permittedProperties = array_merge($requiredProperties, $optionalProperties);
        sort($permittedProperties);

        $properties = [];
        foreach ($object as $property => $value) {
            if ($this->isJson()) {
                $propertyPosition = ($position === '.' ? ".{$property}" : "{$position}.{$property}");
            } else {
                $propertyPosition = $position;
            }
            $data = $this->formatData($element, $property);
            if ($value === null) {
                $value = 'null';
            }
            if (in_array($property, $permittedProperties)) {
                if ($value === 'null') {
                    $isRequired = in_array($property, $requiredProperties);
                    $message = "Null value for property '{$property}' is invalid";
                    if ($isRequired) {
                        $this->addCriticalError($message, $message, $propertyPosition, $data);
                        $this->setInvalid($property, $value);
                        // value is needed for further checks, null values will be removed later
                        $properties[$property] = $value;
                    } else {
                        $hint = 'optional elements without a value must be omitted';
                        $this->addError($message, $message, $propertyPosition, $data, $hint);
                        $this->setFixed($property, $value);
                    }
                } else {
                    $properties[$property] = $value;
                }
            } elseif (($correctedProperty = $this->inArrayFuzzy($property, $permittedProperties)) !== null) {
                $message = "Spelling of property '{$property}' is wrong";
                $this->addError($message, $message, $propertyPosition, $data, "must be spelled '{$correctedProperty}'");
                $this->setSpelling($property, $correctedProperty);
                if ($value === 'null') {
                    $isRequired = in_array($correctedProperty, $requiredProperties);
                    $message = "Null value for property '{$correctedProperty}' is invalid";
                    if ($isRequired) {
                        $this->addCriticalError($message, $message, $propertyPosition, $data);
                        $this->setInvalid($property, $value);
                        // value is needed for further checks, null values will be removed later
                        $properties[$correctedProperty] = $value;
                    } else {
                        $hint = 'optional elements without a value must be omitted';
                        $this->addError($message, $message, $propertyPosition, $data, $hint);
                        $this->setFixed($correctedProperty, $value);
                    }
                } else {
                    $properties[$correctedProperty] = $value;
                }
            } else {
                // TODO: better way to check if the context is parent
                if (
                    $element === 'Report_Item' && ! ($this instanceof JsonParentItem51) &&
                    $this->config->isAttributesToShow($this->reportHeader->getReportId(), $this->getFormat(), $property)
                ) {
                    $message = "Property '{$property}' is not included in Attributes_To_Show and therefore invalid";
                } else {
                    $message = "Property '{$property}' is invalid";
                }
                $this->addError(
                    $message,
                    $message,
                    $propertyPosition,
                    $data,
                    "permitted properties are '" . implode("', '", $permittedProperties) . "'"
                );
                $this->setInvalid($property, $value);
            }
        }

        foreach ($requiredProperties as $property) {
            if (! array_key_exists($property, $properties)) {
                $message = "Required property '{$property}' is missing";
                $this->addCriticalError($message, $message, $position, $element);
                // even if a required property is missing the object might still be usable,
                // therefore the calling method has to decide whether the data is usable
            }
        }

        $result = [];
        foreach ($properties as $property => $value) {
            if ($value !== 'null') {
                $result[$property] = $value;
            }
        }
        return $result;
    }

    protected function parseIdentifierList(string $position, string $property, $value): void
    {
        if (! $this->isNonEmptyArray($position, $property, $value)) {
            return;
        }

        $identifierType = substr($property, 0, - 3);
        $identifierConfig = $this->config->getIdentifiers($identifierType, $this->getFormat());
        $identifierList = new TypeValueList($this, $position, $value, $property, $identifierConfig);
        if ($identifierList->isUsable()) {
            $this->setData($property, $identifierList);
            if ($identifierList->isFixed() || $identifierList->isInvalid()) {
                // if the identifier list is both usable and invalid, some invalid data has been removed,
                // so the identifier list also has been fixed in this case
                $this->setFixed($property, $value);
            }
        } else {
            $this->setInvalid($property, $value);
        }
    }

    protected function parseIdentifierList51(string $position, string $property, $value): void
    {
        if (! $this->isObject($position, $property, $value)) {
            return;
        }

        if ($this->isEmptyObject($value)) {
            $message = "Empty object for '{$property}' is invalid";
            $data = $this->formatData($property, $value);
            if ($property === 'Institution_ID') {
                $addLevel = 'addCriticalError';
                $hint = 'at least one Institution_ID must be provided';
                //$this->setUnusable();
            } else {
                $addLevel = 'addError';
                $hint = 'optional elements without a value must be omitted';
            }
            $this->$addLevel($message, $message, $position, $data, $hint);
        }

        $identifierType = substr($property, 0, - 3);
        $identifierConfig = $this->config->getIdentifiers($identifierType, $this->getFormat());
        $identifierList = new IdentifierList51($this, $position, $value, $property, $identifierConfig);
        if ($identifierList->isUsable()) {
            $this->setData($property, $identifierList);
            if ($identifierList->isFixed() || $identifierList->isInvalid()) {
                // if the identifier list is both usable and invalid, some invalid data has been removed,
                // so the identifier list also has been fixed in this case
                $this->setFixed($property, $value);
            }
        } else {
            $this->setInvalid($property, $value);
        }
    }

    protected function parseItemContributors(string $position, string $property, $value): void
    {
        if (! $this->isNonEmptyArray($position, $property, $value)) {
            return;
        }

        $itemContributorList = new ItemContributorList($this, $position, $value);
        if ($itemContributorList->isUsable()) {
            $this->setData($property, $itemContributorList);
            if ($itemContributorList->isFixed()) {
                $this->setFixed($property, $value);
            }
        } else {
            $this->setInvalid($property, $value);
        }
    }

    protected function parseAuthorList51(string $position, string $property, $value): void
    {
        if (! $this->isNonEmptyArray($position, $property, $value)) {
            return;
        }

        $authorList = new AuthorList51($this, $position, $value);
        if ($authorList->isUsable()) {
            $this->setData($property, $authorList);
            if ($authorList->isFixed()) {
                $this->setFixed($property, $value);
            }
        } else {
            $this->setInvalid($property, $value);
        }
    }

    protected function parseItemDates(string $position, string $property, $value): void
    {
        if (! $this->isNonEmptyArray($position, $property, $value)) {
            return;
        }

        if ($this->isJson()) {
            $position = "{$this->position}.{$property}";
        }
        $typesConfig = [
            'Publication_Date' => [
                'check' => 'checkedDate'
            ]
        ];
        $itemDateList = new TypeValueList($this, $position, $value, $property, $typesConfig);
        if ($itemDateList->isUsable()) {
            $this->setData($property, $itemDateList);
            if ($itemDateList->isFixed()) {
                $this->setFixed($property, $value);
            }
        } else {
            $this->setInvalid($property, $value);
        }
    }

    protected function parseItemAttributes(string $position, string $property, $value): void
    {
        if (! $this->isNonEmptyArray($position, $property, $value)) {
            return;
        }

        if ($this->isJson()) {
            $position = "{$this->position}.{$property}";
        }
        $typesConfig = [
            'Article_Version' => [
                'check' => 'checkedArticleVersion'
            ],
            'Article_Type' => [
                'check' => 'checkedRequiredNonEmptyString' // TODO: permitted values?
            ],
            'Qualification_Name' => [
                'check' => 'checkedRequiredNonEmptyString' // TODO: permitted values?
            ],
            'Qualification_Level' => [
                'check' => 'checkedRequiredNonEmptyString' // TODO: permitted values?
            ],
            'Proprietary' => [
                'multi' => true,
                'check' => 'checkedProprietaryValue'
            ]
        ];
        $itemAttributeList = new TypeValueList($this, $position, $value, $property, $typesConfig);
        if ($itemAttributeList->isUsable()) {
            $this->setData($property, $itemAttributeList);
            if ($itemAttributeList->isFixed()) {
                $this->setFixed($property, $value);
            }
        } else {
            $this->setInvalid($property, $value);
        }
    }
}
