<?php

/**
 * R51Config handles the configuration for COUNTER Release 5.1
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools;

class R51Config extends Config
{
    public function __construct()
    {
        $this->readConfig(implode(DIRECTORY_SEPARATOR, [
            dirname(__FILE__),
            'config',
            'r51'
        ]));
    }

    public function getRelease(): string
    {
        return '5.1';
    }

    public function getNumberOfHeaderRows(): int
    {
        return 13;
    }

    public function getDatabaseDataTypes(): array
    {
        return [
            'Database_Aggregated',
            'Database_AI',
            'Database_Full'
        ];
    }

    public function getUniqueTitleDataTypes(): array
    {
        return [
            'Book',
            'Reference_Work'
        ];
    }
}
