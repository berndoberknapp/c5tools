<?php

/**
 * config/r50/reports/tr_j2.php is the configuration for the COUNTER R5 Standard View TR_J2
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

return [
    'ID' => 'TR_J2',
    'Name' => 'Journal Access Denied',
    'FullReport' => 'TR',
    'Elements' => [
        'Title',
        'Item_ID',
        'Publisher',
        'Publisher_ID',
        'Platform',
        'DOI',
        'Proprietary_ID',
        'Print_ISSN',
        'Online_ISSN',
        'URI',
        'Metric_Type',
        'Reporting_Period_Total',
        'Performance'
    ],
    'Filters' => [
        'Access_Method' => [
            'Regular'
        ],
        'Begin_Date',
        'End_Date',
        'Data_Type' => [
            'Journal'
        ],
        'Metric_Type' => [
            'Limit_Exceeded',
            'No_License'
        ],
        'Platform'
    ]
];
