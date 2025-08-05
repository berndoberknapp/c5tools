<?php

/**
 * Helpers is a collection of helper methods used by various classes
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\traits;

use PhpOffice\PhpSpreadsheet\Worksheet\Row;
use ubfr\c5tools\exceptions\ConfigException;

trait Helpers
{
    protected function inArrayFuzzy(string $needle, array $haystack): ?string
    {
        $needle = $this->fuzzy($needle);
        foreach ($haystack as $value) {
            if ($needle === $this->fuzzy($value)) {
                return $value;
            }
        }
        return null;
    }

    protected function fuzzy(string $string): string
    {
        return strtolower(preg_replace('/[\s"“”\'_-]/', '', $string));
    }

    protected function isEmptyObject(object $value): bool
    {
        return (count(get_object_vars($value)) === 0); // TODO: is there a better way to check this?
    }

    /**
     * Convert a COUNTER API parameter name to the corresponding attribute or filter name.
     *
     * @param string $string
     * @return string
     */
    protected function counterCaps(string $string): string
    {
        $string = strtolower($string);
        if ($string === 'item_id') {
            return 'Item_ID';
        } elseif ($string === 'yop') {
            return 'YOP';
        } else {
            return implode('_', array_map('ucfirst', explode('_', $string)));
        }
    }

    protected function startsWith(string $needle, string $haystack): bool
    {
        $needleLength = strlen($needle);
        $haystackLength = strlen($haystack);
        if ($haystackLength < $needleLength) {
            return false;
        }
        return (substr($haystack, 0, $needleLength) === $needle);
    }

    protected function endsWith(string $needle, string $haystack): bool
    {
        $needleLength = strlen($needle);
        $haystackLength = strlen($haystack);
        if ($haystackLength < $needleLength) {
            return false;
        }
        return (substr($haystack, - $needleLength) === $needle);
    }

    protected function hasProperty(object $json, string $property): bool
    {
        $property = $this->fuzzy($property);
        foreach ($json as $key => &$value) {
            if ($this->fuzzy($key) === $property) {
                return true;
            }
        }
        return false;
    }

    protected function getProperty(object $json, string $property)
    {
        $property = $this->fuzzy($property);
        foreach ($json as $key => &$value) {
            if ($this->fuzzy($key) === $property) {
                return $value;
            }
        }
        return null;
    }

    protected function unsetProperty(object $json, string $property): void
    {
        $property = $this->fuzzy($property);
        foreach ($json as $key => &$value) {
            if ($this->fuzzy($key) === $property) {
                unset($json->$key);
                return;
            }
        }
    }

    protected function getRowValues(Row &$row): array
    {
        $rowValues = [];
        foreach ($row->getCellIterator() as $cell) {
            $rowValues[$cell->getColumn()] = $cell->getFormattedValue();
        }
        while (($lastValue = end($rowValues)) !== false) {
            if (trim($lastValue) !== '') {
                break;
            }
            array_pop($rowValues);
        }
        return $rowValues;
    }

    protected function formatData(string $element, $value): string
    {
        return ($element . " '" . (is_scalar($value) ? $value : json_encode($value)) . "'");
    }

    protected function getItemNameElement(string $context): string
    {
        static $report2ItemNameElement = [
            'PR' => 'Platform',
            'DR' => 'Database',
            'TR' => 'Title',
            'IR' => 'Item'
        ];

        if ($context !== 'Item') {
            return 'Item_Name';
        }

        $reportId = $this->reportHeader->getReportId();
        $fullReportId = ($this->config->getFullReportId($reportId) ?? $reportId);
        if (! isset($report2ItemNameElement[$fullReportId])) {
            throw new ConfigException("no ItemNameElement mapping for {$fullReportId}");
        }
        return $report2ItemNameElement[$fullReportId];
    }

    protected function getIsbn13(string $isbn10): string
    {
        $isbn10 = str_replace('-', '', $isbn10);
        if (! preg_match('/^[0-9]{9}[0-9xX]$/', $isbn10)) {
            throw new \InvalidArgumentException("{$isbn10} is no ISBN10");
        }
        $isbn13 = '978' . substr($isbn10, 0, 9);
        $checksum = 0;
        for ($c = 0; $c < 12; $c++) {
            $checksum += substr($isbn13, $c, 1) * (1 + 2 * ($c % 2));
        }
        $checksum = 10 - ($checksum % 10);
        if ($checksum == 10) {
            $checksum = 0;
        }
        return $isbn13 . $checksum;
    }
}
