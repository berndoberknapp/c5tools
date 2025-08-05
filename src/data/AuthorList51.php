<?php

/**
 * AuthorList51 handles COUNTER R5.1 author lists
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

class AuthorList51 implements \ubfr\c5tools\interfaces\CheckedDocument, \Countable, \IteratorAggregate
{
    use CheckedDocument;
    use Parsers;
    use Checks;
    use Helpers;

    protected static array $checkMethods = [
        'Name' => 'checkedRequiredNonEmptyString'
    ];

    protected static ?array $authorIdentifiers = null;

    public function __construct(\ubfr\c5tools\interfaces\CheckedDocument $parent, string $position, array $document)
    {
        $this->document = $document;
        $this->checkResult = $parent->getCheckResult();
        $this->config = $parent->getConfig();
        $this->format = $parent->getFormat();
        $this->position = $position;

        if (self::$authorIdentifiers === null) {
            self::$authorIdentifiers = [];
            foreach ($this->config->getIdentifiers('Author', $this->getFormat()) as $authorIdentifier => $authorIdentifierConfig) {
                self::$authorIdentifiers[] = $authorIdentifier;
                self::$checkMethods[$authorIdentifier] = $authorIdentifierConfig['check'];
            }
        }
    }

    public function __toString(): string
    {
        $authors = [];
        foreach ($this as $author) {
            $identifier = '';
            foreach (self::$authorIdentifiers as $authorIdentifier) {
                if (isset($author[$authorIdentifier])) {
                    $identifier = ' (' . $authorIdentifier . ':' . $author[$authorIdentifier] . ')';
                    break;
                }
            }
            $authors[] = $author['Name'] . $identifier;
        }

        return implode('; ', $authors);
    }

    public function asJson(): array
    {
        $authors = [];
        foreach ($this as $author) {
            $authors[] = (object) $author;
        }

        return $authors;
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

        foreach ($this->document as $index => $authorJson) {
            $this->setIndex($index);
            $position = ($this->isJson() ? "{$this->position}[{$index}]" : $this->position);
            if (! $this->isArrayValueObject($position, $this->position, $authorJson)) {
                continue;
            }
            if ($index > 2) {
                $message = 'More than three authors, ignoring all but the first three authors';
                $data = $this->formatData('Authors', $this->document);
                $this->addError($message, $message, $this->position, $data);
                break;
            }
            $this->parseAuthor($position, $authorJson);
        }

        $this->setParsed();

        if (empty($this->get('.'))) {
            $this->setUnusable();
        }

        $this->document = null;
    }

    protected function parseAuthor(string $position, object $authorJson): void
    {
        $properties = $this->getObjectProperties($position, 'Author', $authorJson, [
            'Name'
        ], self::$authorIdentifiers);
        $author = [];
        foreach ($properties as $property => $value) {
            $position = ($this->isJson() ? "{$position}.{$property}" : $position);
            $checkMethod = self::$checkMethods[$property];
            $value = $this->$checkMethod($position, $property, $properties[$property]);
            if ($value !== null) {
                $author[$property] = $value;
            }
        }

        if (isset($author['Name'])) {
            $this->setData('.', $author);
        } else {
            $this->setInvalid('.', $authorJson);
        }
    }
}
