<?php

/**
 * config/r51/reports/dr_d1.php is the configuration for the COUNTER R5.1 Standard View DR_D1
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

return [
    'ID' => 'DR_D1',
    'Name' => 'Database Search and Item Usage',
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
        'Attribute_Performance'
    ],
    'Filters' => [
        'Access_Method' => [
            'Regular'
        ],
        'Begin_Date',
        'End_Date',
        'Metric_Type' => [
            'Searches_Automated',
            'Searches_Federated',
            'Searches_Regular',
            'Total_Item_Investigations',
            'Total_Item_Requests',
            'Unique_Item_Investigations',
            'Unique_Item_Requests'
        ],
        'Platform'
    ]
];
