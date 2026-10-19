<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Webhooks;

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\Enums\WebhookEventType;
use Digitonic\DrawHouse\Support\Parse;

/**
 * One registered webhook endpoint, from GET /v2/partners/webhooks[/{id}]
 * and PATCH /v2/partners/webhooks/{id}. Never carries the signing secret;
 * see WebhookEndpointWithSecret.
 */
final readonly class WebhookEndpoint
{
    /**
     * Failures in a row at which DrawHouse switch an endpoint off.
     */
    public const DISABLE_AFTER_FAILURES = 50;

    /**
     * @param  list<WebhookEventType>  $events  Subscribed types. Empty means every type.
     * @param  bool|null  $active  False when you disabled it, or DrawHouse did after 50 consecutive failures.
     * @param  int|null  $consecutiveFailures  Failed deliveries in a row; reset by any 2xx.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $id,
        public ?string $url,
        public array $events,
        public ?bool $active,
        public ?string $description,
        public ?int $consecutiveFailures,
        public ?CarbonImmutable $lastSuccessAt,
        public ?CarbonImmutable $lastFailureAt,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $events = is_array($data['events'] ?? null) ? $data['events'] : [];

        return new self(
            id: Parse::int($data['id'] ?? null),
            url: Parse::nonEmptyString($data['url'] ?? null),
            events: array_values(array_filter(array_map(
                static fn (mixed $type): ?WebhookEventType => Parse::enum(WebhookEventType::class, $type),
                $events,
            ))),
            active: Parse::bool($data['active'] ?? null),
            description: Parse::nonEmptyString($data['description'] ?? null),
            consecutiveFailures: Parse::int($data['consecutiveFailures'] ?? null),
            lastSuccessAt: Parse::dateTime($data['lastSuccessAt'] ?? null),
            lastFailureAt: Parse::dateTime($data['lastFailureAt'] ?? null),
            createdAt: Parse::dateTime($data['createdAt'] ?? null),
            updatedAt: Parse::dateTime($data['updatedAt'] ?? null),
            raw: $data,
        );
    }

    public function isActive(): bool
    {
        return $this->active === true;
    }

    /**
     * Whether events of `$type` are sent here: an empty `events` list means
     * every type. A ping from the test route always is.
     */
    public function receives(WebhookEventType $type): bool
    {
        return $type === WebhookEventType::PING || $this->events === [] || in_array($type, $this->events, true);
    }

    /**
     * Inactive with 50 or more failures in a row: DrawHouse switched it off.
     * Re-enable with updateWebhook(active: true) once the receiver is fixed;
     * deliveries that went DEAD meanwhile are not resent.
     */
    public function wasAutoDisabled(): bool
    {
        return $this->active === false && ($this->consecutiveFailures ?? 0) >= self::DISABLE_AFTER_FAILURES;
    }
}
