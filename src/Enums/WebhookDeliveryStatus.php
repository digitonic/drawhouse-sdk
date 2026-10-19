<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Enums;

/**
 * `status` of one row of the webhook delivery log.
 */
enum WebhookDeliveryStatus: string
{
    /** Waiting for its first or next attempt; see `nextAttemptAt`. */
    case PENDING = 'PENDING';

    /** An attempt is in flight. */
    case SENDING = 'SENDING';

    /** Your server answered 2xx. */
    case DELIVERED = 'DELIVERED';

    /**
     * Given up after 8 attempts, or parked because the endpoint was disabled
     * or deleted. DEAD deliveries are not resent, even when the endpoint is
     * re-enabled.
     */
    case DEAD = 'DEAD';

    /**
     * True once DrawHouse will make no further attempt.
     */
    public function isFinal(): bool
    {
        return $this === self::DELIVERED || $this === self::DEAD;
    }
}
