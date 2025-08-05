<?php

/**
 * CounterApiRequest builds, checks and executes COUNTER Release 5.x API requests
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use ubfr\c5tools\exceptions\InvalidCounterApiRequestException;
use ubfr\c5tools\exceptions\FailedCounterApiRequestException;
use ubfr\c5tools\interfaces\CheckedDocument;

class CounterApiRequest
{
    use traits\Helpers;

    protected Config $config;

    protected string $baseUrl;

    protected string $path;

    protected bool $checkRequest;

    protected array $checkRequestResult;

    protected array $httpRequestOptions;

    protected array $credentials;

    protected array $filters;

    protected array $attributes;

    public function __construct(string $baseUrl, bool $checkRequest = true)
    {
        $this->baseUrl = $baseUrl;
        if (substr($baseUrl, - 1) !== '/') {
            $this->baseUrl .= '/';
        }
        $this->path = '';
        $this->config = Config::forRelease(strpos($this->baseUrl, '/r51/') !== false ? '5.1' : '5');

        $this->checkRequest = $checkRequest;
        $this->checkRequestResult = [];

        $this->httpRequestOptions = [
            'cookies' => false, // COUNTER API MUST NOT require cookies
            'connect_timeout' => 30, // 30 seconds connect timeout
            'http_errors' => false, // no exception on 4xx and 5xx errors
            'timeout' => 300 // 5 minutes request timeout
        ];

        $this->credentials = [];
        $this->filters = [];
        $this->attributes = [];

        $this->checkBaseUrl();
    }

    /**
     *
     * @throws \InvalidArgumentException if the url isn't a valid URL
     */
    public static function fromUrl(string $url, bool $checkRequest = true): CounterApiRequest
    {
        $components = parse_url($url);
        if (
            $components === false || ! isset($components['scheme']) || ! isset($components['host']) ||
            ! isset($components['path'])
        ) {
            throw new \InvalidArgumentException("url {$url} is unusable");
        }

        $matches = [];
        if (
            preg_match('#(.*/)(reports/[^/]+)$#', $components['path'], $matches) ||
            preg_match('#(.*/)(members|reports|status)$#', $components['path'], $matches)
        ) {
            $basePath = $matches[1];
            $path = $matches[2];
        } else {
            throw new \InvalidArgumentException("url {$url} is unusable");
        }

        $baseUrl = $components['scheme'] . '://' . $components['host'] .
            (isset($components['port']) ? ':' . $components['port'] : '') . $basePath;
        $counterApiRequest = new CounterApiRequest($baseUrl, $checkRequest);
        $counterApiRequest->setPath($path);

        $reportId = $counterApiRequest->getReportId();
        if (
            $reportId !== null && in_array($reportId, $counterApiRequest->config->getReportIds()) &&
            $counterApiRequest->config->isFullReport($reportId)
        ) {
            $reportAttributes = array_keys(
                $counterApiRequest->config->getReportAttributes($reportId, CheckedDocument::FORMAT_JSON)
            );
        } else {
            $reportAttributes = [];
        }

        $parameterValues = [];
        parse_str($components['query'] ?? '', $parameterValues);
        foreach ($parameterValues as $parameter => $value) {
            if (
                $counterApiRequest->inArrayFuzzy($parameter, [
                    'customer_id',
                    'requestor_id',
                    'api_key'
                ])
            ) {
                $counterApiRequest->credentials[$parameter] = $value;
            } elseif ($counterApiRequest->inArrayFuzzy($parameter, $reportAttributes)) {
                $counterApiRequest->attributes[$parameter] = $value;
            } else {
                $counterApiRequest->filters[$parameter] = $value;
            }
        }

        $counterApiRequest->checkRequest();

        return $counterApiRequest;
    }

    public function getCheckRequestResult(): array
    {
        return $this->checkRequestResult;
    }

    /**
     *
     * @throws \InvalidArgumentException if the baseUrl isn't a valid COUNTER API base URL
     */
    protected function checkBaseUrl(): void
    {
        $components = parse_url($this->baseUrl);
        if ($components === false) {
            throw new \InvalidArgumentException("baseUrl {$this->baseUrl} is unusable");
        }
        if (! isset($components['scheme'])) {
            throw new \InvalidArgumentException("baseUrl {$this->baseUrl} is invalid, scheme is missing");
        }
        if ($components['scheme'] !== 'http' && $components['scheme'] !== 'https') {
            throw new \InvalidArgumentException("baseUrl {$this->baseUrl} is invalid, scheme must be 'http' or 'https'");
        }
        if (! isset($components['host'])) {
            throw new \InvalidArgumentException("baseUrl {$this->baseUrl} is invalid, host is missing");
        }
        if (isset($components['query'])) {
            throw new \InvalidArgumentException("baseUrl {$this->baseUrl} is invalid, contains query");
        }
        if (isset($components['fragment'])) {
            throw new \InvalidArgumentException("baseUrl {$this->baseUrl} is invalid, contains fragment");
        }
    }

    public function setPath(string $path): void
    {
        while (substr($path, 0, 1) === '/') {
            $path = substr($path, 1);
        }
        while (substr($path, - 1) === '/') {
            $path = substr($path, 0, - 1);
        }
        if ($path === '') {
            throw new \InvalidArgumentException("path is empty");
        }
        $this->path = $path;
    }

    public function setHttpRequestOptions(array $httpRequestOptions): void
    {
        $this->httpRequestOptions = array_merge($this->httpRequestOptions, $httpRequestOptions);
    }

    public function setCustomerId(string $customerId): void
    {
        $this->credentials['customer_id'] = $customerId;
    }

    public function setApiKey(string $apiKey): void
    {
        $this->credentials['api_key'] = $apiKey;
    }

    public function setRequestorId(string $requestorId): void
    {
        $this->credentials['requestor_id'] = $requestorId;
    }

    public function setFilters(array $filters): void
    {
        foreach ($filters as $key => $value) {
            if (! is_string($key) || $key === '') {
                throw new \InvalidArgumentException("filter keys must be non-empty strings");
            }
            if (! is_string($value)) {
                throw new \InvalidArgumentException("filter values must be strings");
            }
        }
        $this->filters = $filters;
    }

    public function setAttributes(array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            if (! is_string($key) || $key === '') {
                throw new \InvalidArgumentException("attribute keys must be non-empty strings");
            }
            if (is_array($value)) {
                $attributes[$key] = implode('|', $value);
            } elseif (! is_string($value)) {
                throw new \InvalidArgumentException("attribute values must be strings or arrays");
            }
        }
        $this->attributes = $attributes;
    }

    public function doRequest(): CounterApiResponse
    {
        $this->checkRequest();

        $httpClient = new HttpClient(array_merge([
            'base_uri' => $this->baseUrl
        ], $this->httpRequestOptions));

        try {
            $httpResponse = $httpClient->request('GET', $this->path, [
                'query' => $this->getRequestParameters()
            ]);
        } catch (ConnectException $e) {
            throw new FailedCounterApiRequestException(
                $e->getMessage(),
                $this->getRequestUrl(),
                $e->getRequest(),
                null,
                $e->getCode(),
                $e
            );
        } catch (RequestException $e) {
            throw new FailedCounterApiRequestException(
                $e->getMessage(),
                $this->getRequestUrL(),
                $e->getRequest(),
                $e->getResponse(),
                $e->getCode(),
                $e
            );
        }

        return new CounterApiResponse($this->getRequestUrl(), $httpResponse);
    }

    public function getReportId(): ?string
    {
        $matches = [];
        if (preg_match('|^reports/(.+)$|', $this->path, $matches)) {
            return strtoupper($matches[1]);
        }
        return null;
    }

    public function getRelease(): string
    {
        return $this->config->getRelease();
    }

    public function getCredentials()
    {
        return $this->credentials;
    }

    public function getFilters()
    {
        return $this->filters;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    protected function getRequestParameters(): array
    {
        if ($this->config->getRelease() === '5.1' && $this->path === 'status') {
            return array_merge($this->filters, $this->attributes);
        } else {
            return array_merge($this->credentials, $this->filters, $this->attributes);
        }
    }

    public function getRequestUrl(): string
    {
        if ($this->path === '') {
            throw new \LogicException('path not set');
        }

        $encodedRequestParameters = [];
        foreach ($this->getRequestParameters() as $key => $value) {
            $encodedRequestParameters[] = rawurlencode($key) . '=' . rawurlencode($value);
        }
        return $this->baseUrl . $this->path . '?' . implode('&', $encodedRequestParameters);
    }

    protected function checkRequest(): void
    {
        $this->checkRequestResult = [];

        if (! $this->startsWith('https', $this->baseUrl)) {
            $this->checkRequestResult[] = 'The COUNTER API MUST be implemented using TLS (HTTPS).';
        }
        if ($this->path !== strtolower($this->path)) {
            $this->checkRequestResult[] = "Spelling of path {$this->path} is wrong, must be lower case";
            $this->setPath(strtolower($this->path));
        }

        if ($this->config->getRelease() !== '5.1' || $this->path !== 'status') {
            $this->checkCredentials();
        }

        $matches = [];
        if ($this->path === 'status') {
            $this->checkStatusListRequest();
        } elseif ($this->path === 'members') {
            $this->checkMemberListRequest();
        } elseif ($this->path === 'reports') {
            $this->checkReportListRequest();
        } elseif (preg_match('|^reports/(.+)$|', $this->path, $matches)) {
            $this->checkReportRequest(strtoupper($matches[1]));
        } else {
            $this->checkRequestResult[] = "Path {$this->path} unknown";
        }

        if ($this->checkRequest && ! empty($this->checkRequestResult)) {
            throw new InvalidCounterApiRequestException(
                implode('. ', $this->checkRequestResult) . '.',
                $this->getRequestUrl()
            );
        }
    }

    protected function checkCredentials(): void
    {
        $this->checkParameters('credentials', [
            'customer_id'
        ], [
            'requestor_id',
            'api_key'
        ]);
    }

    protected function checkStatusListRequest(): void
    {
        $this->checkParameters('filters', [], [
            'platform'
        ]);
        $this->checkParameters('attributes', [], []);
    }

    protected function checkMemberListRequest(): void
    {
        $this->checkParameters('filters', [], [
            'platform'
        ]);
        $this->checkParameters('attributes', [], []);
    }

    protected function checkReportListRequest(): void
    {
        $this->checkParameters('filters', [], [
            'platform',
            'search'
        ]);
        $this->checkParameters('attributes', [], []);
    }

    protected function checkReportRequest($reportId): void
    {
        if (in_array($reportId, $this->config->getReportIds())) {
            if ($this->config->isFullReport($reportId)) {
                $this->checkFullReport($reportId);
            } else {
                $this->checkDerivedReport($reportId);
            }
            $this->checkDates();
        } else {
            $this->checkRequestResult[] = "Report_ID {$reportId} unknown";
        }
    }

    protected function checkFullReport($reportId)
    {
        $requiredFilters = [];
        $optionalFilters = [];
        foreach ($this->config->getReportFilters($reportId) as $filterName => $filterConfig) {
            if ($filterName === 'Begin_Date' || $filterName === 'End_Date') {
                $requiredFilters[strtolower($filterName)] = $filterConfig;
            } else {
                $optionalFilters[strtolower($filterName)] = $filterConfig;
            }
        }
        $this->checkParameters('filters', array_keys($requiredFilters), array_keys($optionalFilters));

        $optionalAttributes = [];
        foreach ($this->config->getReportAttributes($reportId, CheckedDocument::FORMAT_JSON) as $attributeName => $attributeConfig) {
            $optionalAttributes[strtolower($attributeName)] = $attributeConfig;
        }
        $this->checkParameters('attributes', [], array_keys($optionalAttributes));
    }

    protected function checkDerivedReport($reportId)
    {
        $this->checkParameters('filters', [
            'begin_date',
            'end_date'
        ], [
            'platform'
        ]);
    }

    protected function checkParameters(string $parameters, array $requiredParameters, array $optionalParameters)
    {
        $allowedParameters = array_merge($requiredParameters, $optionalParameters);

        foreach ($this->$parameters as $parameter => $value) {
            if (! in_array($parameter, $allowedParameters)) {
                $correctedParameter = $this->inArrayFuzzy($parameter, $allowedParameters);
                if ($correctedParameter !== null) {
                    $this->checkRequestResult[] = "Spelling of parameter {$parameter} is wrong, must be spelled {$correctedParameter}";
                    if ($this->checkRequest) {
                        unset($this->$parameters[$parameter]);
                        $this->$parameters[$correctedParameter] = $value;
                        $parameter = $correctedParameter;
                    }
                } else {
                    $this->checkRequestResult[] = "Parameter {$parameter} is invalid";
                }
            }
            if ($value === '') {
                $this->checkRequestResult[] = "Parameter {$parameter} value is empty";
            }
        }

        foreach (array_diff($requiredParameters, array_keys($this->$parameters)) as $missingParameter) {
            $this->checkRequestResult[] = "Required parameter {$missingParameter} missing";
        }

        // TODO: check attribute and filter values
    }

    protected function checkDates()
    {
        // TODO: check begin_date and end_date values
    }
}
