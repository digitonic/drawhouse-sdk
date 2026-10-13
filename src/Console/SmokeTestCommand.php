<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Console;

use Digitonic\DrawHouse\DrawHouse;
use Digitonic\DrawHouse\Exceptions\DrawHouseException;
use Digitonic\DrawHouse\Exceptions\NotFoundException;
use Digitonic\DrawHouse\Exceptions\PartnerNotLiveException;
use Digitonic\DrawHouse\Support\PlayersQuery;
use Illuminate\Console\Command;
use Throwable;

/**
 * OPT-IN live smoke test against the real DrawHouse API, for verifying the
 * v1.2 surface once credentials are issued. Never run by
 * the test suite or any scheduler.
 *
 * Reads DRAWHOUSE_EMAIL / DRAWHOUSE_PASSWORD from config (env). Prints ids,
 * counts and statuses only: never tokens, never player PII.
 */
final class SmokeTestCommand extends Command
{
    protected $signature = 'drawhouse:smoke
        {--players : Also pull one page of the players feed (requires a LIVE partner)}
        {--live : Also pull the live draws list (requires affiliateId)}
        {--tickets : Also pull the tickets of the first live draw (requires --live)}
        {--reports : Also pull yesterday\'s daily report}';

    protected $description = 'Opt-in live smoke test of the DrawHouse Partner API v1.2 surface (reads credentials from env; prints no tokens or PII)';

    public function handle(DrawHouse $drawhouse): int
    {
        $base = (string) config('drawhouse.base_url');
        $this->line("Base URL: {$base}");

        if (! $this->step('login', fn () => $drawhouse->login()->isComplete() ? 'token pair received' : 'INCOMPLETE token pair')) {
            return self::FAILURE;
        }

        $me = null;
        $this->step('GET /v2/partners/me', function () use ($drawhouse, &$me): string {
            $me = $drawhouse->me();

            return sprintf(
                'status=%s affiliateId=%s playerId=%s company=%s crmInScope=%s',
                $me->status ?? 'null',
                $me->affiliateId ?? 'null',
                $me->playerId ?? 'null',
                $me->companyName ?? 'null',
                var_export($me->crmInScope, true),
            );
        });

        if ($me !== null && ! $me->isLive()) {
            $this->warn('Partner is not LIVE; the data endpoints will answer 403 partner_not_live.');
        }

        if ($this->option('players')) {
            $this->step('GET /v2/partners/players?limit=1', function () use ($drawhouse): string {
                $page = $drawhouse->playersPage(PlayersQuery::make()->limit(1)->includeLifetime());
                $first = $page->items[0] ?? null;

                return sprintf(
                    'items=%d nextCursor=%s firstId=%s status=%s channel=%s marketable=%s lifetime=%s',
                    $page->count(),
                    $page->nextCursor ?? 'null',
                    $first?->id ?? 'n/a',
                    $first?->accountStatus?->value ?? 'n/a',
                    $first?->attribution->acquisitionChannel?->value ?? 'n/a',
                    $first === null ? 'n/a' : var_export($first->marketable, true),
                    $first?->lifetime === null ? 'absent' : 'present',
                );
            });
        }

        if ($this->option('live')) {
            $this->step('GET /v2/partners/charity-raffles/live', function () use ($drawhouse): string {
                $draws = $drawhouse->liveDraws(limit: 5);
                $lines = [sprintf('draws=%d', count($draws))];

                foreach ($draws as $draw) {
                    $lines[] = sprintf(
                        '  game=%s round=%s state=%s sold=%s available=%s countdown=%s instantWins=%s liveDraw=%s asOf=%s',
                        $draw->gameId ?? 'null',
                        $draw->roundId ?? 'null',
                        $draw->roundState?->value ?? 'null',
                        $draw->tickets->sold ?? 'null',
                        $draw->tickets->available ?? 'null',
                        $draw->countdownTarget()?->toIso8601ZuluString() ?? 'null',
                        $draw->instantWins === null ? 'absent' : 'present',
                        $draw->liveDraw === null ? 'null' : 'present',
                        $draw->asOf?->toIso8601ZuluString() ?? 'null',
                    );
                }

                return implode(PHP_EOL, $lines);
            });
        }

        if ($this->option('tickets')) {
            $this->step('GET /v2/games/tickets (first live draw)', function () use ($drawhouse): string {
                $draw = $drawhouse->liveDraws(limit: 1)[0] ?? null;

                if ($draw?->gameId === null) {
                    return 'no live draw to fetch tickets for';
                }

                $tickets = $drawhouse->tickets($draw->gameId);
                $first = $tickets[0] ?? null;

                return sprintf(
                    'game=%d tickets=%d firstId=%s price=%s paymentStatus=%s hasUtm=%s',
                    $draw->gameId,
                    count($tickets),
                    $first?->id ?? 'n/a',
                    $first?->price ?? 'n/a',
                    $first?->paymentStatus ?? 'n/a',
                    $first === null ? 'n/a' : var_export($first->hasUtm(), true),
                );
            });
        }

        if ($this->option('reports')) {
            $this->step('GET /v2/affiliates/reports (yesterday)', function () use ($drawhouse): string {
                $yesterday = now('UTC')->subDay()->startOfDay();
                $reports = $drawhouse->dailyReports($yesterday, $yesterday);

                // The API has been seen ignoring start/end; pick yesterday's row explicitly.
                $r = null;
                foreach ($reports as $candidate) {
                    if ($candidate->startAt?->isSameDay($yesterday)) {
                        $r = $candidate;
                    }
                }

                return $r === null ? sprintf('%d snapshot(s) returned, none for %s', count($reports), $yesterday->toDateString()) : sprintf(
                    'rows=%d id=%s day=%s registrations=%s played=%s active=%s internalMarketable=%s externalMarketable=%s',
                    count($reports),
                    $r->id ?? 'null',
                    $r->startAt?->toDateString(),
                    $r->registrations ?? 'null',
                    $r->played ?? 'null',
                    $r->active ?? 'null',
                    $r->internalMarketable ?? 'null',
                    $r->externalMarketable ?? 'null',
                );
            });
        }

        return self::SUCCESS;
    }

    /**
     * @param  callable(): string  $run
     */
    private function step(string $label, callable $run): bool
    {
        try {
            $this->info("[ok] {$label}: ".$run());

            return true;
        } catch (PartnerNotLiveException $e) {
            $this->warn("[403 partner_not_live] {$label}: partner status is ".($e->partnerStatus ?? 'unknown'));
        } catch (NotFoundException $e) {
            $this->warn("[404] {$label}: ".$e->getMessage());
        } catch (DrawHouseException $e) {
            $this->error(sprintf('[HTTP %d / errorCode %s] %s: %s', $e->status, $e->errorCode ?? 'null', $label, $e->getMessage()));
        } catch (Throwable $e) {
            $this->error("[error] {$label}: ".$e::class.' '.$e->getMessage());
        }

        return false;
    }
}
