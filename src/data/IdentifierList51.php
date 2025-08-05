<?php

/**
 * IdentifierList51 handles COUNTER R5.1 organization and item identifier lists
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

class IdentifierList51 implements \ubfr\c5tools\interfaces\CheckedDocument
{
    use CheckedDocument;
    use Parsers;
    use Checks;
    use Helpers;

    protected string $property;

    protected array $identifierConfig;

    protected bool $skipChecks;

    public function __construct(
        \ubfr\c5tools\interfaces\CheckedDocument $parent,
        string $position,
        $document,
        string $property,
        array $identifierConfig,
        bool $skipChecks = false
    ) {
        $this->document = $document;
        $this->checkResult = $parent->getCheckResult();
        $this->config = $parent->getConfig();
        $this->format = $parent->getFormat();
        $this->position = $position;

        $this->property = $property;
        $this->identifierConfig = $identifierConfig;
        $this->skipChecks = $skipChecks;
    }

    protected function parseDocument(): void
    {
        $this->setParsing();

        $properties = $this->getObjectProperties(
            $this->position,
            $this->property,
            $this->document,
            [],
            array_keys($this->identifierConfig)
        );
        foreach ($properties as $property => $identifiers) {
            if ($this->identifierConfig[$property]['multi'] ?? false) {
                if (! is_array($identifiers)) {
                    $type = (is_object($identifiers) ? 'an object' : 'a scalar');
                    $message = "{$property} must be an array, found {$type}";
                    $position = ($this->isJson() ? "{$this->position}.{$property}" : $this->position);
                    $data = $this->formatData($property, $identifiers);
                    $this->addError($message, $message, $position, $data);
                    $this->setInvalid($property, $identifiers);
                    continue;
                }
                if (empty($identifiers)) {
                    $message = "{$property} must not be empty";
                    $position = ($this->isJson() ? "{$this->position}.{$property}" : $this->position);
                    $data = $this->formatData($property, $identifiers);
                    $hint = 'optional elements without a value must be omitted';
                    $this->addError($message, $message, $position, $data, $hint);
                    $this->setInvalid($property, $identifiers);
                    continue;
                }
                $checkMethod = $this->identifierConfig[$property]['check'];
                foreach ($identifiers as $index => $identifier) {
                    $this->setIndex($index);
                    $position = ($this->isJson() ? "{$this->position}.{$property}[{$index}]" : $this->position);
                    if ($identifier === null) {
                        $message = "Null value for property '{$property}' is invalid";
                        $data = $this->formatData($property, $identifier);
                        $this->addError($message, $message, $position, $data);
                    } elseif (! $this->skipChecks) {
                        $identifier = $this->$checkMethod($position, $property, $identifier);
                    }
                    if ($identifier !== null) {
                        $this->setData($property === 'Proprietary_ID' ? 'Proprietary' : $property, $identifier);
                    } else {
                        $this->setInvalid($property, $identifier);
                    }
                }
                $this->setIndex(null);
            } else {
                if (! is_scalar($identifiers)) {
                    $type = (is_object($identifiers) ? 'an object' : 'an array');
                    $message = "{$property} must be a scalar, found {$type}";
                    $position = ($this->isJson() ? "{$this->position}.{$property}" : $this->position);
                    $data = $this->formatData($property, $identifiers);
                    $this->addCriticalError($message, $message, $position, $data);
                    $this->setInvalid($property, $identifiers);
                    continue;
                }
                $checkMethod = $this->identifierConfig[$property]['check'];
                $position = ($this->isJson() ? "{$this->position}.{$property}" : $this->position);
                if (! $this->skipChecks) {
                    $identifier = $this->$checkMethod($position, $property, $identifiers);
                }
                if ($identifier !== null) {
                    $this->setData($property === 'Proprietary_ID' ? 'Proprietary' : $property, $identifier);
                } else {
                    $this->setInvalid($property, $identifier);
                }
            }
        }

        $this->setParsed();

        if (empty($this->getData())) {
            $this->setUnusable();
        }

        $this->document = null;
    }
}
