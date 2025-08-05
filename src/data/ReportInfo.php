<?php

/**
 * ReportInfo handles the JSON Report list entries
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

use ubfr\c5tools\Config;
use ubfr\c5tools\traits\CheckedDocument;
use ubfr\c5tools\traits\Checks;
use ubfr\c5tools\traits\Helpers;
use ubfr\c5tools\traits\Parsers;

class ReportInfo implements \ubfr\c5tools\interfaces\CheckedDocument
{
    use CheckedDocument;
    use Parsers;
    use Checks;
    use Helpers;

    protected static array $requiredProperties = [
        // order matters here - Release must be checked first, and Report_ID before Report_Name
        'Release' => 'checkedRelease',
        'Report_ID' => 'checkedReportId',
        'Report_Name' => 'checkedReportName',
        'Report_Description' => 'checkedReportDescription'
    ];

    protected static array $requiredProperties51 = [
        // order matters here - Release must be checked first, and Report_ID before Report_Name
        'Release' => 'checkedRelease',
        'Report_ID' => 'checkedReportId',
        'Report_Name' => 'checkedReportName',
        'Report_Description' => 'checkedReportDescription',
        'First_Month_Available' => 'checkedDate',
        'Last_Month_Available' => 'checkedDate'
    ];

    protected static array $optionalProperties = [
        'Path' => 'checkedPath'
    ];

    protected ?string $reportId;

    public function __construct(\ubfr\c5tools\interfaces\CheckedDocument $parent, string $position, object $document)
    {
        $this->document = $document;
        $this->checkResult = $parent->getCheckResult();
        $this->config = $parent->getConfig();
        $this->format = $parent->getFormat();
        $this->position = $position;

        $this->reportId = null;
    }

    protected function parseDocument(): void
    {
        $this->setParsing();

        $requiredProperties = ($this->config->getRelease() === '5' ? self::$requiredProperties : self::$requiredProperties51);
        $properties = $this->getObjectProperties(
            $this->position,
            'Report',
            $this->document,
            array_keys($requiredProperties),
            array_keys(self::$optionalProperties)
        );

        $config = null;
        foreach (array_merge($requiredProperties, self::$optionalProperties) as $property => $checkedMethod) {
            $position = "{$this->position}.{$property}";
            if (
                in_array($property, [
                    'Release',
                    'Report_Description',
                    'First_Month_Available',
                    'Last_Month_Available'
                ])
            ) {
                $value = $this->$checkedMethod($position, $property, $properties[$property] ?? null);
            } else {
                $value = $this->$checkedMethod($position, $property, $properties[$property] ?? null, $config);
            }
            if ($value !== null) {
                $this->setData($property, $value);
            }
            if ($property === 'Release') {
                if ($value === null) {
                    $this->setParsed();
                    $this->setUnusable();
                    return;
                }
                // TODO: Must Release match the request?
                $config = Config::forRelease($value);
            }
        }

        $this->setParsed();

        $this->checkMonthsAvailable();

        if ($this->reportId === null) {
            $this->setUnusable();
        }

        $this->document = null;
    }

    protected function checkedReportDescription(string $position, string $property, $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->checkedRequiredNonEmptyString($position, $property, $value);
    }

    protected function checkedPath(string $position, string $property, $value, Config $config): ?string
    {
        if ($value === null) {
            return null;
        }

        $path = $this->checkedOptionalNonEmptyString($position, $property, $value, false, true);
        if ($path === null) {
            return null;
        }

        if ($this->reportId === null || ! in_array($this->reportId, $config->getReportIds())) {
            // no Report_ID or Custom Report, no check possible
            return $path;
        }

        $pathForId = '/reports/' . strtolower($this->reportId);
        if ($this->endsWith($pathForId, $path)) {
            return $path;
        }

        $summary = "{$property} value is wrong for Report_ID";
        $message = "{$property} value '{$path}' is wrong for Report_ID '{$this->reportId}'";
        $data = $this->formatData($property, $path);
        $hint = "must be '{$pathForId}'";
        $this->addError($summary, $message, $position, $data, $hint);
        $this->setFixed($property, $path);
        return $pathForId;
    }

    protected function checkMonthsAvailable(): void
    {
        $firstMonthAvailable = $this->get('First_Month_Available');
        $lastMonthAvailable = $this->get('Last_Month_Available');
        if (
            $firstMonthAvailable !== null &&
            $lastMonthAvailable !== null &&
            $lastMonthAvailable < $firstMonthAvailable
        ) {
            $message = "Last_Month_Available is before First_Month_Available";
            $position = "{$this->position}.Last_Month_Available";
            $data = $this->formatData(
                'First_Month_Available/Last_Month_Available',
                "{$firstMonthAvailable}/{$lastMonthAvailable}"
            );
            $this->addError($message, $message, $position, $data);
            $this->setUnusable();
        }
    }
}
