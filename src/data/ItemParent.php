<?php

/**
 * ItemParent is the abstract base class for handling COUNTER R5 Item Parents
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

abstract class ItemParent implements \ubfr\c5tools\interfaces\CheckedDocument
{
    use CheckedDocument;
    use Parsers;
    use Checks;
    use Helpers;

    protected ReportHeader $reportHeader;

    protected ReportItem $reportItem;

    abstract protected function parseDocument(): void;

    public function __construct(\ubfr\c5tools\interfaces\CheckedDocument $parent, string $position, $document)
    {
        $this->document = $document;
        $this->checkResult = $parent->getCheckResult();
        $this->config = $parent->getConfig();
        $this->format = $parent->getFormat();
        $this->position = $position;

        $this->reportHeader = $parent->getReportHeader();
        $this->reportItem = $parent;
    }

    public function getHash(): string
    {
        $hashContext = hash_init('sha256');
        $this->updateHash($hashContext, $this->data);
        return hash_final($hashContext);
    }

    protected function updateHash(object $hashContext, array $elements, ?string $position = null)
    {
        ksort($elements);
        foreach ($elements as $element => $value) {
            $element = (string) $element;
            if (is_object($value)) {
                $value = $value->getData();
            }
            if (is_array($value)) {
                $this->updateHash($hashContext, $value, ($position === null ? $element : $position . '.' . $element));
            } else {
                $string = ($position === null ? $element : $position . '.' . $element) . ' => ' . $value;
                hash_update($hashContext, mb_strtolower($string));
            }
        }
    }
}
