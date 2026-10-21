<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Console;

use Digitonic\DrawHouse\Auth\TokenStore;
use Digitonic\DrawHouse\DrawHouse;
use Digitonic\DrawHouse\Http\Contracts\RequiresAccessToken;
use Digitonic\DrawHouse\Http\DrawHouseConnector;
use Digitonic\DrawHouse\Http\Requests\GetAffiliateReportsRequest;
use Digitonic\DrawHouse\Http\Requests\GetBalancesRequest;
use Digitonic\DrawHouse\Http\Requests\GetDailyMetricsRequest;
use Digitonic\DrawHouse\Http\Requests\GetGamesRequest;
use Digitonic\DrawHouse\Http\Requests\GetLiveDrawRequest;
use Digitonic\DrawHouse\Http\Requests\GetLiveDrawsRequest;
use Digitonic\DrawHouse\Http\Requests\GetMeRequest;
use Digitonic\DrawHouse\Http\Requests\GetPartnerTicketsRequest;
use Digitonic\DrawHouse\Http\Requests\GetPlayerRequest;
use Digitonic\DrawHouse\Http\Requests\GetPlayersRequest;
use Digitonic\DrawHouse\Http\Requests\GetReportUtmsRequest;
use Digitonic\DrawHouse\Http\Requests\GetTicketsRequest;
use Digitonic\DrawHouse\Http\Requests\GetTransactionsRequest;
use Digitonic\DrawHouse\Support\PlayersQuery;
use Digitonic\DrawHouse\Support\ResponseShape;
use Digitonic\DrawHouse\Support\TicketsQuery;
use Digitonic\DrawHouse\Support\TransactionsQuery;
use Illuminate\Console\Command;
use Saloon\Http\Auth\HeaderAuthenticator;
use Saloon\Http\Request;
use Throwable;

/**
 * OPT-IN. Records REDACTED responses from the real DrawHouse API into
 * `tests/Fixtures/live/*.json` (`--record`), or re-fetches them and reports
 * where the live response SHAPE now differs from what was recorded
 * (`--check`). The point is to notice when DrawHouse change something
 * before a DTO quietly starts parsing it to null.
 *
 * Never run by the test suite or a scheduler. Reads credentials from config
 * (env). Values under PII keys (email, phone, names, DOB), commercial terms,
 * account identifiers (affiliateId, playerId, companyName) and any token are
 * replaced before anything is written; see ResponseShape::REDACT_KEYS. Game,
 * prize and image values are NOT redacted: review a recording before
 * committing it.
 *
 * Each fixture file is `{ endpoint, request, recordedAt, status, body }`.
 * Error responses (e.g. 403 partner_not_live) are recorded too: they are
 * part of the contract.
 */
final class FixturesCommand extends Command
{
    protected $signature = 'drawhouse:fixtures
        {--record : Fetch every endpoint and write redacted fixtures}
        {--check : Fetch every endpoint and diff the response shape against the recorded fixtures}
        {--path= : Fixture directory (default: the SDK\'s tests/Fixtures/live)}';

    protected $description = 'Opt-in: record redacted live DrawHouse responses as test fixtures, or check the live response shapes against them';

    public function handle(DrawHouse $drawhouse, DrawHouseConnector $connector, TokenStore $tokens): int
    {
        if (! $this->option('record') && ! $this->option('check')) {
            $this->error('Pass --record or --check.');

            return self::INVALID;
        }

        $dir = rtrim((string) ($this->option('path') ?: dirname(__DIR__, 2).'/tests/Fixtures/live'), '/');

        if ($this->option('record') && ! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error("Cannot create {$dir}");

            return self::FAILURE;
        }

        // One login; the token goes on each authenticated request below.
        $drawhouse->login();
        $token = $tokens->accessToken() ?? '';
        $me = $drawhouse->me();
        $affiliateId = $me->affiliateId;

        $this->line(sprintf('Partner status=%s affiliateId=%s; fixtures in %s', $me->status ?? 'null', $affiliateId ?? 'null', $dir));

        $drift = 0;

        foreach ($this->plan($drawhouse, $affiliateId) as $name => [$request, $description]) {
            if ($request === null) {
                $this->warn("[skip] {$name}: {$description}");

                continue;
            }

            try {
                if ($this->needsToken($request)) {
                    $request->authenticate(new HeaderAuthenticator($token, DrawHouse::ACCESS_TOKEN_HEADER));
                }

                $response = $connector->send($request);
                $body = $response->json();
            } catch (Throwable $e) {
                $this->error("[error] {$name}: ".$e->getMessage());

                continue;
            }

            if (! is_array($body)) {
                $this->error("[error] {$name}: HTTP {$response->status()} with a non-JSON body");

                continue;
            }

            $redacted = ResponseShape::redact($body);
            $file = "{$dir}/{$name}.json";

            if ($this->option('record')) {
                file_put_contents($file, json_encode([
                    'endpoint' => $description,
                    'request' => $request->getMethod()->value.' '.$request->resolveEndpoint(),
                    'recordedAt' => now('UTC')->toIso8601ZuluString(),
                    'status' => $response->status(),
                    'body' => $redacted,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);

                $this->info(sprintf('[recorded] %s: HTTP %d → %s', $name, $response->status(), basename($file)));

                continue;
            }

            if (! is_file($file)) {
                $this->warn("[missing] {$name}: no recorded fixture at ".basename($file).' (run --record)');

                continue;
            }

            /** @var array{status: int, body: array<string, mixed>} $recorded */
            $recorded = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

            $diff = ResponseShape::diff(ResponseShape::describe($recorded['body']), ResponseShape::describe($redacted));

            if ($recorded['status'] !== $response->status()) {
                $diff[] = sprintf('~ HTTP status changed from %d to %d', $recorded['status'], $response->status());
            }

            if ($diff === []) {
                $this->info("[same] {$name}");

                continue;
            }

            $drift++;
            $this->warn("[drift] {$name}:");

            foreach ($diff as $line) {
                $this->line('    '.$line);
            }
        }

        if ($this->option('check') && $drift > 0) {
            $this->error("{$drift} endpoint(s) have drifted from the recorded fixtures. Review, then re-run with --record if the SDK has been updated.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Which requests to record, in order. Later entries reuse ids discovered
     * by earlier ones (first live draw → single live draw, tickets; first
     * daily report → UTMs; first player → single player).
     *
     * @return iterable<string, array{Request|null, string}>
     */
    private function plan(DrawHouse $drawhouse, ?int $affiliateId): iterable
    {
        yield 'me' => [new GetMeRequest, 'GET /v2/partners/me'];

        yield 'balances' => [new GetBalancesRequest, 'GET /v2/players/balances'];

        $yesterday = now('UTC')->subDay()->toDateString();
        yield 'daily-reports' => [new GetAffiliateReportsRequest($yesterday, $yesterday), "GET /v2/affiliates/reports?interval=days&start={$yesterday}&end={$yesterday}"];

        $reportId = $this->quietly(fn () => $drawhouse->dailyReports($yesterday, $yesterday)[0]->id ?? null);
        yield 'report-utms' => $reportId === null
            ? [null, 'no daily report for yesterday']
            : [new GetReportUtmsRequest($reportId), "GET /v2/affiliates/reports/{$reportId}/utms"];

        yield 'players-page' => [new GetPlayersRequest(PlayersQuery::make()->limit(2)->includeLifetime()), 'GET /v2/partners/players?limit=2&include=lifetime'];

        $playerId = $this->quietly(fn () => $drawhouse->playersPage(PlayersQuery::make()->limit(1))->items[0]->id ?? null);
        yield 'player' => $playerId === null
            ? [null, 'players feed unavailable (partner not LIVE?) or empty']
            : [new GetPlayerRequest($playerId), "GET /v2/partners/players/{$playerId}"];

        yield 'partner-tickets' => [new GetPartnerTicketsRequest(TicketsQuery::make()->limit(2)), 'GET /v2/partners/tickets?limit=2'];
        yield 'transactions' => [new GetTransactionsRequest(TransactionsQuery::make()->limit(2)), 'GET /v2/partners/transactions?limit=2'];
        yield 'daily-metrics' => [new GetDailyMetricsRequest($yesterday, $yesterday), "GET /v2/partners/metrics/daily?from={$yesterday}&to={$yesterday}"];

        if ($affiliateId === null) {
            yield 'games' => [null, 'no affiliateId on the partner profile'];
            yield 'live-draws' => [null, 'no affiliateId on the partner profile'];
            yield 'live-draw' => [null, 'no affiliateId on the partner profile'];
            yield 'tickets' => [null, 'no affiliateId on the partner profile'];

            return;
        }

        yield 'games' => [new GetGamesRequest($affiliateId, limit: 2), 'GET /v2/partners/charity-raffles/games?affiliateId={affiliateId}&limit=2'];
        yield 'live-draws' => [new GetLiveDrawsRequest($affiliateId, limit: 2), 'GET /v2/partners/charity-raffles/live?affiliateId={affiliateId}&limit=2'];

        $gameId = $this->quietly(fn () => $drawhouse->liveDraws(limit: 1, affiliateId: $affiliateId)[0]->gameId ?? null);

        yield 'live-draw' => $gameId === null
            ? [null, 'no live draw to fetch']
            : [new GetLiveDrawRequest($gameId, $affiliateId), "GET /v2/partners/charity-raffles/live/{$gameId}?affiliateId={affiliateId}"];

        yield 'tickets' => $gameId === null
            ? [null, 'no live draw whose tickets could be fetched']
            : [new GetTicketsRequest($gameId, limit: 2), "GET /v2/games/tickets?gameId={$gameId}&limit=2&offset=0"];
    }

    private function needsToken(Request $request): bool
    {
        return $request instanceof RequiresAccessToken;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T|null
     */
    private function quietly(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (Throwable) {
            return null;
        }
    }
}
