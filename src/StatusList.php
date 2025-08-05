<?php

/**
 * StatusList is the main class for parsing and validating JSON Status List responses
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools;

use ubfr\c5tools\data\StatusInfo;

class StatusList implements interfaces\CheckedDocument, interfaces\JsonDocument, \Countable, \IteratorAggregate
{
    use traits\CheckedDocument;
    use traits\Parsers;
    use traits\Checks;
    use traits\Helpers;

    public function __construct(Document $document, CounterApiRequest $request)
    {
        if (! $document->isStatusList()) {
            throw new \InvalidArgumentException("document is not valid for StatusList");
        }

        $this->document = $document;
        $this->request = $request;
        $this->checkResult = new CheckResult();
        $this->config = Config::forRelease($request->getRelease());
        $this->format = self::FORMAT_JSON;
        $this->position = '.';

        $this->checkHttpCode200('status list');
        $this->checkNoByteOrderMark();
    }

    public function getJsonString(): string
    {
        return $this->document->getBuffer();
    }

    public function getJson()
    {
        return $this->document->getDocument();
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

        // response is either an object or a non-empty array (checked in Document::isStatusList)
        $json = $this->getJson();
        if (is_object($json)) {
            $message = 'JSON document must be an array, found an object';
            $this->addError($message, $message, $this->position, null);
            $json = [
                $json
            ];
        }

        foreach ($json as $index => $statusInfoJson) {
            $this->setIndex($index);
            $position = "{$this->position}[{$index}]";
            if (! $this->isArrayValueObject($position, '.', $statusInfoJson)) {
                continue;
            }
            $statusInfo = new StatusInfo($this, $position, $statusInfoJson);
            if ($statusInfo->isUsable()) {
                $this->setData('.', $statusInfo);
                if ($statusInfo->isFixed()) {
                    $this->setFixed('.', $statusInfoJson);
                }
            } else {
                $this->setInvalid('.', $statusInfoJson);
            }
        }

        $this->setParsed();

        if (empty($this->get('.'))) {
            $this->setUnusable();
        }
    }
}
