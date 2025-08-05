<?php

/**
 * JsonDocument is implemented by all classes that handle JSON COUNTER files and API responses
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\interfaces;

interface JsonDocument
{
    public function getJsonString(): string;

    public function getJson();
}
