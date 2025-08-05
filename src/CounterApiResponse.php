<?php

/**
 * CounterApiResponse creates a JSON {@Document} from a COUNTER Release 5.x API response
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools;

use Psr\Http\Message\ResponseInterface;
use ubfr\c5tools\exceptions\InvalidCounterApiResponseException;

class CounterApiResponse extends Document
{
    protected ResponseInterface $httpResponse;

    public function __construct(string $url, ResponseInterface $httpResponse)
    {
        $this->httpResponse = $httpResponse;

        try {
            $this->jsonFromBuffer((string) $this->httpResponse->getBody());
        } catch (\InvalidArgumentException $e) {
            throw new InvalidCounterApiResponseException($e->getMessage(), $url, $this->httpResponse);
        }
    }

    public function getHttpResponse(): ResponseInterface
    {
        return $this->httpResponse;
    }
}
