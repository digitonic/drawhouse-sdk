<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Tickets;

use Digitonic\DrawHouse\Support\Parse;

/**
 * One cursor page of the tickets feed, GET /v2/partners/tickets:
 * `data.items` and `data.nextCursor`.
 *
 * Every filter runs in DrawHouse's database, so a page is `limit` rows
 * unless the tickets ran out, and `nextCursor` is set only on a full page.
 */
final readonly class PartnerTicketPage
{
    /**
     * @param  list<PartnerTicket>  $items  Ordered by id ascending.
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
            items: array_map(PartnerTicket::fromArray(...), Parse::items($data['items'] ?? [])),
            nextCursor: Parse::nonEmptyString($data['nextCursor'] ?? null),
            raw: $data,
        );
    }

    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }
}
