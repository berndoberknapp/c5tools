<?php

/**
 * ReportAttributeList handles JSON COUNTER R5 Report Attribute lists
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

class ReportAttributeList extends NameValueList
{
    protected string $reportId;

    protected array $attributesConfig;

    public function __construct(\ubfr\c5tools\interfaces\CheckedDocument $parent, string $position, $document)
    {
        $this->reportId = $parent->getReportId();
        $this->attributesConfig = $parent->getConfig()->getReportAttributes($this->reportId, $parent->getFormat());

        parent::__construct($parent, $position, $document, 'Report_Attributes', $this->attributesConfig);
    }

    public function asArray(): array
    {
        $reportAttributes = [];
        foreach ($this->getData() as $key => $values) {
            if (isset($this->attributesConfig[$key]['multi'])) {
                $reportAttributes[$key] = $values;
            } else {
                $reportAttributes[$key] = $values[0];
            }
        }

        return $reportAttributes;
    }

    protected function parseDocument(): void
    {
        $this->setParsing();

        parent::parseDocument();

        foreach ($this->values as $name => $values) {
            if (! empty($values)) {
                $this->setData($name, $values);
            }
        }

        $this->setParsed();

        if (empty($this->getData())) {
            $this->setUnusable();
        }
    }

    protected function getValuesString(string $name, array $values): string
    {
        $extensions = [];
        if ($name === 'Attributes_To_Show') {
            foreach ($values as $index => $value) {
                if ($this->config->isCommonExtension($this->reportId, $this->getFormat(), $value)) {
                    $extensions[] = $value;
                    unset($values[$index]);
                }
            }
        }

        $result = "'" . implode("', '", $values) . "'";
        if (! empty($extensions)) {
            $result .= ' and common extension';
            if (count($extensions) > 1) {
                $result .= 's';
            }
            $result .= " '" . implode("', '", $extensions) . "'";
        }

        return $result;
    }
}
