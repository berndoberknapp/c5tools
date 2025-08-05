# COUNTER Release 5.x Tools (c5tools)

C5tools is a PHP library for fetching, parsing and validating COUNTER reports and COUNTER API (formerly SUSHI) responses. The main features are:

* Support for COUNTER Release 5 and Release 5.1
* Support for COUNTER Reports and Standard Views in both JSON and tabular format
* Support for common extensions as specified in Section 11 of the COUNTER Code of Practice
* A COUNTER API client that supports all COUNTER API paths and parameters
* Generating validation reports in text, Excel and JSON format
* Parsed COUNTER reports and API responses are available for further processing

The c5tools were originally developed for the German National Statistics Service ([NatStat](https://statistik.hebis.de/)) and later extended for performing the validation in the former COUNTER Validation Tool and now the [COUNTER Validator](https://validator.countermetrics.org/).

Please see the [COUNTER Metrics website](https://countermetrics.org/), the [COUNTER Code of Practice](https://cop5.countermetrics.org/) and the [COUNTER API Specification](https://countermetrics.stoplight.io/) for more information on COUNTER Metrics and the COUNTER standard.

## Requirements

Running the c5tools requires [PHP](https://www.php.net/) (8.2 or newer) and [Composer](https://getcomposer.org/) for managing the dependencies.

Running the c5tools in a [Docker](https://www.docker.com/) container is also supported, in this case only Docker is required.

## Installation via Packagist

PHP applications that use Composer can install the c5tools via [Packagist](https://packagist.org/) by requiring the ubfr/c5tools package.

## Running the Demo Scripts

For running the scripts in the [demo directory](demo) please clone the project, install Composer in the project directory and run
```console
php composer.phar install
```
to install the dependencies. Then run
```console
php demo/validate_file.php
php demo/validate_api.php
```
to get a short help on using the demo scripts for validating COUNTER report files and COUNTER API requests and responses.

## Running a Docker Container

The Docker setup was provided by [@beda42](https://www.github.com/beda42), it is used by the [COUNTER Validator](https://github.com/Project-Counter/counter-validator) and was tested with Docker 27.3 and 28.2. The Docker container provides a simple REST API for performing COUNTER report file and COUNTER API validations (see the [public directory](public)).

For creating and running the Docker container please clone the project and run
```console
docker compose up
```
in the project directory. A request to validate a COUNTER report file in Excel format for example could be submitted with [curl](https://curl.se/):
```console
curl --data-binary '@/path/to/reportfile.xlsx' 'http://127.0.0.1:8180/file.php?extension=xlsx'
```
Note that the extension parameter in the URL must match the usual file extensions for the type of file submitted (e.g. json, tsv, csv, or xlsx). A COUNTER API validation can be performed by submitting the COUNTER API URL:
```console
curl -d '{ "url": "https://counter.api.server/path/r51/status" }' 'http://127.0.0.1:8180/api.php'
```
For better readability the response could be piped through `jq .`
