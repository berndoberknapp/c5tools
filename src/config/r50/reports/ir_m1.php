<?php

/**
 * config/r50/reports/ir_m1.php is the configuration for the COUNTER R5 Standard View IR_M1
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

return [
    'ID' => 'IR_M1',
    'Name' => 'Multimedia Item Requests',
    'FullReport' => 'IR',
    'Elements' => [
        'Item',
        'Item_ID', // TODO: restrict to permitted elements
        'Publisher',
        'Publisher_ID',
        'Platform',
        'DOI',
        'Proprietary_ID',
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
            'Multimedia'
        ],
        'Metric_Type' => [
            'Total_Item_Requests'
        ],
        'Platform'
    ]
];
