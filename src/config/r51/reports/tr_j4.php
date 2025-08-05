<?php

/**
 * config/r51/reports/tr_j4.php is the configuration for the COUNTER R5.1 Standard View TR_J4
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

return [
    'ID' => 'TR_J4',
    'Name' => 'Journal Requests by YOP (Controlled)',
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
        'YOP',
        'Metric_Type',
        'Reporting_Period_Total',
        'Attribute_Performance'
    ],
    'Filters' => [
        'Access_Method' => [
            'Regular'
        ],
        'Access_Type' => [
            'Controlled'
        ],
        'Begin_Date',
        'End_Date',
        'Data_Type' => [
            'Journal'
        ],
        'Metric_Type' => [
            'Total_Item_Requests',
            'Unique_Item_Requests'
        ],
        'Platform'
    ]
];
