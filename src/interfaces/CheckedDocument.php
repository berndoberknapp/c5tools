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

namespace ubfr\c5tools\interfaces;

use ubfr\c5tools\CheckResult;
use ubfr\c5tools\Config;

interface CheckedDocument
{
    public const FORMAT_JSON = 'json';

    public const FORMAT_TABULAR = 'tabular';

    public function debug(int $level, ?string $property): void;

    public function isParsed(): bool;

    public function isUsable(): bool;

    public function isFixed(): bool;

    public function isInvalid(): bool;

    public function getCheckResult(): CheckResult;

    public function getConfig(): Config;

    public function getFormat(): string;

    public function get(string $property, bool $keepInvalid);

    public function getData(): array;
}
