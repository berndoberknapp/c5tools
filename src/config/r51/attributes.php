<?php

/**
 * config/r51/attributes.php is the configuration for the COUNTER R5.1 report attributes
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

return [
    'Attributes_To_Show' => [
        'multi' => true
    ],
    'Exclude_Monthly_Details' => [
        'json' => false,
        'values' => [
            'False',
            'True'
        ],
        'default' => 'False'
    ],
    'Granularity' => [
        'tabular' => false,
        'values' => [
            'Month',
            'Totals'
        ],
        'default' => 'Month'
    ],
    'Include_Component_Details' => [
        'values' => [
            'False',
            'True'
        ],
        'default' => 'False'
    ],
    'Include_Parent_Details' => [
        'values' => [
            'False',
            'True'
        ],
        'default' => 'False'
    ]
];
