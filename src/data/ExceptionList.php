<?php

/**
 * ExceptionList handles COUNTER Exception lists
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

class ExceptionList implements \ubfr\c5tools\interfaces\CheckedDocument, \Countable, \IteratorAggregate
{
    use CheckedDocument;
    use Parsers;
    use Checks;
    use Helpers;

    public function __construct(\ubfr\c5tools\interfaces\CheckedDocument $parent, string $position, array $document)
    {
        $this->document = $document;
        $this->checkResult = $parent->getCheckResult();
        $this->config = $parent->getConfig();
        $this->format = $parent->getFormat();
        $this->position = $position;
    }

    public function __toString(): string
    {
        $exceptions = [];
        foreach ($this as $exception) {
            $exceptions[] = (string) $exception;
        }

        return implode('; ', $exceptions);
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

        foreach ($this->document as $index => $exceptionJson) {
            $this->setIndex($index);
            $position = ($this->isJson() ? "{$this->position}[{$index}]" : $this->position);
            if (! $this->isArrayValueObject($position, $this->position, $exceptionJson)) {
                continue;
            }
            $exception = new Exception($this, $position, $exceptionJson);
            if ($exception->isUsable()) {
                $this->setData('.', $exception);
                if ($exception->isFixed()) {
                    $this->setFixed('.', $exceptionJson);
                }
            } else {
                $this->setInvalid('.', $exceptionJson);
            }
        }

        $this->setParsed();

        if (empty($this->get('.'))) {
            $this->setUnusable();
        }

        $this->document = null;
    }
}
