<?php

/**
 * CheckedDocument is the basis for all parsed and validated documents, including CounterApiException
 *
 * Since PHP requires Exceptions to be derived from Exception or Error and doesn't support multiple
 * inheritance an interface and a trait that implements the interface are used instead of a class.
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\traits;

use ubfr\c5tools\CounterApiRequest;
use ubfr\c5tools\CheckResult;
use ubfr\c5tools\Config;
use ubfr\c5tools\exceptions\UnusableCheckedDocumentException;

trait CheckedDocument
{
    protected $document = null;

    protected ?CounterApiRequest $request = null;

    protected ?CheckResult $checkResult = null;

    protected ?Config $config = null;

    protected ?string $format = null;

    protected ?string $position = null;

    protected bool $isParsing = false;

    protected bool $isParsed = false;

    protected bool $isUsable = true;

    protected ?int $index = null;

    protected array $data = [];

    protected array $fixed = [];

    protected array $invalid = [];

    protected array $spelling = [];

    public function debug(int $level = 1, ?string $property = null): void
    {
        $indent = str_repeat('  ', $level);
        if ($property === null) {
            print(get_class($this) . ":\n");
            print("{$indent}isUsable: ");
            var_dump($this->isUsable());
            print("{$indent}isFixed: ");
            var_dump($this->isFixed());
            print("{$indent}isInvalid: ");
            var_dump($this->isInvalid());
            $this->debug($level, 'data');
            $this->debug($level, 'fixed');
            $this->debug($level, 'invalid');
            $this->debug($level, 'spelling');
            return;
        }
        if (empty($this->$property)) {
            return;
        }
        print("{$indent}{$property}:\n");
        foreach ($this->$property as $key => $values) {
            if (is_array($values)) {
                if (count($values) === 0) {
                    print("{$indent}  {$key}: []\n");
                    continue;
                }
                ksort($values);
                foreach ($values as $index => $value) {
                    print("{$indent}  {$key}[{$index}]: ");
                    if (is_object($value)) {
                        if (method_exists($value, 'debug')) {
                            $value->debug($level + 2);
                        } else {
                            print(json_encode($value) . "\n");
                        }
                    } elseif (is_array($value)) {
                        print("\n");
                        ksort($value);
                        foreach ($value as $subKey => $subValue) {
                            print("{$indent}    {$subKey}: ");
                            var_dump($subValue);
                        }
                    } else {
                        var_dump($value);
                    }
                }
            } else {
                print("{$indent}  {$key}: ");
                if (is_object($values)) {
                    if (method_exists($values, 'debug')) {
                        $values->debug($level + 2);
                    } else {
                        print(json_encode($values) . "\n");
                    }
                } elseif (is_array($values)) {
                    print("\n");
                    ksort($values);
                    foreach ($values as $subKey => $subValue) {
                        print("{$indent}    {$subKey}: ");
                        var_dump($subValue);
                    }
                } else {
                    var_dump($values);
                }
            }
        }
    }

    public function asJson()
    {
        if (! $this->isUsable()) {
            throw new UnusableCheckedDocumentException(get_class($this) . ' is unusable');
        }

        if ($this->get('.') !== null) {
            $json = [];
            foreach ($this->get('.') as $value) {
                if (is_object($value)) {
                    $json[] = $value->asJson();
                } elseif (is_scalar($value)) {
                    $json[] = $value;
                } else {
                    throw new \ErrorException('class ' . get_class($this) . ' requires custom asJson() implementation');
                }
            }
        } else {
            $json = new \stdClass();
            foreach ($this->getData() as $key => $value) {
                $json->$key = (is_object($value) ? $value->asJson() : $value);
            }
        }

        return $json;
    }

    public function isParsed(): bool
    {
        return $this->isParsed;
    }

    public function isUsable(): bool
    {
        if (! $this->isUsable) {
            return false;
        }

        if (! $this->isParsed) {
            $this->parseDocument();
        }

        return $this->isUsable;
    }

    public function isFixed(): bool
    {
        if (! empty($this->fixed)) {
            return true;
        }

        if (! $this->isParsed) {
            $this->parseDocument();
        }

        return ! empty($this->fixed);
    }

    public function isInvalid(): bool
    {
        if (! empty($this->invalid)) {
            return true;
        }

        if (! $this->isParsed) {
            $this->parseDocument();
        }

        return ! empty($this->invalid);
    }

    public function getRequest(): ?CounterApiRequest
    {
        return $this->request;
    }

    public function getCheckResult(): CheckResult
    {
        if (! $this->isParsed && ! $this->isParsing) {
            $this->parseDocument();
        }

        return $this->checkResult;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function getPosition(): string
    {
        return $this->position;
    }

    public function getIndexFromPosition(): ?int
    {
        if ($this->isJson()) {
            $matches = [];
            return (preg_match('/.+\[([0-9]+)\]$/', $this->position, $matches) ? $matches[1] : null);
        } else {
            return (is_numeric($this->position) ? $this->position : null);
        }
    }

    public function isJson(): bool
    {
        return ($this->getFormat() === self::FORMAT_JSON);
    }

    public function isTabular(): bool
    {
        return ($this->getFormat() === self::FORMAT_TABULAR);
    }

    public function get(string $property, bool $keepInvalid = false)
    {
        if (! $this->isParsed) {
            $this->parseDocument();
        }

        if (isset($this->data[$property])) {
            return $this->data[$property];
        }
        // TODO: fully implement keepInvalid option
        if ($keepInvalid && isset($this->invalid[$property])) {
            return $this->invalid[$property];
        }
        return null;
    }

    public function getData(): array
    {
        if (! $this->isParsed) {
            $this->parseDocument();
        }
        return $this->data;
    }

    public function getInvalid(string $property)
    {
        // TODO: parseDocument?
        if (isset($this->invalid[$property])) {
            return $this->invalid[$property];
        }
        return null;
    }

    protected function setParsing(): void
    {
        if ($this->isParsing) {
            throw new \LogicException('parsing loop detected');
        }
        if ($this->isParsed) {
            throw new \LogicException('document already parsed');
        }
        $this->isParsing = true;
    }

    protected function setParsed(): void
    {
        if (! $this->isParsing) {
            throw new \LogicException('parsing flag not set');
        }
        $this->isParsing = false;
        $this->isParsed = true;
    }

    protected function setUnusable(): void
    {
        $this->isUsable = false;
    }

    protected function setIndex(?int $index): void
    {
        $this->index = $index;
    }

    protected function getIndex(): int
    {
        return $this->index;
    }

    protected function setArrayValue(string $array, string $property, $value): void
    {
        if (isset($this->$array[$property])) {
            if (is_array($this->$array[$property])) {
                if ($this->index === null) {
                    throw new \LogicException("array present for {$property}, but no index specified");
                }
            } else {
                if ($this->index !== null) {
                    throw new \LogicException("value present for {$property}, but index specified");
                }
            }
        }

        if (($array === 'fixed' || $array === 'invalid') && ! is_scalar($value)) {
            $value = json_encode($value);
        }

        if ($this->index === null) {
            $this->$array[$property] = $value;
        } else {
            if (! isset($this->$array[$property])) {
                $this->$array[$property] = [];
            }
            $this->$array[$property][$this->index] = $value;
        }
    }

    protected function setData(string $property, $value): void
    {
        $this->setArrayValue('data', $property, $value);
    }

    protected function setFixed(string $property, $value): void
    {
        $this->setArrayValue('fixed', $property, $value);
    }

    protected function setInvalid(string $property, $value): void
    {
        $this->setArrayValue('invalid', $property, $value);
    }

    protected function setSpelling(string $property, string $correctedProperty): void
    {
        $this->setArrayValue('spelling', $property, $correctedProperty);
    }

    protected function addFatalError(string $summary, string $message): void
    {
        $this->checkResult->addFatalError($summary, $message);
    }

    protected function addCriticalError(
        string $summary,
        string $message,
        ?string $position,
        ?string $data,
        ?string $hint = null
    ): void {
        $this->checkResult->addCriticalError($summary, $message, $position, $data, $hint);
    }

    protected function addError(
        string $summary,
        string $message,
        ?string $position,
        ?string $data,
        ?string $hint = null
    ): void {
        $this->checkResult->addError($summary, $message, $position, $data, $hint);
    }

    protected function addWarning(
        string $summary,
        string $message,
        ?string $position,
        ?string $data,
        ?string $hint = null
    ): void {
        $this->checkResult->addWarning($summary, $message, $position, $data, $hint);
    }

    protected function addNotice(
        string $summary,
        string $message,
        ?string $position,
        ?string $data,
        ?string $hint = null
    ): void {
        $this->checkResult->addNotice($summary, $message, $position, $data, $hint);
    }
}
