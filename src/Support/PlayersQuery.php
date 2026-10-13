<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Digitonic\DrawHouse\Enums\AccountStatus;
use Digitonic\DrawHouse\Enums\AcquisitionChannel;
use InvalidArgumentException;

/**
 * Filters for GET /v2/partners/players. Immutable; every
 * `with*` returns a copy.
 *
 *   PlayersQuery::make()->updatedAfter($since)->limit(500)->includeLifetime()
 *
 * Validation that the API would otherwise answer 400 for is done here:
 * `createdBefore` must be later than `createdAfter`, and `limit` is clamped
 * to the maximum of 500.
 */
final readonly class PlayersQuery
{
    public const DEFAULT_LIMIT = 100;

    public const MAX_LIMIT = 500;

    /**
     * @param  list<AccountStatus>  $statuses
     * @param  list<AcquisitionChannel>  $acquisitionChannels
     */
    public function __construct(
        public int $limit = self::DEFAULT_LIMIT,
        public ?CarbonImmutable $createdAfter = null,
        public ?CarbonImmutable $createdBefore = null,
        public ?CarbonImmutable $updatedAfter = null,
        public array $statuses = [],
        public array $acquisitionChannels = [],
        public ?bool $marketable = null,
        public bool $includeLifetime = false,
    ) {
        if ($this->limit < 1) {
            throw new InvalidArgumentException('DrawHouse players feed: limit must be a positive integer');
        }

        if ($this->createdAfter !== null && $this->createdBefore !== null && ! $this->createdBefore->greaterThan($this->createdAfter)) {
            throw new InvalidArgumentException(sprintf(
                'DrawHouse players feed: createdBefore (%s) must be later than createdAfter (%s)',
                $this->createdBefore->toIso8601ZuluString(),
                $this->createdAfter->toIso8601ZuluString(),
            ));
        }
    }

    public static function make(): self
    {
        return new self;
    }

    /**
     * Page size. Values above 500 are clamped (the API would clamp them too).
     */
    public function limit(int $limit): self
    {
        return $this->with(limit: min($limit, self::MAX_LIMIT));
    }

    /** Only players registered at or after this time. */
    public function createdAfter(CarbonInterface|string $at): self
    {
        return $this->with(createdAfter: self::instant($at));
    }

    /** Only players registered before this time. Must be later than createdAfter. */
    public function createdBefore(CarbonInterface|string $at): self
    {
        return $this->with(createdBefore: self::instant($at));
    }

    /**
     * Only players changed at or after this time: any change to the player,
     * their consents, status or purchases moves `updatedAt`. This is the
     * parameter for daily incremental pulls. (A time-out ending does NOT
     * move it — see PlayerDetail::isTimedOutAt().)
     */
    public function updatedAfter(CarbonInterface|string $at): self
    {
        return $this->with(updatedAfter: self::instant($at));
    }

    /** Filter by accountStatus. Repeatable. */
    public function status(AccountStatus ...$statuses): self
    {
        return $this->with(statuses: array_values(array_unique([...$this->statuses, ...$statuses], SORT_REGULAR)));
    }

    /** Filter by DrawHouse's derived acquisitionChannel. Repeatable. */
    public function acquisitionChannel(AcquisitionChannel ...$channels): self
    {
        return $this->with(acquisitionChannels: array_values(array_unique([...$this->acquisitionChannels, ...$channels], SORT_REGULAR)));
    }

    /** Filter on the derived `marketable` flag. */
    public function marketable(bool $marketable = true): self
    {
        return $this->with(marketable: $marketable);
    }

    /** Add the GBP lifetime money block to each row (off by default on the list). */
    public function includeLifetime(bool $include = true): self
    {
        return $this->with(includeLifetime: $include);
    }

    /**
     * The query string parameters, before the cursor is added. Repeatable
     * parameters are lists; the request serialises them as repeated keys.
     *
     * @return array<string, string|int|list<string>>
     */
    public function toQuery(): array
    {
        $query = ['limit' => $this->limit];

        if ($this->createdAfter !== null) {
            $query['createdAfter'] = $this->createdAfter->toIso8601ZuluString();
        }

        if ($this->createdBefore !== null) {
            $query['createdBefore'] = $this->createdBefore->toIso8601ZuluString();
        }

        if ($this->updatedAfter !== null) {
            $query['updatedAfter'] = $this->updatedAfter->toIso8601ZuluString();
        }

        if ($this->statuses !== []) {
            $query['status'] = array_map(static fn (AccountStatus $s): string => $s->value, $this->statuses);
        }

        if ($this->acquisitionChannels !== []) {
            $query['acquisitionChannel'] = array_map(static fn (AcquisitionChannel $c): string => $c->value, $this->acquisitionChannels);
        }

        if ($this->marketable !== null) {
            $query['marketable'] = $this->marketable ? 'true' : 'false';
        }

        if ($this->includeLifetime) {
            $query['include'] = 'lifetime';
        }

        return $query;
    }

    /**
     * @param  list<AccountStatus>|null  $statuses
     * @param  list<AcquisitionChannel>|null  $acquisitionChannels
     */
    private function with(
        ?int $limit = null,
        ?CarbonImmutable $createdAfter = null,
        ?CarbonImmutable $createdBefore = null,
        ?CarbonImmutable $updatedAfter = null,
        ?array $statuses = null,
        ?array $acquisitionChannels = null,
        ?bool $marketable = null,
        ?bool $includeLifetime = null,
    ): self {
        return new self(
            limit: $limit ?? $this->limit,
            createdAfter: $createdAfter ?? $this->createdAfter,
            createdBefore: $createdBefore ?? $this->createdBefore,
            updatedAfter: $updatedAfter ?? $this->updatedAfter,
            statuses: $statuses ?? $this->statuses,
            acquisitionChannels: $acquisitionChannels ?? $this->acquisitionChannels,
            marketable: $marketable ?? $this->marketable,
            includeLifetime: $includeLifetime ?? $this->includeLifetime,
        );
    }

    private static function instant(CarbonInterface|string $at): CarbonImmutable
    {
        return ($at instanceof CarbonInterface ? CarbonImmutable::instance($at) : CarbonImmutable::parse($at))->utc();
    }
}
