<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO;

use Digitonic\DrawHouse\Support\Parse;

/**
 * One cursor page of GET /v2/partners/players: `data.items` and
 * `data.nextCursor`.
 *
 * Filtered pages are FILLED: `status`, `acquisitionChannel` and `marketable`
 * are applied after each page is read and the server keeps reading until it
 * has `limit` matching players or runs out. So, unlike the games endpoint, a
 * page shorter than `limit` IS the end of the data, and `nextCursor` is null
 * exactly then. Only `nextCursor` should drive paging.
 */
final readonly class PlayerPage
{
    /**
     * @param  list<PlayerDetail>  $items  Ordered by id ascending.
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
            items: array_map(PlayerDetail::fromArray(...), Parse::items($data['items'] ?? [])),
            nextCursor: Parse::nonEmptyString($data['nextCursor'] ?? null),
            raw: $data,
        );
    }

    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }

    public function isLastPage(): bool
    {
        return $this->nextCursor === null;
    }

    public function count(): int
    {
        return count($this->items);
    }
}
