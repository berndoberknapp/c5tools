<?php

/**
 * MemberList is the main class for parsing and validating JSON Member List responses
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools;

use ubfr\c5tools\data\MemberInfo;

class MemberList implements interfaces\CheckedDocument, interfaces\JsonDocument, \Countable, \IteratorAggregate
{
    use traits\CheckedDocument;
    use traits\Parsers;
    use traits\Checks;
    use traits\Helpers;

    public function __construct(Document $document, CounterApiRequest $request)
    {
        if (! $document->isMemberList()) {
            throw new \InvalidArgumentException("document is not valid for MemberList");
        }

        $this->document = $document;
        $this->request = $request;
        $this->checkResult = new CheckResult();
        $this->config = Config::forRelease($request->getRelease());
        $this->format = self::FORMAT_JSON;
        $this->position = '.';

        $this->checkHttpCode200('member list');
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

        // response is either an object or an array (checked in Document::isMemberList)
        $json = $this->getJson();
        if (is_object($json)) {
            $message = 'JSON document must be an array, found an object';
            $this->addError($message, $message, $this->position, null);
            $json = [
                $json
            ];
        }

        if (empty($json)) {
            $message = 'JSON document is an empty array';
            $hint = 'if the customer is not a multi-site organization, the details for the customer must be returned';
            $this->addError($message, $message, $this->position, null, $hint);
            $this->setParsed();
            $this->setUnusable();
            return;
        }

        foreach ($json as $index => $memberInfoJson) {
            $this->setIndex($index);
            $position = "{$this->position}[{$index}]";
            if (! $this->isArrayValueObject($position, '.', $memberInfoJson)) {
                continue;
            }
            $memberInfo = new MemberInfo($this, $position, $memberInfoJson);
            if ($memberInfo->isUsable()) {
                $this->setData('.', $memberInfo);
                if ($memberInfo->isFixed()) {
                    $this->setFixed('.', $memberInfoJson);
                }
            } else {
                $this->setInvalid('.', $memberInfoJson);
            }
        }

        $this->setParsed();

        if (empty($this->get('.'))) {
            // TODO; error for empty list
            $this->setUnusable();
        } else {
            $this->checkMemberInfo();
        }
    }

    protected function checkMemberInfo(): void
    {
        $membersByCustomerId = [];
        foreach ($this as $index => $memberInfo) {
            $customerId = $memberInfo->get('Customer_ID');
            if (isset($membersByCustomerId[$customerId])) {
                $firstMemberInfo = $membersByCustomerId[$customerId][0];
                $message = "Multiple member list entries with Customer_ID '{$customerId}' ";
                // TODO: implement diff method for MemberInfo
                $diffs = [];
                if ($memberInfo->get('Name') !== $firstMemberInfo->get('Name')) {
                    $diffs[] = 'Names';
                }
                if ($memberInfo->get('Institution_Name') !== $firstMemberInfo->get('Institution_Name')) {
                    $diffs[] = 'Institution_Names';
                }
                if ($memberInfo->get('Requestor_ID') !== $firstMemberInfo->get('Requestor_ID')) {
                    $diffs[] = 'Requestor_IDs';
                }
                if (empty($diffs)) {
                    $addLevel = 'addWarning';
                    $message .= 'and identical Name and Requestor_ID';
                } else {
                    $addLevel = 'addError';
                    $message .= 'and different ' . implode(' and ', $diffs);
                }
                $this->$addLevel($message, $message, "{$this->position}[{$index}]", null);
            } else {
                $membersByCustomerId[$customerId] = [];
                $membersByCustomerId[$customerId][] = $memberInfo;
            }
        }
    }
}
