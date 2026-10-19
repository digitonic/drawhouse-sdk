<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Exceptions;

use RuntimeException;

/**
 * An incoming webhook request failed verification: the signature header is
 * missing or malformed, the timestamp is outside the tolerance, no configured
 * secret produced the signature, or the verified body is not an event.
 *
 * Answer the request with a 4xx and do not act on it. DrawHouse retry
 * anything that is not a 2xx, so a request rejected only because a secret
 * rotation has not been picked up yet will arrive again.
 *
 * `reason` is one of the class constants. The message never contains a
 * secret or the expected signature.
 */
final class WebhookVerificationException extends RuntimeException
{
    public const MISSING_SIGNATURE = 'missing_signature';

    public const MALFORMED_SIGNATURE = 'malformed_signature';

    public const TIMESTAMP_OUTSIDE_TOLERANCE = 'timestamp_outside_tolerance';

    public const SIGNATURE_MISMATCH = 'signature_mismatch';

    public const MALFORMED_PAYLOAD = 'malformed_payload';

    public function __construct(
        string $message,
        public readonly string $reason,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function missingSignature(): self
    {
        return new self('DrawHouse webhook request has no X-DrawHouse-Signature header', self::MISSING_SIGNATURE);
    }

    public static function malformedSignature(string $why): self
    {
        return new self('DrawHouse webhook signature header is malformed: '.$why, self::MALFORMED_SIGNATURE);
    }

    public static function timestampOutsideTolerance(int $timestamp, int $now, int $tolerance): self
    {
        return new self(
            sprintf(
                'DrawHouse webhook signature timestamp %d is %d seconds from now, outside the %d-second tolerance',
                $timestamp,
                abs($now - $timestamp),
                $tolerance,
            ),
            self::TIMESTAMP_OUTSIDE_TOLERANCE,
        );
    }

    public static function signatureMismatch(int $secrets): self
    {
        return new self(
            sprintf('DrawHouse webhook signature does not match any of the %d configured secret(s)', $secrets),
            self::SIGNATURE_MISMATCH,
        );
    }

    public static function malformedPayload(string $why, ?\Throwable $previous = null): self
    {
        return new self('DrawHouse webhook body is not a valid event: '.$why, self::MALFORMED_PAYLOAD, $previous);
    }

    /**
     * True when the request is genuine but too old (or too far ahead): a
     * replay, a stuck queue on either side, or a clock problem.
     */
    public function isExpired(): bool
    {
        return $this->reason === self::TIMESTAMP_OUTSIDE_TOLERANCE;
    }
}
