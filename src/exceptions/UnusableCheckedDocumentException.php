<?php

/**
 * UnusableCheckedDocumentException is thrown by unusable CheckedDocuments
 *
 * A CheckedDocument is unusable when it has detected serious errors while parsing or validating parts
 * of a COUNTER file or API response. The UnusableCheckedDocumentException is thrown when such an unsuable
 * CheckedDocument is asked to return a JSON or tabular version of the element it represents.
 *
 * @author Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2019-2025 Albert-Ludwigs-Universität, Universitätsbibliothek
 */

namespace ubfr\c5tools\exceptions;

class UnusableCheckedDocumentException extends \Exception
{
}
