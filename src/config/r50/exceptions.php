<?php

/**
 * config/r50/exceptions.php is the configuration for the COUNTER R5 exceptions
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

return [
    1000 => [
        'Message' => 'Service Not Available',
        'Severity' => 'Fatal',
        'HttpCode' => 503
    ],
    1010 => [
        'Message' => 'Service Busy',
        'Severity' => 'Fatal',
        'HttpCode' => 503
    ],
    1011 => [
        'Message' => 'Report Queued for Processing',
        'Severity' => 'Warning',
        'HttpCode' => 202
    ],
    1020 => [
        'Message' => 'Client has made too many requests',
        'Severity' => 'Fatal',
        'HttpCode' => 429,
        'DataRequired' => true
    ],
    1030 => [
        'Message' => 'Insufficient Information to Process Request',
        'Severity' => 'Fatal',
        'HttpCode' => 400
    ],
    2000 => [
        'Message' => 'Requestor Not Authorized to Access Service',
        'Severity' => 'Error',
        'HttpCode' => 401
    ],
    2010 => [
        'Message' => 'Requestor is Not Authorized to Access Usage for Institution',
        'Severity' => 'Error',
        'HttpCode' => 403
    ],
    2020 => [
        'Message' => 'APIKey Invalid',
        'Severity' => 'Error',
        'HttpCode' => 401
    ],
    2030 => [
        'Message' => 'IP Address Not Authorized to Access Service',
        'Severity' => 'Error',
        'HttpCode' => 401
    ],
    3000 => [
        'Message' => 'Report Not Supported',
        'Severity' => 'Error',
        'HttpCode' => 404
    ],
    3010 => [
        'Message' => 'Report Version Not Supported',
        'Severity' => 'Error',
        'HttpCode' => 404
    ],
    3020 => [
        'Message' => 'Invalid Date Arguments',
        'Severity' => 'Error',
        'HttpCode' => 400
    ],
    3030 => [
        'Message' => 'No Usage Available for Requested Dates',
        'Severity' => 'Error',
        'HttpCode' => 200
    ],
    3031 => [
        'Message' => 'Usage Not Ready for Requested Dates',
        'Severity' => [
            'Warning',
            'Error'
        ],
        'HttpCode' => 200,
        'DataRequired' => true // TODO: conflict with consortium reports
    ],
    3032 => [
        'Message' => 'Usage No Longer Available for Requested Dates',
        'Severity' => 'Warning',
        'HttpCode' => 200,
        'DataRequired' => true
    ],
    3040 => [
        'Message' => 'Partial Data Returned',
        'Severity' => 'Warning',
        'HttpCode' => 200,
        'DataRequired' => true
    ],
    3050 => [
        'Message' => 'Parameter Not Recognized in this Context',
        'Severity' => 'Warning',
        'HttpCode' => 200,
        'DataRequired' => true
    ],
    3060 => [
        'Message' => 'Invalid ReportFilter Value',
        'Severity' => [
            'Warning',
            'Error'
        ],
        'HttpCode' => 200,
        'DataRequired' => true
    ],
    3061 => [
        'Message' => 'Incongruous ReportFilter Value',
        'Severity' => [
            'Warning',
            'Error'
        ],
        'HttpCode' => 200
    ],
    3062 => [
        'Message' => 'Invalid ReportAttribute Value',
        'Severity' => [
            'Warning',
            'Error'
        ],
        'HttpCode' => 200,
        'DataRequired' => true
    ],
    3070 => [
        'Message' => 'Required ReportFilter Missing',
        'Severity' => [
            'Warning',
            'Error'
        ],
        'HttpCode' => 200,
        'DataRequired' => true
    ]
];
