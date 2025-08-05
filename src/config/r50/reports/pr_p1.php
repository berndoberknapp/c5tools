<?php

/**
 * config/r50/reports/pr_p1.php is the configuration for the COUNTER R5 Standard View PR_P1
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

return [
    'ID' => 'PR_P1',
    'Name' => 'Platform Usage',
    'FullReport' => 'PR',
    'Elements' => [
        'Platform',
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
            'Searches_Platform',
            'Total_Item_Requests',
            'Unique_Item_Requests',
            'Unique_Title_Requests'
        ],
        'Platform'
    ]
];
