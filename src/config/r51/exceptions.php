<?php

/**
 * config/r51/exceptions.php is the configuration for the COUNTER R5.1 exceptions
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

return [
    1000 => [
        'Message' => 'Service Not Available',
        'HttpCode' => 503
    ],
    1010 => [
        'Message' => 'Service Busy',
        'HttpCode' => 503
    ],
    1011 => [
        'Message' => 'Report Queued for Processing',
        'HttpCode' => 202
    ],
    1020 => [
        'Message' => 'Client has made too many requests',
        'HttpCode' => 429,
        'DataRequired' => true
    ],
    1030 => [
        'Message' => 'Insufficient Information to Process Request',
        'HttpCode' => 400
    ],
    2000 => [
        'Message' => 'Requestor Not Authorized to Access Service',
        'HttpCode' => 401
    ],
    2010 => [
        'Message' => 'Requestor is Not Authorized to Access Usage for Institution',
        'HttpCode' => 403
    ],
    2011 => [
        'Message' => 'Global Reports Not Supported',
        'HttpCode' => 403
    ],
    2020 => [
        'Message' => 'APIKey Invalid',
        'HttpCode' => 401
    ],
    2030 => [
        'Message' => 'IP Address Not Authorized to Access Service',
        'HttpCode' => 401
    ],
    3020 => [
        'Message' => 'Invalid Date Arguments',
        'HttpCode' => 400
    ],
    3030 => [
        'Message' => 'No Usage Available for Requested Dates',
        'HttpCode' => 200
    ],
    3031 => [
        'Message' => 'Usage Not Ready for Requested Dates',
        'HttpCode' => 200,
        'DataRequired' => true // TODO: conflict with consortium reports
    ],
    3032 => [
        'Message' => 'Usage No Longer Available for Requested Dates',
        'HttpCode' => 200,
        'DataRequired' => true
    ],
    3040 => [
        'Message' => 'Partial Data Returned',
        'HttpCode' => 200,
        'DataRequired' => true
    ],
    3050 => [
        'Message' => 'Parameter Not Recognized in this Context',
        'HttpCode' => 200,
        'DataRequired' => true
    ],
    3060 => [
        'Message' => 'Invalid ReportFilter Value',
        'HttpCode' => 200,
        'DataRequired' => true
    ],
    3061 => [
        'Message' => 'Incongruous ReportFilter Value',
        'HttpCode' => 200
    ],
    3062 => [
        'Message' => 'Invalid ReportAttribute Value',
        'HttpCode' => 200,
        'DataRequired' => true
    ],
    3063 => [
        'Message' => 'Components Not Supported',
        'HttpCode' => 200
    ],
    3070 => [
        'Message' => 'Required ReportFilter Missing',
        'HttpCode' => 200,
        'DataRequired' => true
    ]
];
