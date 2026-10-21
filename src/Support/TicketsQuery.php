<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Digitonic\DrawHouse\Enums\TicketPaymentStatus;
use Digitonic\DrawHouse\Enums\TicketStatus;
use Digitonic\DrawHouse\Enums\TicketType;
use InvalidArgumentException;

/**
 * Filters for the tickets feed, GET /v2/partners/tickets (Partner API v1.4).
 * Immutable; every method returns a copy.
 *
 *   TicketsQuery::make()->updatedAfter($since)->paymentStatus(TicketPaymentStatus::SUCCEEDED)->limit(500)
 *
 * Every filter runs in DrawHouse's database. `createdBefore` must be later
 * than `createdAfter`, and `limit` is clamped to 500.
 */
final readonly class TicketsQuery
{
    public const DEFAULT_LIMIT = 100;

    public const MAX_LIMIT = 500;

    /**
     * @param  list<int>  $gameIds
     * @param  list<int>  $roundIds
     * @param  list<int>  $editionIds
     * @param  list<int>  $playerIds
     * @param  list<TicketStatus>  $statuses
     * @param  list<TicketPaymentStatus>  $paymentStatuses
     * @param  list<TicketType>  $ticketTypes
     */
    public function __construct(
        public int $limit = self::DEFAULT_LIMIT,
        public ?CarbonImmutable $createdAfter = null,
        public ?CarbonImmutable $createdBefore = null,
        public ?CarbonImmutable $updatedAfter = null,
        public array $gameIds = [],
        public array $roundIds = [],
        public array $editionIds = [],
        public array $playerIds = [],
        public array $statuses = [],
        public array $paymentStatuses = [],
        public array $ticketTypes = [],
    ) {
        if ($this->limit < 1) {
            throw new InvalidArgumentException('DrawHouse tickets feed: limit must be a positive integer');
        }

        if ($this->createdAfter !== null && $this->createdBefore !== null && ! $this->createdBefore->greaterThan($this->createdAfter)) {
            throw new InvalidArgumentException(sprintf(
                'DrawHouse tickets feed: createdBefore (%s) must be later than createdAfter (%s)',
                $this->createdBefore->toIso8601ZuluString(),
                $this->createdAfter->toIso8601ZuluString(),
            ));
        }
    }

    public static function make(): self
    {
        return new self;
    }

    /** Page size. Values above 500 are clamped (the API would clamp them too). */
    public function limit(int $limit): self
    {
        return $this->with(['limit' => min($limit, self::MAX_LIMIT)]);
    }

    /** Only tickets bought at or after this time. */
    public function createdAfter(CarbonInterface|string $at): self
    {
        return $this->with(['createdAfter' => self::instant($at)]);
    }

    /** Only tickets bought before this time. Must be later than createdAfter. */
    public function createdBefore(CarbonInterface|string $at): self
    {
        return $this->with(['createdBefore' => self::instant($at)]);
    }

    /**
     * Only tickets changed at or after this time: payment outcome, refunds
     * and draw results all move `updatedAt`. The parameter for incremental
     * pulls.
     */
    public function updatedAfter(CarbonInterface|string $at): self
    {
        return $this->with(['updatedAfter' => self::instant($at)]);
    }

    /** Only tickets in these draws (game ids). Repeatable. */
    public function game(int ...$ids): self
    {
        return $this->with(['gameIds' => self::merge($this->gameIds, $ids)]);
    }

    /** Only tickets in these rounds. Repeatable. */
    public function round(int ...$ids): self
    {
        return $this->with(['roundIds' => self::merge($this->roundIds, $ids)]);
    }

    /** Only tickets bought through these editions. Repeatable. */
    public function edition(int ...$ids): self
    {
        return $this->with(['editionIds' => self::merge($this->editionIds, $ids)]);
    }

    /** Only tickets bought by these players (`id` on the players feed). Repeatable. */
    public function player(int ...$ids): self
    {
        return $this->with(['playerIds' => self::merge($this->playerIds, $ids)]);
    }

    /** Filter by ticket status. Repeatable. */
    public function status(TicketStatus ...$statuses): self
    {
        return $this->with(['statuses' => self::merge($this->statuses, $statuses)]);
    }

    /** Filter by payment status. Repeatable. Most callers want SUCCEEDED. */
    public function paymentStatus(TicketPaymentStatus ...$statuses): self
    {
        return $this->with(['paymentStatuses' => self::merge($this->paymentStatuses, $statuses)]);
    }

    /** Filter by ticket type. Repeatable. */
    public function ticketType(TicketType ...$types): self
    {
        return $this->with(['ticketTypes' => self::merge($this->ticketTypes, $types)]);
    }

    /**
     * The query string parameters, before the cursor is added. Repeatable
     * parameters are lists; the request serialises them as repeated keys.
     *
     * @return array<string, string|int|list<string|int>>
     */
    public function toQuery(): array
    {
        $query = ['limit' => $this->limit];

        foreach (['createdAfter' => $this->createdAfter, 'createdBefore' => $this->createdBefore, 'updatedAfter' => $this->updatedAfter] as $key => $at) {
            if ($at !== null) {
                $query[$key] = $at->toIso8601ZuluString();
            }
        }

        $lists = [
            'gameId' => $this->gameIds,
            'roundId' => $this->roundIds,
            'editionId' => $this->editionIds,
            'playerId' => $this->playerIds,
            'status' => array_map(static fn (TicketStatus $s): string => $s->value, $this->statuses),
            'paymentStatus' => array_map(static fn (TicketPaymentStatus $s): string => $s->value, $this->paymentStatuses),
            'ticketType' => array_map(static fn (TicketType $t): string => $t->value, $this->ticketTypes),
        ];

        foreach ($lists as $key => $values) {
            if ($values !== []) {
                $query[$key] = $values;
            }
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }

    /**
     * @template T
     *
     * @param  list<T>  $current
     * @param  array<T>  $added
     * @return list<T>
     */
    private static function merge(array $current, array $added): array
    {
        return array_values(array_unique([...$current, ...$added], SORT_REGULAR));
    }

    private static function instant(CarbonInterface|string $at): CarbonImmutable
    {
        return ($at instanceof CarbonInterface ? CarbonImmutable::instance($at) : CarbonImmutable::parse($at))->utc();
    }
}
