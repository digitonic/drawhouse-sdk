<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Digitonic\DrawHouse\DTO\LiveDraws\ImageSet;
use Digitonic\DrawHouse\DTO\LiveDraws\InstantWins;
use Digitonic\DrawHouse\DTO\LiveDraws\LiveDrawStatus;
use Digitonic\DrawHouse\DTO\LiveDraws\LiveEdition;
use Digitonic\DrawHouse\DTO\LiveDraws\LivePrize;
use Digitonic\DrawHouse\DTO\LiveDraws\TicketCounts;
use Digitonic\DrawHouse\Enums\RaffleEntryType;
use Digitonic\DrawHouse\Enums\RoundState;
use Digitonic\DrawHouse\Support\Parse;

/**
 * One item of GET /v2/partners/charity-raffles/live (`data.draws`) or the
 * object from /live/{gameId}: everything an "enter now" widget
 * needs for one of your editions.
 *
 * Responses are CACHED SERVER-SIDE FOR 15 SECONDS (`Cache-Control: public,
 * max-age=15`); poll every 15–30 seconds, no faster. `asOf` is when the
 * snapshot was built and can be up to 15 seconds old.
 *
 * Count down to countdownTarget(): `schedule.liveDrawAt` when a live draw is
 * armed, otherwise `schedule.drawAt`. NEVER `scheduledDrawTime`, which is the
 * advertised time and is usually 15 minutes AFTER `drawAt` (entries close
 * at `drawAt`).
 *
 * `instantWins` is only present when the round has instant-win prizes;
 * `liveDraw` only after the round is resulted. Both are null otherwise.
 */
final readonly class LiveDraw
{
    /**
     * @param  array<string, ImageSet>  $images  Keyed by surface (`hero`, `lobby-card`, …).
     * @param  RoundSchedule  $schedule  The same flat timestamps as the games catalogue.
     * @param  CarbonImmutable|null  $scheduledDrawTime  The ADVERTISED draw time. Not the countdown target.
     * @param  list<LivePrize>  $prizes  Your edition's names, descriptions and images applied.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $gameId,
        public ?string $gameName,
        public ?int $roundId,
        public ?RoundState $roundState,
        public ?RaffleEntryType $entryType,
        public LiveEdition $edition,
        public array $images,
        public TicketCounts $tickets,
        public RoundSchedule $schedule,
        public ?NextTransition $nextTransition,
        public ?CarbonImmutable $scheduledDrawTime,
        public array $prizes,
        public ?InstantWins $instantWins,
        public ?LiveDrawStatus $liveDraw,
        public ?CarbonImmutable $asOf,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $object = static fn (mixed $value): array => is_array($value) ? $value : [];

        $images = [];

        foreach ($object($data['images'] ?? null) as $surface => $image) {
            if (is_array($image)) {
                $images[(string) $surface] = ImageSet::fromArray($image);
            }
        }

        return new self(
            gameId: Parse::int($data['gameId'] ?? null),
            gameName: Parse::string($data['gameName'] ?? null),
            roundId: Parse::int($data['roundId'] ?? null),
            roundState: Parse::enum(RoundState::class, $data['roundState'] ?? null),
            entryType: Parse::enum(RaffleEntryType::class, $data['entryType'] ?? null),
            edition: LiveEdition::fromArray($object($data['edition'] ?? null)),
            images: $images,
            tickets: TicketCounts::fromArray($object($data['tickets'] ?? null)),
            schedule: RoundSchedule::fromArray($object($data['schedule'] ?? null)),
            nextTransition: is_array($data['nextTransition'] ?? null) ? NextTransition::fromArray($data['nextTransition']) : null,
            scheduledDrawTime: Parse::dateTime($data['scheduledDrawTime'] ?? null),
            prizes: array_map(LivePrize::fromArray(...), Parse::items($data['prizes'] ?? [])),
            instantWins: is_array($data['instantWins'] ?? null) ? InstantWins::fromArray($data['instantWins']) : null,
            liveDraw: is_array($data['liveDraw'] ?? null) ? LiveDrawStatus::fromArray($data['liveDraw']) : null,
            asOf: Parse::dateTime($data['asOf'] ?? null),
            raw: $data,
        );
    }

    /**
     * True while a live draw is armed: `schedule.liveDrawAt` is only present
     * then.
     */
    public function isLiveDrawArmed(): bool
    {
        return $this->schedule->liveDrawAt !== null;
    }

    /**
     * The instant to count down to: `schedule.liveDrawAt` when a live draw
     * is armed, otherwise `schedule.drawAt` (when entries close and the draw
     * runs). Never `scheduledDrawTime`, which is the advertised time and is
     * typically 15 minutes later than `drawAt`. Null when neither is set.
     */
    public function countdownTarget(): ?CarbonImmutable
    {
        return $this->schedule->liveDrawAt ?? $this->schedule->drawAt;
    }

    /**
     * Whole seconds from `$now` until countdownTarget(), floored at 0 once
     * the target has passed. Null when there is no target.
     */
    public function secondsUntilDraw(?CarbonInterface $now = null): ?int
    {
        $target = $this->countdownTarget();

        if ($target === null) {
            return null;
        }

        $now = $now === null ? CarbonImmutable::now('UTC') : CarbonImmutable::instance($now);

        return max(0, (int) floor($now->diffInSeconds($target, false)));
    }

    /**
     * Entries are open: the round is OPEN and tickets remain.
     */
    public function isOpenForEntry(): bool
    {
        return $this->roundState === RoundState::OPEN && ! $this->tickets->isSoldOut();
    }

    public function image(string $surface, ?string $variant = null): ?string
    {
        return ($this->images[$surface] ?? null)?->url($variant);
    }

    /**
     * @return list<LivePrize>
     */
    public function drawPrizes(): array
    {
        return array_values(array_filter($this->prizes, static fn (LivePrize $p): bool => ! $p->isInstant()));
    }

    /**
     * @return list<LivePrize>
     */
    public function instantPrizes(): array
    {
        return array_values(array_filter($this->prizes, static fn (LivePrize $p): bool => $p->isInstant()));
    }
}
