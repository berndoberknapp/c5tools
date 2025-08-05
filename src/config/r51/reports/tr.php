<?php

/**
 * config/r51/reports/tr.php is the configuration for the COUNTER R5.1 Report TR
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

return [
    'ID' => 'TR',
    'Name' => 'Title Report',
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
            'YOP',
            'Access_Type',
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
        'Access_Type' => [
            'Controlled',
            'Free_To_Read',
            'Open'
        ],
        'Begin_Date',
        'End_Date',
        'Data_Type' => [
            'Book',
            'Conference',
            'Journal',
            'Newspaper_or_Newsletter',
            'Other',
            'Patent',
            'Reference_Work',
            'Report',
            'Standard',
            'Thesis_or_Dissertation',
            'Unspecified'
        ],
        'Item_ID',
        'Metric_Type' => [
            'Total_Item_Investigations',
            'Total_Item_Requests',
            'Unique_Item_Investigations',
            'Unique_Item_Requests',
            'Unique_Title_Investigations',
            'Unique_Title_Requests',
            'Limit_Exceeded',
            'No_License'
        ],
        'Platform',
        'YOP',
        'Attributed' => [
            'No',
            'Yes'
        ],
        'Country_Code',
        'Subdivision_Code'
    ]
];
