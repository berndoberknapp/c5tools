<?php

/**
 * config/r51/reports/tr_b1.php is the configuration for the COUNTER R5.1 Standard View TR_B1
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

return [
    'ID' => 'TR_B1',
    'Name' => 'Book Requests (Controlled)',
    'FullReport' => 'TR',
    'Elements' => [
        'Title',
        'Item_ID',
        'Publisher',
        'Publisher_ID',
        'Platform',
        'DOI',
        'Proprietary_ID',
        'ISBN',
        'Print_ISSN',
        'Online_ISSN',
        'URI',
        'Data_Type',
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
            'Book',
            'Reference_Work'
        ],
        'Metric_Type' => [
            'Total_Item_Requests',
            'Unique_Title_Requests'
        ],
        'Platform'
    ]
];
