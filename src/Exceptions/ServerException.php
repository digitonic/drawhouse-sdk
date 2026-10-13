<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Exceptions;

/**
 * HTTP 500 / errorCode 3 — a backend failure, safe to retry with backoff; the client retries once automatically and
 * leaves any further backoff to the caller (see DrawHouseException::isRetryable()).
 */
final class ServerException extends DrawHouseException {}
