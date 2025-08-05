<?php

/**
 * Exception handles COUNTER Exceptions
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

use Psr\Http\Message\ResponseInterface;
use ubfr\c5tools\exceptions\CounterApiException;
use ubfr\c5tools\traits\CheckedDocument;
use ubfr\c5tools\traits\Checks;
use ubfr\c5tools\traits\Helpers;
use ubfr\c5tools\traits\Parsers;

class Exception implements \ubfr\c5tools\interfaces\CheckedDocument
{
    use CheckedDocument;
    use Parsers;
    use Checks;
    use Helpers;

    protected static array $fixProperties = [
        'Number' => 'Code'
    ];

    protected array $requiredProperties = [
        'Code',
        'Message'
    ];

    protected static array $optionalProperties = [
        'Help_URL',
        'Data'
    ];

    protected static array $permittedSeverities = [
        'Fatal',
        'Error',
        'Warning',
        'Debug',
        'Info'
    ];

    protected bool $isCounterApiException;

    protected ResponseInterface $httpResponse;

    public function __construct(\ubfr\c5tools\interfaces\CheckedDocument $parent, string $position, object $document)
    {
        $this->document = $document;
        $this->checkResult = $parent->getCheckResult();
        $this->config = $parent->getConfig();
        $this->format = $parent->getFormat();
        $this->position = $position;

        $this->isCounterApiException = ($parent instanceof CounterApiException);
        if ($this->isCounterApiException) {
            $this->httpResponse = $parent->getHttpResponse();
        }

        if ($this->config->getRelease() === '5') {
            $this->requiredProperties[] = 'Severity';
        }
    }

    public function __toString(): string
    {
        $exception = $this->get('Code') . ': ' . $this->get('Message');
        if ($this->get('Data') !== null) {
            $exception .= ' (' . $this->get('Data') . ')';
        }

        return $exception;
    }

    protected function parseDocument(): void
    {
        $this->setParsing();

        $this->fixObjectProperties($this->position, 'Exception', $this->document, self::$fixProperties);

        $properties = $this->getObjectProperties(
            $this->position,
            'Exception',
            $this->document,
            $this->requiredProperties,
            self::$optionalProperties
        );

        foreach (array_keys($properties) as $property) {
            if ($this->isJson()) {
                $position = ($this->position === '.' ? ".{$property}" : "{$this->position}.{$property}");
            } else {
                $position = $this->position;
            }
            if ($property === 'Code') {
                $value = $this->checkedInteger($position, $property, $properties[$property], true);
            } elseif ($property === 'Severity') {
                $value = $this->checkedPermittedValue(
                    $position,
                    $property,
                    $properties[$property],
                    self::$permittedSeverities,
                    true
                );
            } elseif ($property === 'Help_URL') {
                $value = $this->checkedUrl($position, $property, $properties[$property]);
            } else {
                // Message or Data
                $errorIsCritical = ($property === 'Message');
                $whiteSpaceIsError = $errorIsCritical;
                if (in_array($property, $this->requiredProperties)) {
                    $value = $this->checkedRequiredNonEmptyString(
                        $position,
                        $property,
                        $properties[$property],
                        $errorIsCritical,
                        $whiteSpaceIsError
                    );
                } else {
                    $value = $this->checkedOptionalNonEmptyString(
                        $position,
                        $property,
                        $properties[$property],
                        $errorIsCritical,
                        $whiteSpaceIsError
                    );
                }
            }
            if ($value !== null) {
                $this->setData($property, $value);
            }
        }

        $this->setParsed();

        $this->checkException();

        if ($this->get('Code') === null) {
            $this->setUnusable();
        }
    }

    protected function checkException()
    {
        // if Code is missing try to determine it from Message
        if ($this->get('Code') === null && $this->get('Message') !== null) {
            $exception = $this->config->getExceptionForMessage($this->get('Message'));
            if ($exception !== null) {
                $this->setFixed('Code', '');
                $this->setData('Code', $exception['Code']);
            }
        }

        // no further checks possible without Code
        if ($this->get('Code') === null) {
            return;
        }

        $this->checkExceptionCode();
        $this->checkExceptionHttpCode();
        $this->checkExceptionMessage();
        $this->checkExceptionSeverity();
        $this->checkExceptionData();
    }

    protected function checkExceptionCode()
    {
        $code = $this->get('Code');
        if ($this->isJson()) {
            $position = ($this->position === '.' ? '.Code' : "{$this->position}.Code");
        } else {
            $position = $this->position;
        }
        $data = $this->formatData('Code', $code);
        if ($code < 1000) {
            // custom Exception (Info, Debug or Warning Messages) - only permitted in Reports
            if ($this->isCounterApiException) {
                $message = 'Custom Exceptions with Code 0-999 are only permitted in Reports';
                $this->addError($message, $message, $position, $data);
                $this->setInvalid('Code', $code);
            }
        } elseif ($code === 3000 || $code === 3010) {
            $message = "Exception {$code} is not necessary for the RESTful COUNTER API, therefore it is deprecated and will be removed in Release 5.1";
            $hint = 'COUNTER API clients should use the HTTP status codes instead';
            $this->addNotice($message, $message, $position, $data, $hint);
        } else {
            // other standard Exceptions
            $exception = $this->config->getExceptionForCode($code);
            if ($exception === null) {
                $message = "Exception code {$code} is invalid";
                $hint = 'custom Exceptions with Code > 999 are not permitted';
                $this->addError($message, $message, $position, $data, $hint);
                $this->setInvalid('Code', $code);
            }
        }
    }

    protected function checkExceptionHttpCode()
    {
        $code = $this->get('Code');
        $exception = $this->config->getExceptionForCode($code);
        if ($exception === null) {
            // custom Exceptions either must be in a Report which already checks the HTTP status code
            // or they are invalid and therefore the HTTP status code cannot be checked
            return;
        }

        if ($this->isCounterApiException) {
            $httpCode = $this->httpResponse->getstatusCode();
            if ($exception['HttpCode'] !== $httpCode) {
                $httpReason = $this->httpResponse->getReasonPhrase();
                $summary = 'HTTP status code is wrong for Exception Code';
                $message = "HTTP status code '{$httpCode}' is wrong for Exception Code '{$code}'";
                $data = $this->formatData('HTTP status code', "{$httpCode} ({$httpReason})");
                $hint = "HTTP status code must be '" . $exception['HttpCode'] . "'";
                $this->addError($summary, $message, '.', $data, $hint);
            }
            if ($exception['HttpCode'] === 200) {
                $message = "Exceptions with Code '{$code}' are only permitted in Reports";
                $data = $this->formatData('Code', $code);
                $this->addError($message, $message, $this->position, $data);
                $this->setInvalid('Code', $code);
            }
        } elseif ($exception['HttpCode'] !== 200) {
            $message = "Exceptions with Code '{$code}' are not permitted in Reports";
            $data = $this->formatData('Code', $code);
            $this->addError($message, $message, $this->position, $data);
            $this->setInvalid('Code', $code);
        }
    }

    protected function checkExceptionMessage()
    {
        $code = $this->get('Code');
        $exception = $this->config->getExceptionForCode($code);
        if ($exception === null) {
            // Message can only be checked for standard Exceptions
            return;
        }

        if ($this->get('Message') === null) {
            // fix missing Message (error already reported - required property)
            $this->setFixed('Message', '');
            $this->setData('Message', $exception['Message']);
            return;
        }

        $exceptionMessage = $this->get('Message');
        if ($exception['Message'] === $exceptionMessage) {
            // correct message
            return;
        }

        if ($this->isJson()) {
            $position = ($this->position === '.' ? '.Message' : "{$this->position}.Message");
        } else {
            $position = $this->position;
        }
        $data = $this->formatData('Message', $exceptionMessage);
        if ($this->fuzzy($exception['Message']) === $this->fuzzy($exceptionMessage)) {
            $summary = 'Spelling of Message is wrong';
            $message = "Spelling of Message '{$exceptionMessage}' is wrong";
            $hint = "must be spelled '" . $exception['Message'] . "'";
            $this->addError($summary, $message, $position, $data, $hint);
        } else {
            $summary = 'Message is wrong';
            $message = "Message '{$exceptionMessage}' is wrong";
            $hint = "must be '" . $exception['Message'] . "'";
            $this->addError($summary, $message, $position, $data, $hint);
            // store original Message in Data if possible
            if ($this->get('Data') === null) {
                $this->setData('Data', $exceptionMessage);
            }
        }
        $this->setFixed('Message', $exceptionMessage);
        $this->setData('Message', $exception['Message']);
    }

    protected function checkExceptionSeverity()
    {
        if ($this->isTabular() || $this->get('Severity') === null) {
            return;
        }

        $severity = $this->get('Severity');
        $message = 'Severity is not necessary for the RESTful COUNTER API, therefore it is deprecated and will be removed in Release 5.1';
        if ($this->isJson()) {
            $position = ($this->position === '.' ? '.Severity' : "{$this->position}.Severity");
        } else {
            $position = $this->position;
        }
        $data = $this->formatData('Severity', $severity);
        $hint = 'COUNTER API clients should use the HTTP status codes instead';
        $this->addNotice($message, $message, $position, $data, $hint);

        $code = $this->get('Code');
        if ($code === 0) {
            $permittedSeverities = [
                'Info',
                'Debug'
            ];
        } elseif ($code < 1000) {
            $permittedSeverities = [
                'Warning'
            ];
        } else {
            $exception = $this->config->getExceptionForCode($code);
            if ($exception === null) {
                // Severity can only be checked for standard Exceptions
                return;
            }
            $permittedSeverities = (is_array($exception['Severity']) ? $exception['Severity'] : [
                $exception['Severity']
            ]);
        }
        if (! in_array($severity, $permittedSeverities)) {
            $summary = 'Severity is wrong for Exception Code';
            $message = "Severity '{$severity}' is wrong for Exception Code '{$code}'";
            $hint = "permitted values are '" . implode("', '", $permittedSeverities) . "'";
            $this->addError($summary, $message, $position, $data, $hint);
            $this->setFixed('Severity', $severity);
            $this->setData('Severity', $permittedSeverities[0]);
        }
    }

    protected function checkExceptionData()
    {
        if ($this->get('Data') !== null) {
            // if Data is present there is nothing to check
            return;
        }

        $code = $this->get('Code');
        $exception = $this->config->getExceptionForCode($code);
        if ($exception === null) {
            // Data can only be checked for Standard Exceptions
            return;
        }

        $data = "Exception {$code}";
        if (isset($exception['DataRequired']) && $exception['DataRequired']) {
            $message = "Property 'Data' required for Exception Code {$code} is missing";
            $this->addError($message, $message, $this->position, $data);
        } elseif ($code === 2030 && $this->get('Help_URL') === null) {
            $message = "Property 'Data' or 'Help_URL' required for Exception Code {$code} is missing";
            $this->addError($message, $message, $this->position, $data);
        }
    }
}
