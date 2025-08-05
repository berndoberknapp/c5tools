<?php

/**
 * config/r51/reports/pr_p1.php is the configuration for the COUNTER R5.1 Standard View PR_P1
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
        'Data_Type',
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
        'Data_Type' => [
            'Article',
            'Audiovisual',
            'Book',
            'Book_Segment',
            'Conference',
            'Conference_Item',
            'Database_Full_Item',
            'Dataset',
            'Image',
            'Interactive_Resource',
            'Journal',
            'Multimedia',
            'News_Item',
            'Newspaper_or_Newsletter',
            'Other',
            'Patent',
            'Platform',
            'Reference_Item',
            'Reference_Work',
            'Report',
            'Software',
            'Sound',
            'Standard',
            'Thesis_or_Dissertation',
            'Unspecified'
        ],
        'Metric_Type' => [
            'Searches_Platform',
            'Total_Item_Requests',
            'Unique_Item_Requests',
            'Unique_Title_Requests'
        ],
        'Platform'
    ]
];
