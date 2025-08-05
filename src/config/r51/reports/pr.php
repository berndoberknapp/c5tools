<?php

/**
 * config/r51/reports/pr.php is the configuration for the COUNTER R5.1 Report PR
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

return [
    'ID' => 'PR',
    'Name' => 'Platform Report',
    'Elements' => [
        'Platform',
        'Data_Type',
        'Metric_Type',
        'Reporting_Period_Total',
        'Attribute_Performance'
    ],
    'Attributes' => [
        'Attributes_To_Show' => [
            'Institution_Name',
            'Customer_ID',
            'Country_Name',
            'Country_Code',
            'Subdivision_Name',
            'Subdivision_Code',
            'Attributed',
            'Access_Method'
        ],
        'Exclude_Monthly_Details',
        'Granularity'
    ],
    'Filters' => [
        'Access_Method' => [
            'Regular',
            'TDM'
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
            'Total_Item_Investigations',
            'Total_Item_Requests',
            'Unique_Item_Investigations',
            'Unique_Item_Requests',
            'Unique_Title_Investigations',
            'Unique_Title_Requests'
        ],
        'Platform',
        'Attributed' => [
            'No',
            'Yes'
        ],
        'Country_Code',
        'Subdivision_Code'
    ]
];
