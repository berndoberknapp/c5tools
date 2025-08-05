<?php

/**
 * MemberInfo handles the JSON Member list entries
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

class MemberInfo implements \ubfr\c5tools\interfaces\CheckedDocument
{
    use CheckedDocument;
    use Parsers;
    use Checks;
    use Helpers;

    protected static array $fixProperties = [
        'Institution_Name' => 'Name'
    ];

    protected static array $fixProperties51 = [
        'Name' => 'Institution_Name'
    ];

    protected static array $requiredProperties = [
        'Customer_ID',
        'Name'
    ];

    protected static array $requiredProperties51 = [
        'Customer_ID',
        'Institution_Name'
    ];

    protected static array $optionalProperties = [
        'Requestor_ID',
        'Notes',
        'Institution_ID'
    ];

    public function __construct(\ubfr\c5tools\interfaces\CheckedDocument $parent, string $position, object $document)
    {
        $this->document = $document;
        $this->checkResult = $parent->getCheckResult();
        $this->config = $parent->getConfig();
        $this->format = $parent->getFormat();
        $this->position = $position;
    }

    protected function parseDocument(): void
    {
        $this->setParsing();

        $fixProperties = ($this->config->getRelease() === '5' ? self::$fixProperties : self::$fixProperties51);
        $this->fixObjectProperties($this->position, 'Member', $this->document, $fixProperties);

        $requiredProperties = ($this->config->getRelease() === '5' ? self::$requiredProperties : self::$requiredProperties51);
        $properties = $this->getObjectProperties(
            $this->position,
            'Member',
            $this->document,
            $requiredProperties,
            self::$optionalProperties
        );

        foreach (array_keys($properties) as $property) {
            $position = "{$this->position}.{$property}";
            if ($property === 'Institution_ID') {
                if ($this->config->getRelease() === '5') {
                    $this->parseIdentifierList($position, $property, $properties[$property]);
                } else {
                    $this->parseIdentifierList51($position, $property, $properties[$property]);
                }
            } else {
                $errorIsCritical = ($property === 'Customer_ID');
                $whiteSpaceIsError = ($property === 'Customer_ID' || $property === 'Requestor_ID');
                if (in_array($property, $requiredProperties)) {
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
                if ($value !== null) {
                    $this->setData($property, $value);
                }
            }
        }

        $this->setParsed();

        if ($this->get('Customer_ID') === null) {
            $this->setUnusable();
        }

        $this->document = null;
    }
}
