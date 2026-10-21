<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Tickets;

use Digitonic\DrawHouse\DTO\Ticket;
use Digitonic\DrawHouse\Support\Parse;

/**
 * One offset page of GET /v2/games/tickets?gameId= (Partner API v1.4):
 * `data.tickets`, `data.total`, `data.limit` and `data.offset`.
 *
 * `total` counts every matching ticket across all pages, so keep paging
 * while `offset + limit < total`. Before v1.4 the route was not paged and
 * sent no `total`; then the page is everything.
 */
final readonly class GameTicketsPage
{
    /**
     * @param  list<Ticket>  $tickets
     * @param  int|null  $total  Every matching ticket, across all pages.
     * @param  int|null  $limit  The page size applied, after the default and the clamp.
     * @param  int|null  $offset  The offset applied.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public array $tickets,
        public ?int $total,
        public ?int $limit,
        public ?int $offset,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tickets: array_map(Ticket::fromArray(...), Parse::items($data['tickets'] ?? [])),
            total: Parse::int($data['total'] ?? null),
            limit: Parse::int($data['limit'] ?? null),
            offset: Parse::int($data['offset'] ?? null),
            raw: $data,
        );
    }

    /**
     * Whether more tickets follow this page. A page without `total` (the
     * pre-v1.4 response) or an empty page is the last.
     */
    public function hasMore(): bool
    {
        if ($this->total === null || $this->tickets === []) {
            return false;
        }

        return ($this->offset ?? 0) + count($this->tickets) < $this->total;
    }

    /**
     * The offset of the page after this one.
     */
    public function nextOffset(): int
    {
        return ($this->offset ?? 0) + count($this->tickets);
    }
}
