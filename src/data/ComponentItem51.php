<?php

/**
 * ComponentItem51 is the abstract base class for handling COUNTER R5.1 Component Item list entries
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\data;

use ubfr\c5tools\traits\CheckedDocument;
use ubfr\c5tools\traits\Checks;
use ubfr\c5tools\traits\Helpers;
use ubfr\c5tools\traits\Parsers;
use ubfr\c5tools\traits\MetricTypesPresent;

abstract class ComponentItem51 implements \ubfr\c5tools\interfaces\CheckedDocument
{
    use CheckedDocument;
    use Parsers;
    use Checks;
    use Helpers;
    use MetricTypesPresent;

    protected ReportHeader $reportHeader;

    abstract protected function parseDocument(): void;

    public function __construct(
        \ubfr\c5tools\interfaces\CheckedDocument $parent,
        string $position,
        int $index,
        $document
    ) {
        $this->document = $document;
        $this->checkResult = $parent->getCheckResult();
        $this->config = $parent->getConfig();
        $this->format = $parent->getFormat();
        $this->position = $position;

        $this->reportHeader = $parent->getReportHeader();
    }

    public function getReportHeader(): ReportHeader
    {
        return $this->reportHeader;
    }

    public function getHash(): string
    {
        $hashContext = hash_init('sha256');
        $this->updateHash($hashContext, $this->getJsonMetadata());
        return hash_final($hashContext);
    }

    protected function updateHash(object $hashContext, array $elements, ?string $position = null)
    {
        ksort($elements);
        foreach ($elements as $element => $value) {
            if (is_object($value) || is_array($value)) {
                continue;
            } else {
                $string = ($position === null ? $element : $position . '.' . $element) . ' => ' . $value;
                hash_update($hashContext, mb_strtolower($string));
            }
        }
    }

    protected function getJsonMetadata(): array
    {
        $metadata = [];
        foreach ($this->reportHeader->getJsonComponentElements() as $elementName => $elementConfig) {
            if ($elementConfig['metadata']) {
                $elementValue = $this->get($elementName);
                if ($elementValue !== null) {
                    $metadata[$elementName] = $elementValue;
                }
            }
        }

        return $metadata;
    }

    public function aggregatePerformance(array &$aggregatedPerformance): void
    {
        if (($attributePerformance = $this->get('Attribute_Performance')) !== null) {
            $attributePerformance->aggregatePerformance($aggregatedPerformance);
        }
    }

    // TODO: similar function in JsonReportItem51
    public function merge(ComponentItem51 $componentItem): void
    {
        if ($componentItem->get('Attribute_Performance') !== null) {
            if ($this->get('Attribute_Performance') !== null) {
                $this->get('Attribute_Performance')->merge($componentItem->get('Attribute_Performance'));
                if (! $componentItem->get('Attribute_Performance')->isUsable()) {
                    $componentItem->setUnusable();
                }
            } else {
                $this->setData('Attribute_Performance', $componentItem->get('Attribute_Performance'));
            }

            $this->addMetricTypesPresent($componentItem->getMetricTypesPresent());
        }
    }

    // TODO: same function in JsonReportItem51
    public function storeData(): void
    {
        if (($attributePerformanceList = $this->get('Attribute_Performance')) !== null) {
            $attributePerformanceList->storeData();
            if (! $attributePerformanceList->isUsable()) {
                $this->setUnusable();
            }
        }
    }
}
