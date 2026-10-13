<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Exceptions;

/**
 * HTTP 400 / errorCode 7 — an invalid or missing request parameter. The
 * message names the parameter at fault. Not retryable; fix the request.
 */
final class InvalidRequestException extends DrawHouseException {}
