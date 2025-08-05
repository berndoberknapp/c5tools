<?php

/**
 * ItemContributorList handles COUNTER R5 author lists
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

class ItemContributorList implements \ubfr\c5tools\interfaces\CheckedDocument, \Countable, \IteratorAggregate
{
    use CheckedDocument;
    use Parsers;
    use Checks;
    use Helpers;

    protected static ?array $authorIdentifiers = null;

    public function __construct(\ubfr\c5tools\interfaces\CheckedDocument $parent, string $position, array $document)
    {
        $this->document = $document;
        $this->checkResult = $parent->getCheckResult();
        $this->config = $parent->getConfig();
        $this->format = $parent->getFormat();
        $this->position = $position;

        if (self::$authorIdentifiers === null) {
            self::$authorIdentifiers = array_keys($this->config->getIdentifiers('Author', $this->getFormat()));
        }
    }

    public function __toString(): string
    {
        $itemContributors = [];
        foreach ($this as $itemContributor) {
            $identifier = '';
            foreach (self::$authorIdentifiers as $authorIdentifier) {
                if (isset($itemContributor[$authorIdentifier])) {
                    $identifier = ' (' . $authorIdentifier . ':' . $itemContributor[$authorIdentifier] . ')';
                    break;
                }
            }
            $itemContributors[] = $itemContributor['Name'] . $identifier;
        }

        return implode('; ', $itemContributors);
    }

    public function asJson(): array
    {
        $itemContributors = [];
        foreach ($this as $itemContributor) {
            $json = new \stdClass();
            $json->Type = 'Author';
            $json->Name = $itemContributor['Name'];
            foreach (self::$authorIdentifiers as $authorIdentifier) {
                if (isset($itemContributor[$authorIdentifier])) {
                    $json->Identifier = $authorIdentifier . ':' . $itemContributor[$authorIdentifier];
                    break;
                }
            }
            $itemContributors[] = $json;
        }

        return $itemContributors;
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

        foreach ($this->document as $index => $itemContributorJson) {
            $this->setIndex($index);
            $position = ($this->isJson() ? "{$this->position}[{$index}]" : $this->position);
            if (! $this->isArrayValueObject($position, $this->position, $itemContributorJson)) {
                continue;
            }
            $this->parseItemContributor($position, $itemContributorJson);
        }

        $this->setParsed();

        if (empty($this->get('.'))) {
            $this->setUnusable();
        }

        $this->document = null;
    }

    protected function parseItemContributor($position, $itemContributorJson): void
    {
        $properties = $this->getObjectProperties($position, 'Item_Contributor', $itemContributorJson, [
            'Name',
            'Type'
        ], [
            'Identifier'
        ]);
        if (! isset($properties['Type']) || ! isset($properties['Name'])) {
            $this->setInvalid('.', $itemContributorJson);
            return;
        }

        $itemContributor = [];
        foreach (array_keys($properties) as $property) {
            $propertyPosition = ($this->isJson() ? "{$position}.{$property}" : $position);
            if ($property === 'Type') {
                $type = $this->checkedPermittedValue($propertyPosition, $property, $properties[$property], [
                    'Author'
                ], true);
                if ($type === null) {
                    $this->setInvalid('.', $itemContributorJson);
                    return;
                }
            } elseif ($property === 'Name') {
                $name = $this->checkedRequiredNonEmptyString($propertyPosition, $property, $properties[$property]);
                if ($name === null) {
                    $this->setInvalid('.', $itemContributorJson);
                    return;
                }
                $itemContributor['Name'] = $name;
            } else {
                $identifier = $this->checkedAuthorIdentifier($propertyPosition, $property, $properties[$property]);
                if ($identifier !== null) {
                    $itemContributor[$identifier->Type] = $identifier->Value;
                }
            }
        }
        $this->setData('.', $itemContributor);
    }
}
