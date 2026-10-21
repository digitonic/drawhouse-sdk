<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Money;

use Digitonic\DrawHouse\Support\Parse;

/**
 * One page of the money feed: `data.items` and `data.nextCursor`.
 *
 * Rows are ordered by `createdAt`, then `kind`, then row id. The cursor is
 * OPAQUE: send it back as `after` exactly as received. It is set only on a
 * full page, because every filter runs in DrawHouse's database.
 */
final readonly class MoneyEventPage
{
    /**
     * @param  list<MoneyEvent>  $items
     * @param  string|null  $nextCursor  Pass back as `after`, verbatim. Null means you have everything.
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
            items: array_map(MoneyEvent::fromArray(...), Parse::items($data['items'] ?? [])),
            nextCursor: Parse::nonEmptyString($data['nextCursor'] ?? null),
            raw: $data,
        );
    }

    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }
}
