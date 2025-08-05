<?php

/**
 * AlertList handles the JSON StatusInfo Alert lists
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

class AlertList implements \ubfr\c5tools\interfaces\CheckedDocument, \Countable, \IteratorAggregate
{
    use CheckedDocument;
    use Parsers;
    use Checks;
    use Helpers;

    // TODO: no required properties doesn't make much sense...
    protected static array $requiredProperties = [];

    protected static array $optionalProperties = [
        'Date_Time',
        'Alert'
    ];

    public function __construct(\ubfr\c5tools\interfaces\CheckedDocument $parent, string $position, array $document)
    {
        $this->document = $document;
        $this->checkResult = $parent->getCheckResult();
        $this->config = $parent->getConfig();
        $this->format = $parent->getFormat();
        $this->position = $position;
    }

    public function count(): int
    {
        if (! $this->isParsed()) {
            $this->parseDocument();
        }
        return ($this->isUsable() ? count($this->get('.')) : 0);
    }

    public function getIterator(): \ArrayIterator
    {
        if (! $this->isParsed()) {
            $this->parseDocument();
        }
        return new \ArrayIterator($this->isUsable() ? $this->get('.') : []);
    }

    protected function parseDocument(): void
    {
        $this->setParsing();

        foreach ($this->document as $index => $alertJson) {
            $this->setIndex($index);
            $position = "{$this->position}[{$index}]";
            if (! $this->isArrayValueObject($position, $this->position, $alertJson)) {
                continue;
            }
            $properties = $this->getObjectProperties(
                $position,
                'Alert',
                $alertJson,
                self::$requiredProperties,
                self::$optionalProperties
            );

            $alert = [];
            foreach (array_keys($properties) as $property) {
                $position = "{$this->position}[{$index}].{$property}";
                if ($property === 'Date_Time') {
                    $value = $this->checkedRfc3339Date($position, $property, $properties[$property], false);
                } elseif ($property === 'Alert') {
                    $value = $this->checkedRequiredNonEmptyString($position, $property, $properties[$property]);
                }
                if ($value !== null) {
                    $alert[$property] = $value;
                }
            }
            if (isset($alert['Alert'])) {
                $this->setData('.', $alert);
            } else {
                $this->setInvalid('.', $alertJson);
            }
        }

        $this->setParsed();

        if (empty($this->get('.'))) {
            $this->setUnusable();
        }
    }
}
