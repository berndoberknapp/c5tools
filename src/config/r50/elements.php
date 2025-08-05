<?php

/**
 * config/r50/elements.php is the configuration for the COUNTER R5 report item, parent and component elements
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

return [
    'json' => [
        'item' => [
            'Institution_Name' => [
                'check' => 'checkedRequiredNonEmptyString',
                'attribute' => true,
                'extension' => true
            ],
            'Customer_ID' => [
                'check' => 'checkedRequiredNonEmptyString',
                'attribute' => true,
                'extension' => true
            ],
            'Country_Name' => [
                'check' => 'checkedRequiredNonEmptyString',
                'attribute' => true,
                'extension' => true
            ],
            'Country_Code' => [
                'check' => 'checkedCountryCode',
                'attribute' => true,
                'extension' => true
            ],
            'Subdivision_Name' => [
                'check' => 'checkedRequiredNonEmptyString',
                'attribute' => true,
                'extension' => true
            ],
            'Subdivision_Code' => [
                'check' => 'checkedSubdivisionCode',
                'attribute' => true,
                'extension' => true
            ],
            'Attributed' => [
                'check' => 'checkedEnumeratedValue',
                'attribute' => true,
                'extension' => true
            ],
            'Database' => [
                'check' => 'checkedRequiredFilteredValue',
                'metadata' => true,
                'required' => true
            ],
            'Title' => [
                'check' => 'checkedRequiredString',
                'metadata' => true,
                'required' => true
            ],
            'Item' => [
                'check' => 'checkedRequiredString',
                'metadata' => true,
                'required' => true
            ],
            'Item_ID' => [
                'parse' => 'parseIdentifierList',
                'metadata' => true
            ],
            'Item_Contributors' => [
                'parse' => 'parseItemContributors',
                'metadata' => true
            ],
            'Item_Dates' => [
                'parse' => 'parseItemDates',
                'metadata' => true
            ],
            'Item_Attributes' => [
                'parse' => 'parseItemAttributes',
                'metadata' => true
            ],
            'Publisher' => [
                'check' => 'checkedRequiredString',
                'metadata' => true,
                'required' => true
            ],
            'Publisher_ID' => [
                'parse' => 'parseIdentifierList',
                'metadata' => true
            ],
            'Platform' => [
                'check' => 'checkedRequiredFilteredValue',
                'metadata' => true,
                'required' => true
            ],
            'Item_Parent' => [
                'parse' => 'parseItemParent'
            ],
            'Item_Component' => [
                'parse' => 'parseItemComponent'
            ],
            'Data_Type' => [
                'check' => 'checkedDataType',
                'attribute' => true,
                'required' => true
            ],
            'Section_Type' => [
                'check' => 'checkedSectionType',
                'attribute' => true
            ],
            'YOP' => [
                'check' => 'checkedYop',
                'attribute' => true,
                'required' => true
            ],
            'Access_Type' => [
                'check' => 'checkedEnumeratedValue',
                'attribute' => true,
                'required' => true
            ],
            'Access_Method' => [
                'check' => 'checkedEnumeratedValue',
                'attribute' => true,
                'required' => true
            ],
            'Format' => [
                'check' => 'checkedFormat',
                'attribute' => true,
                'extension' => true
            ],
            'Performance' => [
                'parse' => 'parsePerformance',
                'required' => true
            ]
        ],
        'parent' => [
            'Item_Name' => [
                'check' => 'checkedOptionalNonEmptyString',
                'metadata' => true
            ],
            'Item_ID' => [
                'parse' => 'parseIdentifierList',
                'metadata' => true,
                'required' => true
            ],
            'Item_Contributors' => [
                'parse' => 'parseItemContributors',
                'metadata' => true
            ],
            'Item_Dates' => [
                'parse' => 'parseItemDates',
                'metadata' => true
            ],
            'Item_Attributes' => [
                'parse' => 'parseItemAttributes',
                'metadata' => true
            ],
            'Data_Type' => [
                'check' => 'checkedParentDataType',
                'attribute' => true,
                'required' => true
            ]
        ],
        'component' => [
            'Item_Name' => [
                'check' => 'checkedOptionalNonEmptyString',
                'metadata' => true
            ],
            'Item_ID' => [
                'parse' => 'parseIdentifierList',
                'metadata' => true,
                'required' => true
            ],
            'Item_Contributors' => [
                'parse' => 'parseItemContributors',
                'metadata' => true
            ],
            'Item_Dates' => [
                'parse' => 'parseItemDates',
                'metadata' => true
            ],
            'Item_Attributes' => [
                'parse' => 'parseItemAttributes',
                'metadata' => true
            ],
            'Data_Type' => [
                'check' => 'checkedComponentDataType',
                'attribute' => true,
                'required' => true
            ],
            'Performance' => [
                'parse' => 'parsePerformance',
                'required' => true
            ]
        ]
    ],
    'tabular' => [
        'item' => [
            'Institution_Name' => [
                'check' => 'checkedRequiredNonEmptyString',
                'attribute' => true,
                'extension' => true
            ],
            'Customer_ID' => [
                'check' => 'checkedRequiredNonEmptyString',
                'attribute' => true,
                'extension' => true
            ],
            'Country_Name' => [
                'check' => 'checkedRequiredNonEmptyString',
                'attribute' => true,
                'extension' => true
            ],
            'Country_Code' => [
                'check' => 'checkedCountryCode',
                'attribute' => true,
                'extension' => true
            ],
            'Subdivision_Name' => [
                'check' => 'checkedRequiredNonEmptyString',
                'attribute' => true,
                'extension' => true
            ],
            'Subdivision_Code' => [
                'check' => 'checkedSubdivisionCode',
                'attribute' => true,
                'extension' => true
            ],
            'Attributed' => [
                'check' => 'checkedEnumeratedValue',
                'attribute' => true,
                'extension' => true
            ],
            'Database' => [
                'check' => 'checkedRequiredFilteredValue',
                'metadata' => true,
                'required' => true
            ],
            'Title' => [
                'check' => 'checkedRequiredString',
                'metadata' => true,
                'required' => true
            ],
            'Item' => [
                'check' => 'checkedRequiredString',
                'metadata' => true,
                'required' => true
            ],
            'Publisher' => [
                'check' => 'checkedRequiredString',
                'metadata' => true,
                'required' => true
            ],
            'Publisher_ID' => [
                'parse' => 'parseIdentifierList',
                'metadata' => true
            ],
            'Platform' => [
                'check' => 'checkedRequiredFilteredValue',
                'metadata' => true,
                'required' => true
            ],
            'Authors' => [
                'parse' => 'parseAuthors',
                'metadata' => true
            ],
            'Publication_Date' => [
                'parse' => 'parsePublicationDate',
                'metadata' => true
            ],
            'Article_Version' => [
                'parse' => 'parseArticleVersion',
                'metadata' => true
            ],
            'DOI' => [
                'check' => 'checkedDoiIdentifier',
                'metadata' => true
            ],
            'Proprietary_ID' => [
                'check' => 'checkedProprietaryIdentifier',
                'metadata' => true
            ],
            'ISBN' => [
                'check' => 'checkedIsbnIdentifier',
                'metadata' => true
            ],
            'Print_ISSN' => [
                'check' => 'checkedIssnIdentifier',
                'metadata' => true
            ],
            'Online_ISSN' => [
                'check' => 'checkedIssnIdentifier',
                'metadata' => true
            ],
            'URI' => [
                'check' => 'checkedUriIdentifier',
                'metadata' => true
            ],
            'Parent_Title' => [
                'check' => 'checkedRequiredString'
            ],
            'Parent_Authors' => [
                'parse' => 'parseAuthors'
            ],
            'Parent_Publication_Date' => [
                'parse' => 'parsePublicationDate'
            ],
            'Parent_Article_Version' => [
                'parse' => 'parseArticleVersion'
            ],
            'Parent_Data_Type' => [
                'check' => 'checkedParentDataType',
                'attribute' => true
            ],
            'Parent_DOI' => [
                'check' => 'checkedDoiIdentifier'
            ],
            'Parent_Proprietary_ID' => [
                'check' => 'checkedProprietaryIdentifier'
            ],
            'Parent_ISBN' => [
                'check' => 'checkedIsbnIdentifier'
            ],
            'Parent_Print_ISSN' => [
                'check' => 'checkedIssnIdentifier'
            ],
            'Parent_Online_ISSN' => [
                'check' => 'checkedIssnIdentifier'
            ],
            'Parent_URI' => [
                'check' => 'checkedUriIdentifier'
            ],
            'Component_Title' => [
                'check' => 'checkedRequiredString'
            ],
            'Component_Authors' => [
                'parse' => 'parseAuthors'
            ],
            'Component_Publication_Date' => [
                'parse' => 'parsePublicationDate'
            ],
            'Component_Data_Type' => [
                'check' => 'checkedComponentDataType',
                'attribute' => true
            ],
            'Component_DOI' => [
                'check' => 'checkedDoiIdentifier'
            ],
            'Component_Proprietary_ID' => [
                'check' => 'checkedProprietaryIdentifier'
            ],
            'Component_ISBN' => [
                'check' => 'checkedIsbnIdentifier'
            ],
            'Component_Print_ISSN' => [
                'check' => 'checkedIssnIdentifier'
            ],
            'Component_Online_ISSN' => [
                'check' => 'checkedIssnIdentifier'
            ],
            'Component_URI' => [
                'check' => 'checkedUriIdentifier'
            ],
            'Data_Type' => [
                'check' => 'checkedDataType',
                'attribute' => true,
                'required' => true
            ],
            'Section_Type' => [
                'check' => 'checkedSectionType',
                'attribute' => true
            ],
            'YOP' => [
                'check' => 'checkedYop',
                'attribute' => true,
                'required' => true
            ],
            'Access_Type' => [
                'check' => 'checkedEnumeratedValue',
                'attribute' => true,
                'required' => true
            ],
            'Access_Method' => [
                'check' => 'checkedEnumeratedValue',
                'attribute' => true,
                'required' => true
            ],
            'Format' => [
                'check' => 'checkedFormat',
                'attribute' => true,
                'extension' => true
            ],
            'Metric_Type' => [
                'check' => 'checkedEnumeratedValue',
                'required' => true
            ],
            'Reporting_Period_Total' => [
                'parse' => 'parseReportingPeriodTotal',
                'required' => true
            ]
        ]
    ]
];
