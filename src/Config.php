<?php

/**
 * Config is the abstract base class for handling the configuration
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools;

use ubfr\c5tools\exceptions\ConfigException;
use ubfr\c5tools\interfaces\CheckedDocument;

abstract class Config
{
    use traits\Helpers;

    protected array $headers;

    protected array $identifiers;

    protected array $attributes;

    protected array $elements;

    protected array $exceptions;

    protected array $reports;

    protected static array $configClassForRelease = [
        '5' => 'ubfr\c5tools\R50Config',
        '5.1' => 'ubfr\c5tools\R51Config'
    ];

    public static string $defaultRelease = '5.1';

    abstract public function getRelease(): string;

    abstract public function getNumberOfHeaderRows(): int;

    abstract public function getDatabaseDataTypes(): array;

    abstract public function getUniqueTitleDataTypes(): array;

    public static function supportedReleases(): array
    {
        // array_keys(self::$configClassForRelease) doesn't work, since '5' is converted to 5
        return [
            '5',
            '5.1'
        ];
    }

    public static function forRelease(string $release): Config
    {
        static $configClasses = [];

        if (! in_array($release, self::supportedReleases(), true)) {
            throw new \InvalidArgumentException("COUNTER Release {$release} invalid/unsupported");
        }

        if (! isset($configClasses[$release])) {
            $configClass = self::$configClassForRelease[$release];
            $configClasses[$release] = new $configClass();
        }

        return $configClasses[$release];
    }

    protected function readConfig(string $configDir): void
    {
        $this->readHeaders($configDir);
        $this->readIdentifiers($configDir);
        $this->readAttributes($configDir);
        $this->readElements($configDir);
        $this->readExceptions($configDir);
        $this->readReports($configDir . DIRECTORY_SEPARATOR . 'reports');
    }

    protected function readHeaders(string $configDir): void
    {
        $filename = $configDir . DIRECTORY_SEPARATOR . 'headers.php';
        $this->headers = require($filename);
        foreach ($this->headers as $headerName => $headerConfig) {
            if (! isset($headerConfig['check']) && ! isset($headerConfig['parse'])) {
                throw new ConfigException("neither check nor parse present for header {$headerName}");
            }
            if (isset($headerConfig['check']) && isset($headerConfig['parse'])) {
                throw new ConfigException("both check and parse present for header {$headerName}");
            }
        }
    }

    protected function readIdentifiers(string $configDir): void
    {
        $filename = $configDir . DIRECTORY_SEPARATOR . 'identifiers.php';
        $this->identifiers = require($filename);
        foreach ($this->identifiers as $objectName => $identifierTypes) {
            foreach ($identifierTypes as $typeName => $typeConfig) {
                if (! isset($typeConfig['check'])) {
                    throw new ConfigException("check missing for identifier {$objectName}/{$typeName}");
                }
            }
        }
    }

    protected function readAttributes(string $configDir): void
    {
        $filename = $configDir . DIRECTORY_SEPARATOR . 'attributes.php';
        $this->attributes = require($filename);
    }

    protected function readElements(string $configDir): void
    {
        $filename = $configDir . DIRECTORY_SEPARATOR . 'elements.php';
        $this->elements = require($filename);
        foreach ($this->elements as $format => $contextFormatConfig) {
            foreach ($contextFormatConfig as $context => $formatConfig) {
                foreach ($formatConfig as $elementName => $elementConfig) {
                    if (! isset($elementConfig['check']) && ! isset($elementConfig['parse'])) {
                        throw new ConfigException(
                            "neither check nor parse present for {$format} {$context} element {$elementName}"
                        );
                    }
                    if (isset($elementConfig['check']) && isset($elementConfig['parse'])) {
                        throw new ConfigException(
                            "both check and parse present for {$format} {$context} element {$elementName}"
                        );
                    }
                }
            }
        }
    }

    protected function readExceptions(string $configDir): void
    {
        $filename = $configDir . DIRECTORY_SEPARATOR . 'exceptions.php';
        $this->exceptions = require($filename);
    }

    protected function readReports(string $reportsDir): void
    {
        $this->reports = [];
        foreach (new \DirectoryIterator($reportsDir) as $finfo) {
            if (! $finfo->isDot()) {
                $filename = $reportsDir . DIRECTORY_SEPARATOR . $finfo->getFilename();
                $report = require($filename);
                if (! isset($report['ID'])) {
                    throw new ConfigException("ID missing in {$filename}");
                }
                if (isset($report['FullReport']) === isset($report['Attributes'])) {
                    throw new ConfigException('neither FullReport nor Attributes present');
                }
                $id = $report['ID'];
                unset($report['ID']);
                if (isset($this->reports[$id])) {
                    throw new ConfigException("duplicate ID '{$id}' in {$filename}");
                }
                $this->reports[$id] = $report;
            }
        }
    }

    public function isFullReport(string $reportId): bool
    {
        if (! isset($this->reports[$reportId])) {
            throw new \InvalidArgumentException("Report_ID {$reportId} invalid");
        }
        return ! isset($this->reports[$reportId]['FullReport']);
    }

    public function getFullReportId(string $reportId): ?string
    {
        if (! isset($this->reports[$reportId])) {
            throw new \InvalidArgumentException("Report_ID {$reportId} invalid");
        }
        if ($this->isFullReport($reportId)) {
            return null;
        } else {
            return $this->reports[$reportId]['FullReport'];
        }
    }

    public function isPlatformReport(string $reportId): bool
    {
        return ($reportId === 'PR' || $this->getFullReportId($reportId) === 'PR');
    }

    public function isDatabaseReport(string $reportId): bool
    {
        return ($reportId === 'DR' || $this->getFullReportId($reportId) === 'DR');
    }

    public function isTitleReport(string $reportId): bool
    {
        return ($reportId === 'TR' || $this->getFullReportId($reportId) === 'TR');
    }

    public function isItemReport(string $reportId): bool
    {
        return ($reportId === 'IR' || $this->getFullReportId($reportId) === 'IR');
    }

    public function getReportHeaders(string $format): array
    {
        if ($format !== CheckedDocument::FORMAT_JSON && $format !== CheckedDocument::FORMAT_TABULAR) {
            throw new \LogicException("report format {$format} invalid");
        }

        $headers = [];
        foreach ($this->headers as $headerName => $headerConfig) {
            if ($format === CheckedDocument::FORMAT_JSON) {
                if (! isset($headerConfig[CheckedDocument::FORMAT_JSON])) {
                    continue;
                }
                $header = [
                    'required' => ($headerConfig[CheckedDocument::FORMAT_JSON] === 'required')
                ];
            } else {
                if (! isset($headerConfig['row'])) {
                    continue;
                }
                $row = $headerConfig['row'];
                if (! is_int($row) || $row < 1 || $row > $this->getNumberOfHeaderRows()) {
                    throw new ConfigException("row number {$row} invalid");
                }
                $header = [
                    'row' => $headerConfig['row']
                ];
            }
            if (isset($headerConfig['check'])) {
                $header['check'] = $headerConfig['check'];
            }
            if (isset($headerConfig['parse'])) {
                $header['parse'] = $headerConfig['parse'];
            }
            $headers[$headerName] = $header;
        }

        return $headers;
    }

    public function getTabularHeaderCell(string $header): string
    {
        if (! isset($this->headers[$header]) || ! isset($this->headers[$header]['row'])) {
            throw new \InvalidArgumentException("Header {$header} invalid");
        }

        return 'B' . $this->headers[$header]['row'];
    }

    public function getReportIds(): array
    {
        return array_keys($this->reports);
    }

    public function getReportName(string $reportId): string
    {
        if (! isset($this->reports[$reportId])) {
            throw new \InvalidArgumentException("Report_ID {$reportId} invalid");
        }
        if (! isset($this->reports[$reportId]['Name'])) {
            throw new ConfigException("Name missing for report {$reportId}");
        }
        return $this->reports[$reportId]['Name'];
    }

    public function getReportIdForName(string $reportName): ?string
    {
        $reportName = $this->fuzzy($reportName);
        foreach ($this->reports as $reportId => $report) {
            if ($this->fuzzy($report['Name']) === $reportName) {
                return $reportId;
            }
        }
        return null;
    }

    public function getIdentifiers(string $object, string $format): array
    {
        if (! isset($this->identifiers[$object])) {
            throw new ConfigException("no identifiers for {$object}");
        }

        $identifiers = [];
        foreach ($this->identifiers[$object] as $identifierName => $identifierConfig) {
            if (! isset($identifierConfig[$format]) || $identifierConfig[$format] !== false) {
                $identifiers[$identifierName] = $identifierConfig;
            }
        }
        return $identifiers;
    }

    public function getExceptionForCode(int $code): ?array
    {
        if (isset($this->exceptions[$code])) {
            return array_merge([
                'Code' => $code
            ], $this->exceptions[$code]);
        }
        return null;
    }

    public function getExceptionForMessage(string $message): ?array
    {
        $message = $this->fuzzy($message);
        foreach ($this->exceptions as $code => $exception) {
            if ($this->fuzzy($exception['Message']) === $message) {
                return array_merge([
                    'Code' => $code
                ], $exception);
            }
        }
        return null;
    }

    public function getReportAttributes(string $reportId, string $format): array
    {
        if (! isset($this->reports[$reportId])) {
            throw new \InvalidArgumentException("Report_ID {$reportId} invalid");
        }
        if (! $this->isFullReport($reportId)) {
            throw new \InvalidArgumentException("Report_ID {$reportId} invalid, not a Master Report");
        }
        if ($format !== CheckedDocument::FORMAT_JSON && $format !== CheckedDocument::FORMAT_TABULAR) {
            throw new \LogicException("report format {$format} invalid");
        }
        if (! isset($this->reports[$reportId]['Attributes'])) {
            throw new ConfigException("Attributes missing for {$reportId}");
        }

        $attributes = [];
        foreach ($this->reports[$reportId]['Attributes'] as $key => $value) {
            if (is_array($value)) {
                $attributeName = $key;
                $attributeConfig = [
                    'values' => $value
                ];
            } else {
                $attributeName = $value;
                $attributeConfig = [];
            }
            if (! (isset($this->attributes[$attributeName]))) {
                throw new ConfigException("attribute name {$attributeName} invalid");
            }
            if (
                ! isset($this->attributes[$attributeName][$format]) ||
                $this->attributes[$attributeName][$format] === true
            ) {
                $attributes[$attributeName] = array_merge($attributeConfig, $this->attributes[$attributeName]);
            }
        }

        return $attributes;
    }

    public function isAttributesToShow(string $reportId, string $format, string $element): bool
    {
        if (! $this->isFullReport($reportId)) {
            return false;
        }
        $reportAttributes = $this->getReportAttributes($reportId, $format);
        if (
            ! isset($reportAttributes['Attributes_To_Show']) ||
            ! isset($reportAttributes['Attributes_To_Show']['values'])
        ) {
            throw new ConfigException("Attributes_To_Show or values missing");
        }
        return in_array($element, $reportAttributes['Attributes_To_Show']['values']);
    }

    public function getFullReportAttributes(
        string $reportId,
        string $format,
        bool $excludeCommonExtensions = true
    ): array {
        if (! isset($this->reports[$reportId])) {
            throw new \InvalidArgumentException("Report_ID {$reportId} invalid");
        }
        if (! $this->isFullReport($reportId)) {
            throw new \InvalidArgumentException("Report_ID {$reportId} invalid, not a Master Report");
        }
        if (! isset($this->reports[$reportId]['Attributes'])) {
            throw new ConfigException("Attributes missing for {$reportId}");
        }
        if (! isset($this->reports[$reportId]['Attributes']['Attributes_To_Show'])) {
            throw new ConfigException("Attributes_To_Show missing for {$reportId}");
        }

        $attributesToShow = [];
        foreach ($this->reports[$reportId]['Attributes']['Attributes_To_Show'] as $attributeToShow) {
            if ($excludeCommonExtensions && $this->isCommonExtension($reportId, $format, $attributeToShow)) {
                continue;
            }
            $attributesToShow[] = $attributeToShow;
        }

        $attributes = [
            'Attributes_To_Show' => ($this->getRelease() === '5' ? implode('|', $attributesToShow) : $attributesToShow)
        ];
        if ($reportId === 'IR') {
            $attributes['Include_Parent_Details'] = 'True';
            $attributes['Include_Component_Details'] = 'True';
        }

        return $attributes;
    }

    public function getReportFilters(string $reportId, bool $excludeDefaultsForDerivedReport = true): array
    {
        if (! isset($this->reports[$reportId])) {
            throw new \InvalidArgumentException("Report_ID {$reportId} invalid");
        }

        $filters = [];
        foreach ($this->reports[$reportId]['Filters'] as $key => $value) {
            if (is_array($value)) {
                $filterName = $key;
                $filterConfig = [
                    'multi' => true,
                    'values' => $value
                ];
                if ($this->isFullReport($reportId)) {
                    $filterConfig['default'] = 'All';
                } else {
                    $fullReportId = $this->reports[$reportId]['FullReport'];
                    if (! isset($this->reports[$fullReportId])) {
                        throw new ConfigException("Report {$fullReportId} invalid");
                    }
                    $fullReportFilters = $this->reports[$fullReportId]['Filters'];
                    if (! isset($fullReportFilters[$filterName])) {
                        throw new ConfigException("filter {$filterName} missing for {$fullReportId}");
                    }
                    $fullReportValue = $fullReportFilters[$filterName];
                    if (array_diff($value, $fullReportValue) === array_diff($fullReportValue, $value)) {
                        if ($excludeDefaultsForDerivedReport) {
                            continue;
                        } else {
                            $filterConfig['default'] = 'All';
                        }
                    }
                }
            } else {
                $filterName = $value;
                if ($filterName === 'YOP') {
                    $filterConfig = [
                        'multi' => true
                    ];
                } else {
                    $filterConfig = [];
                }
            }
            $filters[$filterName] = $filterConfig;
        }

        return $filters;
    }

    protected function getElements(string $reportId, string $format, string $context, array $attributesToShow): array
    {
        if (! isset($this->reports[$reportId])) {
            throw new \InvalidArgumentException("Report_ID {$reportId} invalid");
        }
        if (! isset($this->reports[$reportId]['Elements'])) {
            throw new ConfigException("Elements missing for {$reportId}");
        }
        if ($format !== CheckedDocument::FORMAT_JSON && $format !== CheckedDocument::FORMAT_TABULAR) {
            throw new \LogicException("report format {$format} invalid");
        }
        if (! isset($this->elements[$format])) {
            throw new ConfigException("Elements missing for {$format}");
        }
        if (! isset($this->elements[$format][$context])) {
            throw new ConfigException("Elements missing for {$format} {$context}");
        }

        $elements = [];
        foreach ($this->elements[$format][$context] as $elementName => $elementConfig) {
            if (
                $context === 'item' && ! in_array($elementName, $this->reports[$reportId]['Elements']) &&
                ! in_array($elementName, $attributesToShow)
            ) {
                continue;
            }
            if (
                $context === 'parent' && isset($this->reports[$format]['Parent']) &&
                ! in_array($elementName, $this->reports[$format]['Parent'])
            ) {
                continue;
            }

            if (isset($elementConfig['check'])) {
                $elements[$elementName] = [
                    'check' => $elementConfig['check']
                ];
            }
            if (isset($elementConfig['parse'])) {
                $elements[$elementName] = [
                    'parse' => $elementConfig['parse']
                ];
            }
            $elements[$elementName]['attribute'] = $elementConfig['attribute'] ?? false;
            $elements[$elementName]['metadata'] = $elementConfig['metadata'] ?? false;
            $elements[$elementName]['required'] = $elementConfig['required'] ?? false;

            if ($elementName === 'Item_Parent' && isset($this->reports[$reportId]['Parent'])) {
                // Item_Parent is required for IR_A1 which is indicated by the Parent configuration
                $elements[$elementName]['required'] = true;
            }
        }

        return $elements;
    }

    public function getItemElements(string $reportId, string $format, array $attributesToShow): array
    {
        return $this->getElements($reportId, $format, 'item', $attributesToShow);
    }

    public function getJsonParentElements(string $reportId): array
    {
        $elements = $this->getElements($reportId, 'json', 'parent', []);

        if (isset($this->reports[$reportId]['Parent'])) {
            foreach (array_keys($elements) as $elementName) {
                if (! in_array($elementName, $this->reports[$reportId]['Parent'])) {
                    unset($elements[$elementName]);
                }
            }
        }

        return $elements;
    }

    public function getJsonComponentElements(string $reportId): array
    {
        return $this->getElements($reportId, 'json', 'component', []);
    }

    public function isCommonExtension(string $reportId, string $format, string $elementName): bool
    {
        if (! isset($this->reports[$reportId])) {
            throw new \InvalidArgumentException("Report_ID {$reportId} invalid");
        }
        if (! isset($this->reports[$reportId]['Elements'])) {
            throw new ConfigException("Elements missing for {$reportId}");
        }
        if ($format !== CheckedDocument::FORMAT_JSON && $format !== CheckedDocument::FORMAT_TABULAR) {
            throw new \LogicException("report format {$format} invalid");
        }
        if (! isset($this->elements[$format])) {
            throw new ConfigException("Elements missing for {$format}");
        }
        if (! isset($this->elements[$format]['item'])) {
            throw new ConfigException("Elements missing for {$format} item");
        }

        if (isset($this->elements[$format]['item'][$elementName])) {
            $elementConfig = $this->elements[$format]['item'][$elementName];
            if (isset($elementConfig['extension']) && $elementConfig['extension'] === true) {
                return true;
            }
        }
        return false;
    }
}
