<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Webhooks;

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\Enums\WebhookDeliveryStatus;
use Digitonic\DrawHouse\Enums\WebhookEventType;
use Digitonic\DrawHouse\Support\Parse;

/**
 * One row of the delivery log, GET /v2/partners/webhooks/{id}/deliveries: one
 * event's series of attempts to one endpoint. Rows do not carry the event
 * body; `eventId` links a row to the event you received.
 *
 * DrawHouse make up to 8 attempts. A DEAD delivery is never resent.
 */
final readonly class WebhookDelivery
{
    /**
     * @param  int|null  $id  Delivery id: the `X-DrawHouse-Delivery` header, stable across retries.
     * @param  int|null  $eventId  The event `id` carried in the body.
     * @param  int|null  $attempt  Attempts made so far (0 before the first).
     * @param  CarbonImmutable|null  $nextAttemptAt  Present while PENDING.
     * @param  int|null  $lastResponseCode  HTTP status your server last answered; null when the connection failed.
     * @param  string|null  $lastError  `http 500`, a transport error, `endpoint inactive` or `endpoint deleted`.
     * @param  CarbonImmutable|null  $createdAt  When the delivery was queued.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $id,
        public ?int $eventId,
        public ?WebhookEventType $eventType,
        public ?string $eventTypeName,
        public ?WebhookResource $resource,
        public ?CarbonImmutable $occurredAt,
        public ?int $endpointId,
        public ?WebhookDeliveryStatus $status,
        public ?int $attempt,
        public ?CarbonImmutable $nextAttemptAt,
        public ?int $lastResponseCode,
        public ?string $lastError,
        public ?CarbonImmutable $deliveredAt,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Parse::int($data['id'] ?? null),
            eventId: Parse::int($data['eventId'] ?? null),
            eventType: Parse::enum(WebhookEventType::class, $data['eventType'] ?? null),
            eventTypeName: Parse::nonEmptyString($data['eventType'] ?? null),
            resource: is_array($data['resource'] ?? null) ? WebhookResource::fromArray($data['resource']) : null,
            occurredAt: Parse::dateTime($data['occurredAt'] ?? null),
            endpointId: Parse::int($data['endpointId'] ?? null),
            status: Parse::enum(WebhookDeliveryStatus::class, $data['status'] ?? null),
            attempt: Parse::int($data['attempt'] ?? null),
            nextAttemptAt: Parse::dateTime($data['nextAttemptAt'] ?? null),
            lastResponseCode: Parse::int($data['lastResponseCode'] ?? null),
            lastError: Parse::nonEmptyString($data['lastError'] ?? null),
            deliveredAt: Parse::dateTime($data['deliveredAt'] ?? null),
            createdAt: Parse::dateTime($data['createdAt'] ?? null),
            updatedAt: Parse::dateTime($data['updatedAt'] ?? null),
            raw: $data,
        );
    }

    public function isDelivered(): bool
    {
        return $this->status === WebhookDeliveryStatus::DELIVERED;
    }

    public function isDead(): bool
    {
        return $this->status === WebhookDeliveryStatus::DEAD;
    }
}
