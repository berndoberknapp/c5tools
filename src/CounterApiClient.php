<?php

/**
 * CounterApiClient performs COUNTER Release 5.x API requests
 *
 * CounterApiClient creates and executes a {@see CounterApiRequest} and returns the appropriate object for the
 * {@see CounterApiResponse}, either {@see MemberList}, {@see ReportList}, {@see StatusList}, {@see JsonReport},
 * or {@see CounterApiException}. In case of a malformed requests an {@see InvalidCounterApiRequestException} is
 * thrown, in case of a failed request a {@see FailedCounterApiRequestException), and in case of an invalid
 * response an {@see InvalidCounterApiResponseException}.
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools;

use ubfr\c5tools\exceptions\InvalidCounterApiResponseException;
use ubfr\c5tools\exceptions\CounterApiException;

class CounterApiClient
{
    protected CounterApiRequest $request;

    public function __construct(string $baseUrl)
    {
        $this->request = new CounterApiRequest($baseUrl);
    }

    public function setRequestorId(string $requestorId): void
    {
        $this->request->setRequestorId($requestorId);
    }

    public function setApiKey(string $apiKey): void
    {
        $this->request->setApiKey($apiKey);
    }

    public function getMemberList(string $customerId, string $platform = ''): MemberList
    {
        $this->request->setPath('members');
        $this->request->setCustomerId($customerId);
        $filters = [];
        if ($platform !== '') {
            $filters['platform'] = $platform;
        }
        $this->request->setFilters($filters);
        $this->request->setAttributes([]);

        $response = $this->request->doRequest();
        if ($response->isException() || $response->isReportWithException()) {
            throw new CounterApiException($response, $this->request);
        } elseif ($response->isMemberList()) {
            return new MemberList($response, $this->request);
        } else {
            throw new InvalidCounterApiResponseException(
                'response is unusuable',
                $this->getRequestUrl(),
                $response->getHttpResponse()
            );
        }
    }

    public function getReportList(string $customerId, string $platform = '', string $search = ''): ReportList
    {
        $this->request->setPath('reports');
        $this->request->setCustomerId($customerId);
        $filters = [];
        if ($platform !== '') {
            $filters['platform'] = $platform;
        }
        if ($search !== '') {
            $filters['search'] = $search;
        }
        $this->request->setFilters($filters);
        $this->request->setAttributes([]);

        $response = $this->request->doRequest();
        if ($response->isException() || $response->isReportWithException()) {
            throw new CounterApiException($response, $this->request);
        } elseif ($response->isReportList()) {
            return new ReportList($response, $this->request);
        } else {
            throw new InvalidCounterApiResponseException(
                'response is unusuable',
                $this->getRequestUrl(),
                $response->getHttpResponse()
            );
        }
    }

    /**
     * Call the COUNTER API method status to get the COUNTER API server status
     *
     * @param string $customerId
     *            The customer_id for the request (required).
     * @param string $platform
     *            The platform filter (optional).
     *
     * @throws \ubfr\c5tools\exception\InvalidCounterApiRequestException if checkRequest is true and the baseUrl or the
     *         requests parameters aren't valid
     * @throws \ubfr\c5tools\exception\FailedCounterApiRequestException if the connection or request fails completely, for
     *         example because the hostname cannot be resolved
     * @throws \ubfr\c5tools\exception\InvalidCounterApiResponseException if the response is no JSON or unusable
     * @throws \ubfr\c5tools\exception\CounterApiException if the response is a COUNTER API exception
     */
    public function getStatusList(string $customerId, string $platform = ''): StatusList
    {
        $this->request->setPath('status');
        $this->request->setCustomerId($customerId);
        $filters = [];
        if ($platform !== '') {
            $filters['platform'] = $platform;
        }
        $this->request->setFilters($filters);
        $this->request->setAttributes([]);

        $response = $this->request->doRequest();
        if ($response->isException() || $response->isReportWithException()) {
            throw new CounterApiException($response, $this->request);
        } elseif ($response->isStatusList()) {
            return new StatusList($response, $this->request);
        } else {
            throw new InvalidCounterApiResponseException(
                'response is unusuable',
                $this->getRequestUrl(),
                $response->getHttpResponse()
            );
        }
    }

    public function getReport(string $reportId, string $customerId, array $filters, array $attributes = []): JsonReport
    {
        $this->request->setPath("reports/{$reportId}");
        $this->request->setCustomerId($customerId);
        $this->request->setFilters($filters);
        $this->request->setAttributes($attributes);

        $response = $this->request->doRequest();
        if ($response->isException()) {
            throw new CounterApiException($response, $this->request);
        } elseif ($response->isReport()) {
            return new JsonReport($response, $this->request);
        } else {
            throw new InvalidCounterApiResponseException(
                'response is unusuable',
                $this->getRequestUrl(),
                $response->getHttpResponse()
            );
        }
    }

    public function getRequestUrl(): string
    {
        return $this->request->getRequestUrl();
    }
}
