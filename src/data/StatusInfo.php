<?php

/**
 * StatusInfo handles the JSON Status list entries
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

class StatusInfo implements \ubfr\c5tools\interfaces\CheckedDocument
{
    use CheckedDocument;
    use Parsers;
    use Checks;
    use Helpers;

    protected static array $requiredProperties = [
        'Service_Active'
    ];

    protected static array $optionalProperties = [
        'Description',
        'Registry_URL',
        'Note',
        'Alerts'
    ];

    protected static array $optionalProperties51 = [
        'Description',
        'Registry_Record',
        'Note',
        'Alerts'
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

        $properties = $this->getObjectProperties(
            $this->position,
            'Status',
            $this->document,
            self::$requiredProperties,
            $this->config->getRelease() === '5' ? self::$optionalProperties : self::$optionalProperties51
        );

        foreach (array_keys($properties) as $property) {
            $position = "{$this->position}.{$property}";
            if ($property === 'Alerts') {
                if (! $this->isNonEmptyArray($position, $property, $properties[$property])) {
                    continue;
                }
                $alertList = new AlertList($this, $position, $properties[$property]);
                if ($alertList->isUsable()) {
                    $this->setData($property, $alertList);
                    if ($alertList->isFixed()) {
                        $this->setFixed($property, $properties[$property]);
                    }
                } else {
                    $this->setInvalid($property, $properties[$property]);
                }
            } else {
                if ($property === 'Description' || $property == 'Note') {
                    $value = $this->checkedOptionalNonEmptyString($position, $property, $properties[$property]);
                } elseif ($property === 'Service_Active') {
                    $value = $this->checkedBoolean($position, $property, $properties[$property]);
                } elseif ($property === 'Registry_URL' || $property === 'Registry_Record') {
                    $value = $this->checkedRegistryUrl($position, $property, $properties[$property]);
                }
                if ($value !== null) {
                    $this->setData($property, $value);
                }
            }
        }

        $this->setParsed();

        if ($this->get('Service_Active') === null) {
            $this->setUnusable();
        }

        $this->document = null;
    }
}
