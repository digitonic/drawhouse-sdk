<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Exceptions;

/**
 * HTTP 500 / errorCode 3 — a backend failure, safe to retry with backoff; the client retries once automatically and
 * leaves any further backoff to the caller (see DrawHouseException::isRetryable()).
 *
 * HTTP 503 on the webhook routes — secret storage is temporarily unavailable. Nothing was created, or on a rotation the
 * old secret is still in force. Retryable later; the webhook routes are never retried automatically.
 */
final class ServerException extends DrawHouseException {}
