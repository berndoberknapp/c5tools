<?php

/**
 * config/r50/identifiers.php is the configuration for the COUNTER R5 organization and item identifiers
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

return [
    'Institution' => [
        'ISNI' => [
            'multi' => true,
            'check' => 'checkedIsniIdentifier'
        ],
        'ISIL' => [
            'multi' => true,
            'check' => 'checkedIsilIdentifier'
        ],
        'OCLC' => [
            'multi' => true,
            'check' => 'checkedOclcIdentifier'
        ],
        'Proprietary' => [
            'multi' => true,
            'check' => 'checkedProprietaryIdentifier'
        ],
        'ROR' => [
            'multi' => true,
            'check' => 'checkedRorIdentifier'
        ]
    ],
    'Publisher' => [
        'ISNI' => [
            'multi' => true,
            'check' => 'checkedIsniIdentifier'
        ],
        'Proprietary' => [
            'multi' => true,
            'check' => 'checkedProprietaryIdentifier'
        ],
        'ROR' => [
            'multi' => true,
            'check' => 'checkedRorIdentifier'
        ]
    ],
    'Item' => [
        'DOI' => [
            'check' => 'checkedDoiIdentifier'
        ],
        'Proprietary' => [
            'tabular' => false,
            'check' => 'checkedProprietaryIdentifier'
        ],
        'Proprietary_ID' => [
            'json' => false,
            'check' => 'checkedProprietaryIdentifier'
        ],
        'ISBN' => [
            'check' => 'checkedIsbnIdentifier'
        ],
        'Print_ISSN' => [
            'check' => 'checkedIssnIdentifier'
        ],
        'Online_ISSN' => [
            'check' => 'checkedIssnIdentifier'
        ],
        'Linking_ISSN' => [
            'check' => 'checkedIssnIdentifier'
        ],
        'URI' => [
            'check' => 'checkedUriIdentifier'
        ]
    ],
    'Author' => [
        'ISNI' => [
            'check' => 'checkedIsniIdentifier'
        ],
        'ORCID' => [
            'check' => 'checkedOrcidIdentifier'
        ]
    ]
];
