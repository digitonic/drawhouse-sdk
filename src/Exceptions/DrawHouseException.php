<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Exceptions;

use RuntimeException;

/**
 * Raised for any failed DrawHouse call: a non-zero `errorCode` in the
 * `{ errorCode, data }` envelope, a non-2xx status without an envelope, or a
 * body that is not the documented envelope at all.
 *
 * The API's error table is mapped to
 * subclasses by fromResponse():
 *
 *  | HTTP | errorCode | Exception                   | What to do                          |
 *  | ---- | --------- | --------------------------- | ----------------------------------- |
 *  | 400  | 7         | InvalidRequestException     | Fix the request; message names the parameter |
 *  | 401  | 401       | AuthenticationException     | Handled: refresh, then re-login     |
 *  | 403  | 403       | PartnerNotLiveException     | when data is { error: partner_not_live, status } |
 *  | 403  | 403       | DrawHouseException          | not a partner                       |
 *  | 404  | 404       | NotFoundException           | Check the id / not your affiliate   |
 *  | 422  | 422       | AuthenticationException     | Refresh only: re-login (handled)    |
 *  | 500  | 3         | ServerException             | Retry with backoff                  |
 *  | 503  | 503       | ServerException             | Retry later (webhook secret storage unavailable) |
 *
 * The message never contains credentials or tokens.
 */
class DrawHouseException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $errorCode,
        public readonly int $status,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $errorCode ?? 0, $previous);
    }

    /**
     * Build the most specific exception for a failed response, from the
     * error table. `$data` is the envelope's `data` (a message string, or an
     * object for `partner_not_live`), or null when there was no envelope.
     */
    public static function fromResponse(int $status, ?int $errorCode, mixed $data): static
    {
        $detail = self::detail($data);

        if ($status === 403 && is_array($data) && ($data['error'] ?? null) === PartnerNotLiveException::ERROR) {
            return PartnerNotLiveException::fromData($data, $errorCode, $status);
        }

        $message = $errorCode === null
            ? sprintf('DrawHouse request failed with HTTP %d%s', $status, $detail === null ? '' : ': '.$detail)
            : sprintf('DrawHouse error %d (HTTP %d): %s', $errorCode, $status, $detail ?? 'DrawHouse returned an error');

        return match (true) {
            $status === 400 || $errorCode === 7 => new InvalidRequestException($message, $errorCode, $status),
            $status === 404 => new NotFoundException($message, $errorCode, $status),
            $status >= 500 || $errorCode === 3 => new ServerException($message, $errorCode, $status),
            default => new static($message, $errorCode, $status),
        };
    }

    public static function envelope(int $errorCode, mixed $data, int $status): static
    {
        return static::fromResponse($status, $errorCode, $data);
    }

    public static function http(int $status): static
    {
        return static::fromResponse($status, null, null);
    }

    public static function malformed(int $status): static
    {
        return new static(
            sprintf('DrawHouse returned HTTP %d without the expected { errorCode, data } envelope', $status),
            null,
            $status,
        );
    }

    public static function missingData(int $status): static
    {
        return new static(
            sprintf('DrawHouse returned HTTP %d without a data key', $status),
            0,
            $status,
        );
    }

    /**
     * HTTP 500 / errorCode 3 is a backend failure that is safe to retry with
     * backoff, and HTTP 503 (webhook secret storage unavailable) means nothing
     * changed, so it is safe to retry later. Nothing else in the error table
     * is retryable.
     */
    public function isRetryable(): bool
    {
        return $this->status === 500 || $this->status === 503 || $this->errorCode === 3;
    }

    private static function detail(mixed $data): ?string
    {
        if (is_scalar($data)) {
            return (string) $data;
        }

        if (is_array($data) && is_scalar($data['error'] ?? null)) {
            return (string) $data['error'];
        }

        return null;
    }
}
