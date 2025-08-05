<?php

/**
 * ReportList is the main class for parsing and validating JSON Report List responses
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools;

use ubfr\c5tools\data\ReportInfo;

class ReportList implements interfaces\CheckedDocument, interfaces\JsonDocument, \Countable, \IteratorAggregate
{
    use traits\CheckedDocument;
    use traits\Parsers;
    use traits\Checks;
    use traits\Helpers;

    public function __construct(Document $document, CounterApiRequest $request)
    {
        if (! $document->isReportList()) {
            throw new \InvalidArgumentException("document is not valid for ReportList");
        }

        $this->document = $document;
        $this->request = $request;
        $this->checkResult = new CheckResult();
        $this->config = Config::forRelease($request->getRelease());
        $this->format = self::FORMAT_JSON;
        $this->position = '.';

        $this->checkHttpCode200('report list');
        $this->checkNoByteOrderMark();
    }

    public function getJsonString(): string
    {
        return $this->document->getBuffer();
    }

    public function getJson()
    {
        return $this->document->getDocument();
    }

    public function count(): int
    {
        if (! $this->isParsed()) {
            $this->parseDocument();
        }
        return ($this->isUsable() ? count($this->get('.')) : 0);
    }

    public function getIterator(): \ArrayIterator
    {
        if (! $this->isParsed()) {
            $this->parseDocument();
        }
        return new \ArrayIterator($this->isUsable() ? $this->get('.') : []);
    }

    public function getReportIds(): array
    {
        $release = $this->config->getRelease();
        $reportIds = [];
        foreach ($this as $reportInfo) {
            if ($reportInfo->get('Release') === $release) {
                $reportIds[] = $reportInfo->get('Report_ID');
            }
        }

        return $reportIds;
    }

    protected function parseDocument(): void
    {
        $this->setParsing();

        // response is a non-empty array (checked in Document::isReportList)
        foreach ($this->getJson() as $index => $reportInfoJson) {
            $this->setIndex($index);
            $position = "{$this->position}[{$index}]";
            if (! $this->isArrayValueObject($position, '.', $reportInfoJson)) {
                continue;
            }
            $reportInfo = new ReportInfo($this, $position, $reportInfoJson);
            if ($reportInfo->isUsable()) {
                $this->setData('.', $reportInfo);
                if ($reportInfo->isFixed()) {
                    $this->setFixed('.', $reportInfoJson);
                }
            } else {
                $this->setInvalid('.', $reportInfoJson);
            }
        }

        $this->setParsed();

        if (empty($this->get('.'))) {
            $this->setUnusable();
        } else {
            $this->checkR5ReportRelations();
        }
    }

    protected function checkR5ReportRelations(): void
    {
        $reportIds = $this->getReportIds();
        $fullReport = ($this->config->getRelease() === '5' ? 'Master Report' : 'COUNTER Report');
        if (! in_array('PR', $reportIds)) {
            $message = "{$fullReport} PR is missing";
            $hint = "all platforms must provide this {$fullReport}";
            $this->addCriticalError($message, $message, $this->position, null, $hint);
        }
        if (
            empty(array_intersect([
                'DR',
                'TR',
                'IR'
            ], $reportIds))
        ) {
            $message = "{$fullReport}s DR, TR and IR are missing";
            $hint = "all platforms must provide at least one of these {$fullReport}s";
            $this->addCriticalError($message, $message, $this->position, null, $hint);
        }

        $prDerivedReportIds = [
            'PR_P1'
        ];
        $this->checkFullReportRelations($reportIds, 'PR', $prDerivedReportIds);
        $this->checkDerivedReportRelations($reportIds, $prDerivedReportIds);

        $drDerivedReportIds = [
            'DR_D1',
            'DR_D2'
        ];
        $this->checkFullReportRelations($reportIds, 'DR', $drDerivedReportIds);
        $this->checkDerivedReportRelations($reportIds, $drDerivedReportIds);

        $trDerivedBookReportIds = [
            'TR_B1',
            'TR_B2',
            'TR_B3'
        ];
        $trDerivedJournalReportIds = [
            'TR_J1',
            'TR_J2',
            'TR_J3',
            'TR_J4'
        ];
        $this->checkFullReportRelations(
            $reportIds,
            'TR',
            array_merge($trDerivedBookReportIds, $trDerivedJournalReportIds),
            true
        );
        $this->checkDerivedReportRelations($reportIds, $trDerivedBookReportIds);
        $this->checkDerivedReportRelations($reportIds, $trDerivedJournalReportIds);

        $irDerivedReportIds = [
            'IR_A1',
            'IR_M1'
        ];
        $this->checkFullReportRelations($reportIds, 'IR', $irDerivedReportIds, true);
    }

    protected function checkFullReportRelations($reportIds, $fullReportId, $derivedReportIds, $warnOnly = false)
    {
        $fullReport = ($this->config->getRelease() === '5' ? 'Master Report' : 'COUNTER Report');
        if (in_array($fullReportId, $reportIds)) {
            // COUNTER Report present
            foreach ($derivedReportIds as $derivedReportId) {
                if (! in_array($derivedReportId, $reportIds)) {
                    $addLevel = ((substr($derivedReportId, - 1) === '2' || $warnOnly) ? 'addWarning' : 'addCriticalError');
                    $message = "{$fullReport} {$fullReportId} is supported, but Standard View {$derivedReportId} is missing";
                    $this->$addLevel($message, $message, $this->position, null);
                }
            }
        } elseif (! empty(array_intersect($derivedReportIds, $reportIds))) {
            // COUNTER Report missing, but Standard View present
            $message = "{$fullReport} {$fullReportId} missing";
            $this->addCriticalError($message, $message, $this->position, null);
        }
    }

    protected function checkDerivedReportRelations($reportIds, $derivedReportIds)
    {
        foreach ($derivedReportIds as $derivedReportId1) {
            foreach ($derivedReportIds as $derivedReportId2) {
                if (in_array($derivedReportId1, $reportIds) && ! in_array($derivedReportId2, $reportIds)) {
                    $addLevel = (substr($derivedReportId2, - 1) === '2' ? 'addWarning' : 'addCriticalError');
                    $message = "Standard View {$derivedReportId1} supported, but not Standard View {$derivedReportId2}";
                    $this->$addLevel($message, $message, $this->position, null);
                }
            }
        }
    }
}
