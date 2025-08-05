<?php

/**
 * BufferDocument is used for creating {@see Document}s from strings
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools;

use ubfr\c5tools\exceptions\InvalidBufferDocumentException;

class BufferDocument extends Document
{
    public function __construct(string $buffer)
    {
        try {
            $this->jsonFromBuffer($buffer);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidBufferDocumentException($e->getMessage());
        }
    }
}
