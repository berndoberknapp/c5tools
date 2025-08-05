<?php

/**
 * JsonReportHeader is the main class for parsing and validating JSON COUNTER Report Headers
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

class JsonReportHeader extends ReportHeader
{
    protected function parseDocument(): void
    {
        $this->setParsing();

        $headers = $this->config->getReportHeaders($this->getFormat());
        $requiredProperties = [];
        $optionalProperties = [];
        foreach ($headers as $property => $headerConfig) {
            if ($headerConfig['required']) {
                $requiredProperties[] = $property;
            } else {
                $optionalProperties[] = $property;
            }
        }

        $properties = $this->getObjectProperties(
            $this->position,
            'Report_Header',
            $this->document,
            $requiredProperties,
            $optionalProperties
        );
        // order matters, therefore iterate over headers instead of properties
        foreach ($headers as $property => $headerConfig) {
            $position = "{$this->position}.{$property}";
            if (isset($headerConfig['check'])) {
                $checkMethod = $headerConfig['check'];
                $value = $this->$checkMethod($position, $property, $properties[$property] ?? null);
                if ($value !== null) {
                    $this->setData($property, $value);
                }
            } elseif (isset($properties[$property])) {
                $parseMethod = $headerConfig['parse'];
                $this->$parseMethod($position, $property, $properties[$property]);
            }
        }

        $this->setParsed();

        $this->checkRequiredReportElements($requiredProperties);
        $this->checkOptionalReportElements();
        $this->checkGlobalReport();
        $this->checkException3040();
        $this->checkException3063();

        $this->checkResult->setReportHeader($this);
    }

    public function asCells(): array
    {
        $cells = [];
        $rowNumber = 1;
        foreach (explode("\n", json_encode($this->document, JSON_PRETTY_PRINT)) as $line) {
            $cells["A{$rowNumber}"] = $line;
            $rowNumber++;
        }

        return $cells;
    }
}
