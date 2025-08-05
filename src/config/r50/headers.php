<?php

/**
 * config/r50/headers.php is the configuration for the COUNTER R5 report header elements
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

// Order is relevant, see comments below!
return [
    'Release' => [
        'json' => 'required',
        'row' => 3,
        'check' => 'checkedRelease'
    ],
    'Report_ID' => [
        'json' => 'required',
        'row' => 2,
        'check' => 'checkedReportId'
    ],
    'Report_Name' => [
        // check requires Report_ID
        'json' => 'required',
        'row' => 1,
        'check' => 'checkedReportName'
    ],
    'Created' => [
        'json' => 'required',
        'row' => 11,
        'check' => 'checkedRfc3339Date'
    ],
    'Created_By' => [
        'json' => 'required',
        'row' => 12,
        'check' => 'checkedRequiredNonEmptyString'
    ],
    'Customer_ID' => [
        'json' => 'required',
        'check' => 'checkedRequiredNonEmptyString'
    ],
    'Exceptions' => [
        'json' => 'optional',
        'row' => 9,
        'parse' => 'parseExceptionList'
    ],
    'Institution_ID' => [
        'json' => 'optional',
        'row' => 5,
        'parse' => 'parseIdentifierList'
    ],
    'Institution_Name' => [
        'json' => 'required',
        'row' => 4,
        'check' => 'checkedRequiredNonEmptyString'
    ],
    'Metric_Types' => [
        // must be parsed before Report_Filters
        'row' => 6,
        'parse' => 'parseMetricTypes'
    ],
    'Reporting_Period' => [
        // must be parsed before Report_Filters
        'row' => 10,
        'parse' => 'parseReportingPeriod'
    ],
    'Report_Attributes' => [
        'json' => 'optional',
        'row' => 8,
        'parse' => 'parseReportAttributes'
    ],
    'Report_Filters' => [
        'json' => 'required',
        'row' => 7,
        'parse' => 'parseReportFilters'
    ]
];
