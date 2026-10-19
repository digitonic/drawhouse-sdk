<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Webhooks;

use Digitonic\DrawHouse\Support\Parse;

/**
 * One cursor page of GET /v2/partners/webhooks/{id}/deliveries:
 * `data.items` and `data.nextCursor`, oldest first.
 *
 * `nextCursor` is set whenever the page is full, so the page after it can be
 * empty; it is null otherwise. Only `nextCursor` should drive paging.
 */
final readonly class WebhookDeliveriesPage
{
    /**
     * @param  list<WebhookDelivery>  $items
     * @param  string|null  $nextCursor  Pass back as `after`. Null means you have everything.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            items: array_map(WebhookDelivery::fromArray(...), Parse::items($data['items'] ?? [])),
            nextCursor: Parse::nonEmptyString($data['nextCursor'] ?? null),
            raw: $data,
        );
    }

    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }

    public function count(): int
    {
        return count($this->items);
    }
}
