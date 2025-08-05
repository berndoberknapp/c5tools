<?php

/**
 * config/r50/reports/dr_d2.php is the configuration for the COUNTER R5 Standard View DR_D2
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

return [
    'ID' => 'DR_D2',
    'Name' => 'Database Access Denied',
    'FullReport' => 'DR',
    'Elements' => [
        'Database',
        'Item_ID',
        'Publisher',
        'Publisher_ID',
        'Platform',
        'Proprietary_ID',
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
        'Metric_Type' => [
            'Limit_Exceeded',
            'No_License'
        ],
        'Platform'
    ]
];
