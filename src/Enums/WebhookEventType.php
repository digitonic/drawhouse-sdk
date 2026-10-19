<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Enums;

/**
 * The `type` of a webhook event, also sent as the `X-DrawHouse-Event` header.
 *
 * The first three can be subscribed to on an endpoint (`events`).
 * `webhook.ping` is sent only by POST /v2/partners/webhooks/{id}/test and
 * cannot be subscribed to.
 */
enum WebhookEventType: string
{
    /** A round on one of your draws moved into OPEN, RESULTED, LIVE_DRAW, COMPLETED or CANCELLED. */
    case ROUND_STATE_CHANGED = 'round.state_changed';

    /** One of your players completed registration (email verified or Google sign-up). */
    case PLAYER_REGISTERED = 'player.registered';

    /** A player's account status, consent or profile changed. */
    case PLAYER_UPDATED = 'player.updated';

    /** Test ping from the test route. */
    case PING = 'webhook.ping';

    /**
     * Whether the type can be listed in an endpoint's `events`.
     */
    public function isSubscribable(): bool
    {
        return $this !== self::PING;
    }

    /**
     * Every type an endpoint can subscribe to.
     *
     * @return list<self>
     */
    public static function subscribable(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $type): bool => $type->isSubscribable()));
    }
}
