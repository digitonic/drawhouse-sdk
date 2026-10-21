<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Webhooks;

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\Enums\WebhookEventType;
use Digitonic\DrawHouse\Support\Parse;
use InvalidArgumentException;
use JsonException;

/**
 * The JSON body of every webhook request, and the shape the test route
 * returns: `{ id, type, occurredAt, affiliateId, resource, data }`.
 *
 * - Deduplicate on `id`: retries carry the same id.
 * - Order on `occurredAt`, not on arrival.
 *
 * `data` is typed by `type`: RoundStateChangedData, PlayerData (registered
 * and updated), TicketPurchasedData, InstantWinData, WalletMovementData
 * (credited and debited), DrawWonData or PingData. For a type this SDK does
 * not know yet it is the raw array, and `type` is null (`typeName` keeps the
 * wire value).
 *
 * Build one from a verified request with WebhookVerifier::event(), or from a
 * body you have already verified with fromJson().
 */
final readonly class WebhookEvent
{
    /**
     * @param  int|null  $id  Event id. Deduplicate on this.
     * @param  WebhookEventType|null  $type  Null for a type this SDK does not know.
     * @param  string|null  $typeName  `type` as sent.
     * @param  int|null  $affiliateId  Your affiliate id.
     * @param  RoundStateChangedData|PlayerData|TicketPurchasedData|InstantWinData|WalletMovementData|DrawWonData|PingData|array<string, mixed>  $data
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $id,
        public ?WebhookEventType $type,
        public ?string $typeName,
        public ?CarbonImmutable $occurredAt,
        public ?int $affiliateId,
        public WebhookResource $resource,
        public RoundStateChangedData|PlayerData|TicketPurchasedData|InstantWinData|WalletMovementData|DrawWonData|PingData|array $data,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $type = Parse::enum(WebhookEventType::class, $data['type'] ?? null);
        $payload = is_array($data['data'] ?? null) ? $data['data'] : [];

        return new self(
            id: Parse::int($data['id'] ?? null),
            type: $type,
            typeName: Parse::nonEmptyString($data['type'] ?? null),
            occurredAt: Parse::dateTime($data['occurredAt'] ?? null),
            affiliateId: Parse::int($data['affiliateId'] ?? null),
            resource: WebhookResource::fromArray(is_array($data['resource'] ?? null) ? $data['resource'] : []),
            data: match ($type) {
                WebhookEventType::ROUND_STATE_CHANGED => RoundStateChangedData::fromArray($payload),
                WebhookEventType::PLAYER_REGISTERED, WebhookEventType::PLAYER_UPDATED => PlayerData::fromArray($payload),
                WebhookEventType::TICKET_PURCHASED => TicketPurchasedData::fromArray($payload),
                WebhookEventType::INSTANT_WIN_REVEALED => InstantWinData::fromArray($payload),
                WebhookEventType::WALLET_CREDITED, WebhookEventType::WALLET_DEBITED => WalletMovementData::fromArray($payload),
                WebhookEventType::DRAW_WON => DrawWonData::fromArray($payload),
                WebhookEventType::PING => PingData::fromArray($payload),
                null => $payload,
            },
            raw: $data,
        );
    }

    /**
     * Parse a raw request body. Does NOT check the signature; use
     * WebhookVerifier::event() for an incoming request.
     *
     * @throws InvalidArgumentException when the body is not a JSON object
     */
    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('DrawHouse webhook body is not valid JSON: '.$e->getMessage(), 0, $e);
        }

        if (! is_array($data) || array_is_list($data)) {
            throw new InvalidArgumentException('DrawHouse webhook body is not a JSON object');
        }

        return self::fromArray($data);
    }

    public function is(WebhookEventType $type): bool
    {
        return $this->type === $type;
    }

    public function isPing(): bool
    {
        return $this->type === WebhookEventType::PING;
    }

    /**
     * The typed data of a `round.state_changed` event, else null.
     */
    public function roundStateChanged(): ?RoundStateChangedData
    {
        return $this->data instanceof RoundStateChangedData ? $this->data : null;
    }

    /**
     * The typed data of a `player.registered` or `player.updated` event, else null.
     */
    public function player(): ?PlayerData
    {
        return $this->data instanceof PlayerData ? $this->data : null;
    }

    /**
     * The typed data of a `ticket.purchased` event, else null.
     */
    public function ticketPurchased(): ?TicketPurchasedData
    {
        return $this->data instanceof TicketPurchasedData ? $this->data : null;
    }

    /**
     * The typed data of an `instant_win.revealed` event, else null.
     */
    public function instantWin(): ?InstantWinData
    {
        return $this->data instanceof InstantWinData ? $this->data : null;
    }

    /**
     * The typed data of a `wallet.credited` or `wallet.debited` event, else
     * null. isWalletCredit() tells the two apart.
     */
    public function walletMovement(): ?WalletMovementData
    {
        return $this->data instanceof WalletMovementData ? $this->data : null;
    }

    /**
     * Whether this is a `wallet.credited` event (money into the wallet).
     */
    public function isWalletCredit(): bool
    {
        return $this->type === WebhookEventType::WALLET_CREDITED;
    }

    /**
     * The typed data of a `draw.won` event, else null.
     */
    public function drawWon(): ?DrawWonData
    {
        return $this->data instanceof DrawWonData ? $this->data : null;
    }

    /**
     * The typed data of a `webhook.ping` event, else null.
     */
    public function ping(): ?PingData
    {
        return $this->data instanceof PingData ? $this->data : null;
    }
}
