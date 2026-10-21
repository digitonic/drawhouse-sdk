<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Digitonic\DrawHouse\Enums\MoneyKind;
use InvalidArgumentException;

/**
 * Filters for the money feed, GET /v2/partners/transactions and
 * /v2/partners/players/{id}/transactions (Partner API v1.4). Immutable;
 * every method returns a copy.
 *
 *   TransactionsQuery::make()->kind(MoneyKind::DEPOSIT)->status('SUCCESS')->updatedAfter($since)->limit(500)
 *
 * `status` is not validated, because its vocabulary depends on `kind`;
 * combine the two. `createdBefore` must be later than `createdAfter`, and
 * `limit` is clamped to 500. The player filter is ignored on the per-player
 * route.
 */
final readonly class TransactionsQuery
{
    public const DEFAULT_LIMIT = 100;

    public const MAX_LIMIT = 500;

    /**
     * @param  list<MoneyKind>  $kinds
     * @param  list<string>  $statuses  Upper-cased.
     * @param  list<int>  $playerIds
     */
    public function __construct(
        public int $limit = self::DEFAULT_LIMIT,
        public ?CarbonImmutable $createdAfter = null,
        public ?CarbonImmutable $createdBefore = null,
        public ?CarbonImmutable $updatedAfter = null,
        public array $kinds = [],
        public array $statuses = [],
        public array $playerIds = [],
    ) {
        if ($this->limit < 1) {
            throw new InvalidArgumentException('DrawHouse money feed: limit must be a positive integer');
        }

        if ($this->createdAfter !== null && $this->createdBefore !== null && ! $this->createdBefore->greaterThan($this->createdAfter)) {
            throw new InvalidArgumentException(sprintf(
                'DrawHouse money feed: createdBefore (%s) must be later than createdAfter (%s)',
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

    /** Only rows created at or after this time. */
    public function createdAfter(CarbonInterface|string $at): self
    {
        return $this->with(['createdAfter' => self::instant($at)]);
    }

    /** Only rows created before this time. Must be later than createdAfter. */
    public function createdBefore(CarbonInterface|string $at): self
    {
        return $this->with(['createdBefore' => self::instant($at)]);
    }

    /**
     * Only rows changed at or after this time. A row's status changes move
     * `updatedAt`, so an incremental pull picks up deposits, refunds and
     * withdrawals that settled after you first saw them.
     */
    public function updatedAfter(CarbonInterface|string $at): self
    {
        return $this->with(['updatedAfter' => self::instant($at)]);
    }

    /** Only these kinds. Repeatable. */
    public function kind(MoneyKind ...$kinds): self
    {
        return $this->with(['kinds' => array_values(array_unique([...$this->kinds, ...$kinds], SORT_REGULAR))]);
    }

    /** Only these statuses, e.g. `SUCCESS` for deposits. Repeatable; upper-cased. */
    public function status(string ...$statuses): self
    {
        $statuses = array_map(static fn (string $s): string => strtoupper(trim($s)), $statuses);

        return $this->with(['statuses' => array_values(array_unique([...$this->statuses, ...$statuses]))]);
    }

    /** Only these players (`id` on the players feed). Repeatable. */
    public function player(int ...$ids): self
    {
        return $this->with(['playerIds' => array_values(array_unique([...$this->playerIds, ...$ids]))]);
    }

    /**
     * The query string parameters, before the cursor is added. Repeatable
     * parameters are lists; the request serialises them as repeated keys.
     *
     * @param  bool  $forOnePlayer  Leave out the player filter, for the per-player route.
     * @return array<string, string|int|list<string|int>>
     */
    public function toQuery(bool $forOnePlayer = false): array
    {
        $query = ['limit' => $this->limit];

        foreach (['createdAfter' => $this->createdAfter, 'createdBefore' => $this->createdBefore, 'updatedAfter' => $this->updatedAfter] as $key => $at) {
            if ($at !== null) {
                $query[$key] = $at->toIso8601ZuluString();
            }
        }

        if ($this->kinds !== []) {
            $query['kind'] = array_map(static fn (MoneyKind $k): string => $k->value, $this->kinds);
        }

        if ($this->statuses !== []) {
            $query['status'] = $this->statuses;
        }

        if (! $forOnePlayer && $this->playerIds !== []) {
            $query['playerId'] = $this->playerIds;
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

    private static function instant(CarbonInterface|string $at): CarbonImmutable
    {
        return ($at instanceof CarbonInterface ? CarbonImmutable::instance($at) : CarbonImmutable::parse($at))->utc();
    }
}
