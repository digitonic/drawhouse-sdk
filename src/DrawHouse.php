<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Digitonic\DrawHouse\Auth\TokenStore;
use Digitonic\DrawHouse\DTO\Balances;
use Digitonic\DrawHouse\DTO\DailyReport;
use Digitonic\DrawHouse\DTO\DailyReportDelta;
use Digitonic\DrawHouse\DTO\Game;
use Digitonic\DrawHouse\DTO\LiveDraw;
use Digitonic\DrawHouse\DTO\Metrics\DailyMetrics;
use Digitonic\DrawHouse\DTO\Money\MoneyEvent;
use Digitonic\DrawHouse\DTO\Money\MoneyEventPage;
use Digitonic\DrawHouse\DTO\Partner;
use Digitonic\DrawHouse\DTO\Player;
use Digitonic\DrawHouse\DTO\PlayerDetail;
use Digitonic\DrawHouse\DTO\PlayerPage;
use Digitonic\DrawHouse\DTO\Ticket;
use Digitonic\DrawHouse\DTO\Tickets\GameTicketsPage;
use Digitonic\DrawHouse\DTO\Tickets\PartnerTicket;
use Digitonic\DrawHouse\DTO\Tickets\PartnerTicketPage;
use Digitonic\DrawHouse\DTO\TokenPair;
use Digitonic\DrawHouse\DTO\UtmBreakdown;
use Digitonic\DrawHouse\DTO\Webhooks\WebhookDeliveriesPage;
use Digitonic\DrawHouse\DTO\Webhooks\WebhookDelivery;
use Digitonic\DrawHouse\DTO\Webhooks\WebhookEndpoint;
use Digitonic\DrawHouse\DTO\Webhooks\WebhookEndpointWithSecret;
use Digitonic\DrawHouse\DTO\Webhooks\WebhookEvent;
use Digitonic\DrawHouse\Enums\GameState;
use Digitonic\DrawHouse\Enums\MetricsGroupBy;
use Digitonic\DrawHouse\Enums\MetricsTimezone;
use Digitonic\DrawHouse\Enums\Participation;
use Digitonic\DrawHouse\Enums\ReportInterval;
use Digitonic\DrawHouse\Enums\RoundState;
use Digitonic\DrawHouse\Enums\WebhookDeliveryStatus;
use Digitonic\DrawHouse\Enums\WebhookEventType;
use Digitonic\DrawHouse\Exceptions\AuthenticationException;
use Digitonic\DrawHouse\Exceptions\DrawHouseException;
use Digitonic\DrawHouse\Exceptions\InvalidRequestException;
use Digitonic\DrawHouse\Exceptions\NotFoundException;
use Digitonic\DrawHouse\Exceptions\PartnerNotLiveException;
use Digitonic\DrawHouse\Exceptions\ServerException;
use Digitonic\DrawHouse\Http\Contracts\RequiresAccessToken;
use Digitonic\DrawHouse\Http\DrawHouseConnector;
use Digitonic\DrawHouse\Http\Requests\CreateWebhookRequest;
use Digitonic\DrawHouse\Http\Requests\DeleteWebhookRequest;
use Digitonic\DrawHouse\Http\Requests\GetAffiliateReportsRequest;
use Digitonic\DrawHouse\Http\Requests\GetBalancesRequest;
use Digitonic\DrawHouse\Http\Requests\GetDailyMetricsRequest;
use Digitonic\DrawHouse\Http\Requests\GetGamesRequest;
use Digitonic\DrawHouse\Http\Requests\GetLiveDrawRequest;
use Digitonic\DrawHouse\Http\Requests\GetLiveDrawsRequest;
use Digitonic\DrawHouse\Http\Requests\GetMeRequest;
use Digitonic\DrawHouse\Http\Requests\GetPartnerTicketsRequest;
use Digitonic\DrawHouse\Http\Requests\GetPlayerRequest;
use Digitonic\DrawHouse\Http\Requests\GetPlayersReportRequest;
use Digitonic\DrawHouse\Http\Requests\GetPlayersRequest;
use Digitonic\DrawHouse\Http\Requests\GetReportUtmsRequest;
use Digitonic\DrawHouse\Http\Requests\GetTicketsRequest;
use Digitonic\DrawHouse\Http\Requests\GetTransactionsRequest;
use Digitonic\DrawHouse\Http\Requests\GetWebhookDeliveriesRequest;
use Digitonic\DrawHouse\Http\Requests\GetWebhookRequest;
use Digitonic\DrawHouse\Http\Requests\ListWebhooksRequest;
use Digitonic\DrawHouse\Http\Requests\LoginRequest;
use Digitonic\DrawHouse\Http\Requests\RefreshAccessTokenRequest;
use Digitonic\DrawHouse\Http\Requests\RotateWebhookSecretRequest;
use Digitonic\DrawHouse\Http\Requests\TestWebhookRequest;
use Digitonic\DrawHouse\Http\Requests\UpdateWebhookRequest;
use Digitonic\DrawHouse\Support\Parse;
use Digitonic\DrawHouse\Support\PlayersQuery;
use Digitonic\DrawHouse\Support\TicketsQuery;
use Digitonic\DrawHouse\Support\TransactionsQuery;
use Generator;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use JsonException;
use Saloon\Http\Auth\HeaderAuthenticator;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * Thin client for the DrawHouse Partner API. One typed method per endpoint.
 *
 * Auth is transparent: the access token is attached from the cache, a 401
 * triggers /refresh-access-token and one retry, and a failed refresh falls
 * back to /login. Rate guidance is documented per method and NOT enforced;
 * throttling is the caller's responsibility.
 */
final class DrawHouse
{
    public const ACCESS_TOKEN_HEADER = 'access-token';

    /**
     * /refresh-access-token answers `errorCode: 200` on success; 0 is
     * accepted too.
     */
    private const REFRESH_SUCCESS_CODES = [0, 200];

    /**
     * The round states /v2/partners/charity-raffles/live accepts; anything
     * else is a 400.
     */
    private const LIVE_DRAW_STATES = [RoundState::OPEN, RoundState::LIVE_DRAW, RoundState::RESULTED, RoundState::COMPLETED];

    public function __construct(
        private readonly DrawHouseConnector $connector,
        private readonly TokenStore $tokens,
        #[\SensitiveParameter] private readonly string $email,
        #[\SensitiveParameter] private readonly string $password,
        private readonly ?int $affiliateId = null,
    ) {}

    // -------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------

    /**
     * POST /login — exchanges the configured credentials for a token pair and
     * caches it. Called for you when no usable token exists.
     *
     * @throws AuthenticationException
     */
    public function login(): TokenPair
    {
        if ($this->email === '' || $this->password === '') {
            throw AuthenticationException::missingCredentials();
        }

        try {
            // Since Partner API v1.4 /login uses the envelope like every
            // other route; earlier versions put the tokens at the top level
            // of the body. Both are accepted.
            $data = $this->unwrap($this->connector->send(new LoginRequest($this->email, $this->password)), bare: true);
        } catch (DrawHouseException $e) {
            throw AuthenticationException::fromDrawHouse($e, 'login');
        }

        $pair = TokenPair::fromLogin(is_array($data) ? $data : []);

        if (! $pair->isComplete()) {
            throw new AuthenticationException('DrawHouse login response did not include both tokens', null, 200);
        }

        $this->tokens->put($pair);

        return $pair;
    }

    /**
     * POST /refresh-access-token — issues a new access token without
     * re-submitting credentials. The refresh token itself is unchanged.
     *
     * This route is the one exception to the envelope rule: it returns
     * `errorCode: 200` on success, not 0. A 422 means the refresh token has
     * expired or does not match (reauthenticate() then logs in afresh); a
     * 400 means a token was missing from the body.
     *
     * @throws AuthenticationException when there is no cached pair or DrawHouse rejects it
     */
    public function refreshAccessToken(): TokenPair
    {
        $refresh = $this->tokens->refreshToken();
        $access = $this->tokens->accessTokenForRefresh();

        if ($refresh === null || $access === null) {
            throw new AuthenticationException('No DrawHouse token pair is cached to refresh', null, 0);
        }

        try {
            $data = $this->unwrap(
                $this->connector->send(new RefreshAccessTokenRequest($access, $refresh)),
                successCodes: self::REFRESH_SUCCESS_CODES,
            );
        } catch (DrawHouseException $e) {
            throw AuthenticationException::fromDrawHouse($e, 'token refresh');
        }

        $pair = (new TokenPair($access, $refresh, null, $this->tokens->refreshTokenExpiry()))
            ->withRefreshedAccessToken(is_array($data) ? $data : []);

        if ($pair->accessToken === '') {
            throw new AuthenticationException('DrawHouse refresh response did not include newAccessToken', null, 200);
        }

        $this->tokens->putAccessToken($pair->accessToken, $pair->accessTokenExpiry);

        return $pair;
    }

    /**
     * Drop cached tokens so the next call logs in afresh.
     */
    public function forgetTokens(): void
    {
        $this->tokens->forgetTokens();
    }

    // -------------------------------------------------------------------
    // Partner
    // -------------------------------------------------------------------

    /**
     * GET /v2/partners/me — the partner profile.
     *
     * Rate guidance: once; cache `affiliateId` (see affiliateId()).
     * `affiliateId` is nullable; check `status` / isLive() to tell whether
     * the partner is live rather than its presence. Answers 404
     * (NotFoundException) when no partner profile exists for the user.
     */
    public function me(): Partner
    {
        $data = $this->send(new GetMeRequest);

        return Partner::fromArray(is_array($data) ? $data : []);
    }

    /**
     * The partner's affiliate id: from config if set, otherwise fetched once
     * via /v2/partners/me and cached forever.
     *
     * @throws PartnerNotLiveException when /v2/partners/me has no affiliateId yet (partner not LIVE)
     */
    public function affiliateId(): int
    {
        if ($this->affiliateId !== null) {
            return $this->affiliateId;
        }

        if (($cached = $this->tokens->affiliateId()) !== null) {
            return $cached;
        }

        $me = $this->me();
        $id = $me->affiliateId;

        if ($id === null) {
            throw PartnerNotLiveException::affiliateIdPending($me->status);
        }

        $this->tokens->putAffiliateId($id);

        return $id;
    }

    public function forgetAffiliateId(): void
    {
        $this->tokens->forgetAffiliateId();
    }

    // -------------------------------------------------------------------
    // Players feed
    // -------------------------------------------------------------------

    /**
     * GET /v2/partners/players — one cursor page of the players feed:
     * attribution, account status, activity and marketing consent together.
     *
     * The affiliate is taken from the token (no affiliateId parameter); the
     * partner must be LIVE, otherwise PartnerNotLiveException. Returns full
     * PII; the consuming application is responsible for handling it.
     *
     * Rate guidance: daily, with `updatedAfter`. Pass the previous page's
     * `nextCursor` as `$after`; null means the end. Use allPlayers() to walk
     * every page.
     *
     * @throws PartnerNotLiveException
     * @throws InvalidRequestException for a bad parameter the client did not catch
     */
    public function playersPage(?PlayersQuery $query = null, ?string $after = null): PlayerPage
    {
        $data = $this->send(new GetPlayersRequest($query ?? PlayersQuery::make(), $after));

        return PlayerPage::fromArray(is_array($data) ? $data : []);
    }

    /**
     * Lazily walk every page of the players feed, following `nextCursor`
     * until it is null. Nothing is fetched until the collection is iterated,
     * and each page is fetched only when the previous one is exhausted.
     *
     *   DrawHouse::allPlayers(PlayersQuery::make()->updatedAfter($since)->limit(500))
     *       ->each(fn (PlayerDetail $p) => ...);
     *
     * Filtered pages are filled server-side, so a short page is the end of
     * the data (unlike allGames()).
     *
     * @return LazyCollection<int, PlayerDetail>
     */
    public function allPlayers(?PlayersQuery $query = null): LazyCollection
    {
        $query ??= PlayersQuery::make();

        return LazyCollection::make(function () use ($query): Generator {
            $after = null;

            do {
                $page = $this->playersPage($query, $after);

                foreach ($page->items as $player) {
                    yield $player;
                }

                $after = $page->nextCursor;
            } while ($after !== null);
        });
    }

    /**
     * GET /v2/partners/players/{id} — one player, `lifetime` always included.
     *
     * @throws NotFoundException when the player is not yours
     */
    public function player(PlayerDetail|Player|int $player): PlayerDetail
    {
        $id = is_int($player) ? $player : (int) $player->id;

        $data = $this->send(new GetPlayerRequest($id));

        return PlayerDetail::fromArray(is_array($data) ? $data : []);
    }

    // -------------------------------------------------------------------
    // Reports
    // -------------------------------------------------------------------

    /**
     * GET /v2/partners/reports/players — every player attributed to the partner.
     *
     * Rate guidance: daily. Returns full PII; the consuming application is
     * responsible for handling it.
     *
     * @deprecated Still available but has no paging, status,
     *             attribution or consent data. Use the players feed instead:
     *             allPlayers() / playersPage() / player().
     *
     * @return list<Player>
     */
    public function players(): array
    {
        return array_map(Player::fromArray(...), Parse::items($this->send(new GetPlayersReportRequest)));
    }

    /**
     * GET /v2/affiliates/reports?interval=days|weeks&start=&end=
     *
     * Rate guidance: daily, for the previous day.
     *
     * Every count is a CUMULATIVE snapshot as at the end of the report day,
     * not that day's activity, so summing `registrations` across days
     * over-counts. Use dailyReportDeltas() or DailyReport::deltaFrom() for
     * per-day figures.
     *
     * Returns exactly what the API sends, in the API's order. To be sure of
     * the window, filter on `startAt`, or use dailyReportDeltas(), which
     * does.
     *
     * @return list<DailyReport>
     */
    public function dailyReports(
        CarbonInterface|string $start,
        CarbonInterface|string $end,
        ReportInterval $interval = ReportInterval::DAYS,
    ): array {
        $request = new GetAffiliateReportsRequest($this->dateParam($start), $this->dateParam($end), $interval);

        return array_map(DailyReport::fromArray(...), Parse::items($this->send($request)));
    }

    /**
     * Per-day activity derived from consecutive cumulative snapshots: one
     * DailyReportDelta for every day from `$start` to `$end` inclusive.
     *
     * Fetches one extra day before `$start` so the first day has a snapshot
     * to subtract from. If DrawHouse has no snapshot for that day the first
     * delta will be missing (N snapshots give N-1 deltas); check
     * DailyReportDelta::days() when gaps matter.
     *
     * @return list<DailyReportDelta>
     */
    public function dailyReportDeltas(CarbonInterface|string $start, CarbonInterface|string $end): array
    {
        $from = ($start instanceof CarbonInterface ? CarbonImmutable::instance($start) : CarbonImmutable::parse($start))
            ->utc()->startOfDay()->subDay();
        $to = ($end instanceof CarbonInterface ? CarbonImmutable::instance($end) : CarbonImmutable::parse($end))
            ->utc()->endOfDay();

        // Enforce the window client-side as well, so the deltas only ever
        // cover the requested days.
        $snapshots = array_values(array_filter(
            $this->dailyReports($from, $to),
            static fn (DailyReport $r): bool => $r->startAt !== null && $r->startAt->between($from, $to),
        ));

        return DailyReportDelta::fromSnapshots($snapshots);
    }

    /**
     * GET /v2/affiliates/reports/{dailyReportId}/utms
     *
     * Rate guidance: daily, for the previous day.
     *
     * `count` and `registered` are returned exactly as the API sends them;
     * the SDK derives no deltas from the UTM breakdown.
     *
     * @return list<UtmBreakdown>
     */
    public function utms(DailyReport|int $dailyReport): array
    {
        $id = $dailyReport instanceof DailyReport ? (int) $dailyReport->id : $dailyReport;

        return array_map(UtmBreakdown::fromArray(...), Parse::items($this->send(new GetReportUtmsRequest($id))));
    }

    // -------------------------------------------------------------------
    // Games
    // -------------------------------------------------------------------

    /**
     * GET /v2/partners/charity-raffles/games — a single page.
     *
     * Unauthenticated; keyed on `affiliateId` only. HTTP 500 / errorCode 3 is
     * documented as transient and is retried once here.
     *
     * Paging happens BEFORE the participation filter, so a page shorter than
     * `$limit` is not the last page. Use allGames() to iterate safely.
     *
     * @param  list<GameState>|null  $states  null = API defaults (READY, INPROGRESS, REPORTED, RESULTED, COMPLETED).
     *                                        SETUP, ARCHIVED, DELETED, CANCELLED only when passed explicitly.
     * @return list<Game>
     */
    public function games(
        ?Participation $participation = null,
        ?array $states = null,
        int $limit = 50,
        int $offset = 0,
        ?int $affiliateId = null,
    ): array {
        $request = new GetGamesRequest(
            affiliateId: $affiliateId ?? $this->affiliateId(),
            participation: $participation,
            states: $states,
            limit: $limit,
            offset: $offset,
        );

        return array_map(Game::fromArray(...), Parse::items($this->sendWithRetry($request), 'games'));
    }

    /**
     * Lazily walk every page of games, stopping only when a page comes back
     * empty (a short page is NOT the end because filtering follows paging).
     *
     * @param  list<GameState>|null  $states
     * @return Generator<int, Game>
     */
    public function allGames(
        ?Participation $participation = null,
        ?array $states = null,
        int $pageSize = 100,
        ?int $affiliateId = null,
    ): Generator {
        $pageSize = max(1, $pageSize);
        $offset = 0;
        $affiliateId ??= $this->affiliateId();

        do {
            $page = $this->games($participation, $states, $pageSize, $offset, $affiliateId);

            foreach ($page as $game) {
                yield $game;
            }

            $offset += $pageSize;
        } while ($page !== []);
    }

    // -------------------------------------------------------------------
    // Live draws
    // -------------------------------------------------------------------

    /**
     * GET /v2/partners/charity-raffles/live — every game you have an ACTIVE
     * edition on, with ticket counts, countdown timestamps, prizes,
     * instant-win availability and live-draw status. Built for a server of
     * yours that polls it and renders widgets, not for browsers directly.
     *
     * Unauthenticated; keyed on `affiliateId`, which picks the edition that
     * comes back (branding, prize names, images, URL).
     *
     * Rate guidance: responses are CACHED SERVER-SIDE FOR 15 SECONDS, so
     * poll every 15–30 seconds; polling faster returns nothing newer. Check
     * LiveDraw::$asOf for the snapshot time.
     *
     * HTTP 500 / errorCode 3 is retried once; back off further yourself.
     *
     * @param  list<RoundState>|null  $states  OPEN, LIVE_DRAW, RESULTED and/or COMPLETED. null = all four,
     *                                         so one call can feed both an "enter now" and a "drawn" widget.
     * @param  int|null  $editionId  A specific edition of yours when you run more than one on a game.
     * @param  int  $limit  Default 20, clamped to the maximum of 100.
     * @return list<LiveDraw>
     */
    public function liveDraws(
        ?array $states = null,
        ?int $editionId = null,
        int $limit = GetLiveDrawsRequest::DEFAULT_LIMIT,
        ?int $affiliateId = null,
    ): array {
        if ($states !== null) {
            foreach ($states as $state) {
                if (! in_array($state, self::LIVE_DRAW_STATES, true)) {
                    throw new InvalidArgumentException(sprintf(
                        'DrawHouse live draws: state %s is not accepted; use OPEN, LIVE_DRAW, RESULTED or COMPLETED',
                        $state instanceof RoundState ? $state->value : get_debug_type($state),
                    ));
                }
            }
        }

        $request = new GetLiveDrawsRequest(
            affiliateId: $affiliateId ?? $this->affiliateId(),
            editionId: $editionId,
            states: $states,
            limit: max(1, min($limit, GetLiveDrawsRequest::MAX_LIMIT)),
        );

        return array_map(LiveDraw::fromArray(...), Parse::items($this->sendWithRetry($request), 'draws'));
    }

    /**
     * GET /v2/partners/charity-raffles/live/{gameId} — one live draw.
     *
     * Same caching and polling guidance as liveDraws().
     *
     * @throws NotFoundException when that game has no live draw for your affiliate
     */
    public function liveDraw(Game|int $game, ?int $editionId = null, ?int $affiliateId = null): LiveDraw
    {
        $id = $game instanceof Game ? (int) $game->id : $game;

        $request = new GetLiveDrawRequest($id, $affiliateId ?? $this->affiliateId(), $editionId);

        $data = $this->sendWithRetry($request);

        return LiveDraw::fromArray(is_array($data) ? $data : []);
    }

    // -------------------------------------------------------------------
    // Tickets & balances
    // -------------------------------------------------------------------

    /**
     * GET /v2/games/tickets?gameId= — every ticket your players bought in one
     * draw, every page.
     *
     * The route is offset-paged since Partner API v1.4 (5000 a page at
     * most); this follows `total` until it has them all. Before v1.4 it
     * returned only the first 1000.
     *
     * Rate guidance: no more than once every 5 minutes per game.
     *
     * @deprecated DrawHouse recommend the tickets feed for new integrations:
     *             allTickets(TicketsQuery::make()->game($id)), which is
     *             cursor-paged, filterable and carries attribution and outcome.
     *
     * @return list<Ticket>
     *
     * @throws NotFoundException when the game does not exist or is not yours
     */
    public function tickets(Game|int $game): array
    {
        $tickets = [];
        $offset = 0;

        do {
            $page = $this->gameTicketsPage($game, GetTicketsRequest::MAX_LIMIT, $offset);
            $tickets = [...$tickets, ...$page->tickets];
            $offset = $page->nextOffset();
        } while ($page->hasMore());

        return $tickets;
    }

    /**
     * GET /v2/games/tickets?gameId=&limit=&offset= — one offset page of a
     * draw's tickets. `total` counts every matching ticket; keep paging while
     * GameTicketsPage::hasMore().
     *
     * @param  int  $limit  Default 1000, clamped to 5000.
     *
     * @throws NotFoundException when the game does not exist or is not yours
     */
    public function gameTicketsPage(Game|int $game, int $limit = GetTicketsRequest::DEFAULT_LIMIT, int $offset = 0): GameTicketsPage
    {
        $id = $game instanceof Game ? (int) $game->id : $game;
        $request = new GetTicketsRequest($id, max(1, min($limit, GetTicketsRequest::MAX_LIMIT)), max(0, $offset));

        $data = $this->send($request);

        return GameTicketsPage::fromArray(is_array($data) ? $data : []);
    }

    /**
     * GET /v2/partners/tickets — one cursor page of the tickets feed (Partner
     * API v1.4): every ticket your players bought, across every draw, with
     * attribution and raffle outcome.
     *
     * The affiliate is taken from the token; the partner must be LIVE,
     * otherwise PartnerNotLiveException. Every filter runs in the database,
     * so a page is `limit` rows unless the tickets ran out.
     *
     * Rate guidance: daily with `updatedAfter` (payment outcome, refunds and
     * results all move it); no more than every 5 minutes with `roundId` when
     * monitoring a live draw.
     *
     * @throws PartnerNotLiveException
     * @throws InvalidRequestException for a bad parameter the client did not catch
     */
    public function ticketsPage(?TicketsQuery $query = null, ?string $after = null): PartnerTicketPage
    {
        $data = $this->send(new GetPartnerTicketsRequest($query ?? TicketsQuery::make(), $after));

        return PartnerTicketPage::fromArray(is_array($data) ? $data : []);
    }

    /**
     * Lazily walk every page of the tickets feed, following `nextCursor`
     * until it is null.
     *
     *   DrawHouse::allTickets(TicketsQuery::make()->updatedAfter($since)->paymentStatus(TicketPaymentStatus::SUCCEEDED)->limit(500))
     *       ->each(fn (PartnerTicket $t) => ...);
     *
     * @return LazyCollection<int, PartnerTicket>
     */
    public function allTickets(?TicketsQuery $query = null): LazyCollection
    {
        $query ??= TicketsQuery::make();

        return LazyCollection::make(function () use ($query): Generator {
            $after = null;

            do {
                $page = $this->ticketsPage($query, $after);

                foreach ($page->items as $ticket) {
                    yield $ticket;
                }

                $after = $page->items === [] ? null : $page->nextCursor;
            } while ($after !== null);
        });
    }

    /**
     * GET /v2/players/balances — aggregate wallet balances across your players.
     *
     * Rate guidance: no more than once every 15 minutes.
     */
    public function balances(): Balances
    {
        $data = $this->send(new GetBalancesRequest);

        return Balances::fromArray(is_array($data) ? $data : []);
    }

    // -------------------------------------------------------------------
    // Money and metrics
    // -------------------------------------------------------------------

    /**
     * GET /v2/partners/transactions — one page of the money feed (Partner API
     * v1.4): every deposit, purchase, refund, prize payout, withdrawal and
     * site-credit movement by your players, one row each. Nothing is
     * cumulative.
     *
     * The affiliate is taken from the token; the partner must be LIVE. The
     * cursor is opaque: pass `nextCursor` back as `$after` verbatim. Count
     * money only on settled rows (MoneyEvent::isSettled()).
     *
     * Rate guidance: daily, with `updatedAfter`.
     *
     * @throws PartnerNotLiveException
     * @throws InvalidRequestException for a bad parameter the client did not catch
     */
    public function transactionsPage(?TransactionsQuery $query = null, ?string $after = null): MoneyEventPage
    {
        $data = $this->send(new GetTransactionsRequest($query ?? TransactionsQuery::make(), after: $after));

        return MoneyEventPage::fromArray(is_array($data) ? $data : []);
    }

    /**
     * Lazily walk every page of the money feed.
     *
     *   DrawHouse::allTransactions(TransactionsQuery::make()->kind(MoneyKind::DEPOSIT)->status('SUCCESS')->updatedAfter($since))
     *       ->each(fn (MoneyEvent $e) => ...);
     *
     * @return LazyCollection<int, MoneyEvent>
     */
    public function allTransactions(?TransactionsQuery $query = null): LazyCollection
    {
        return $this->walkTransactions($query ?? TransactionsQuery::make(), null);
    }

    /**
     * GET /v2/partners/players/{id}/transactions — one page of the money feed
     * for one player. Takes the same filters except the player. A player
     * outside your affiliate returns an empty page, not a 404.
     */
    public function playerTransactionsPage(PlayerDetail|Player|int $player, ?TransactionsQuery $query = null, ?string $after = null): MoneyEventPage
    {
        $id = is_int($player) ? $player : (int) $player->id;

        $data = $this->send(new GetTransactionsRequest($query ?? TransactionsQuery::make(), $id, $after));

        return MoneyEventPage::fromArray(is_array($data) ? $data : []);
    }

    /**
     * Lazily walk every page of one player's money feed.
     *
     * @return LazyCollection<int, MoneyEvent>
     */
    public function allPlayerTransactions(PlayerDetail|Player|int $player, ?TransactionsQuery $query = null): LazyCollection
    {
        return $this->walkTransactions($query ?? TransactionsQuery::make(), is_int($player) ? $player : (int) $player->id);
    }

    /**
     * GET /v2/partners/metrics/daily — per-day registrations, deposits,
     * spend, prizes and withdrawals for your affiliate (Partner API v1.4).
     *
     * PER-DAY values, not cumulative snapshots like dailyReports(). Days are
     * bucketed in `$timezone` and days with no activity are omitted. `$from`
     * and `$to` are both inclusive and at most 366 days apart. Computed from
     * the same tables as the money feed, so the two agree.
     *
     * Rate guidance: daily, for the previous day.
     *
     * @throws InvalidArgumentException when `$to` is before `$from` or the range is over 366 days
     */
    public function dailyMetrics(
        CarbonInterface|string $from,
        CarbonInterface|string $to,
        MetricsTimezone $timezone = MetricsTimezone::EUROPE_LONDON,
        MetricsGroupBy $groupBy = MetricsGroupBy::NONE,
    ): DailyMetrics {
        $start = CarbonImmutable::parse($this->dateParam($from));
        $end = CarbonImmutable::parse($this->dateParam($to));

        if ($end->lessThan($start)) {
            throw new InvalidArgumentException('DrawHouse daily metrics: `to` must not be before `from`');
        }

        if ($start->diffInDays($end) > GetDailyMetricsRequest::MAX_DAYS) {
            throw new InvalidArgumentException(sprintf('DrawHouse daily metrics: the range is limited to %d days', GetDailyMetricsRequest::MAX_DAYS));
        }

        $data = $this->send(new GetDailyMetricsRequest($start->toDateString(), $end->toDateString(), $timezone, $groupBy));

        return DailyMetrics::fromArray(is_array($data) ? $data : []);
    }

    // -------------------------------------------------------------------
    // Webhooks
    // -------------------------------------------------------------------

    /**
     * GET /v2/partners/webhooks — your endpoints, oldest first. Never
     * includes the secret.
     *
     * The webhook routes take the affiliate from the token and need a LIVE
     * partner, otherwise PartnerNotLiveException.
     *
     * @return list<WebhookEndpoint>
     *
     * @throws PartnerNotLiveException
     */
    public function webhooks(): array
    {
        return array_map(WebhookEndpoint::fromArray(...), Parse::items($this->send(new ListWebhooksRequest), 'items'));
    }

    /**
     * GET /v2/partners/webhooks/{id} — one endpoint, without its secret.
     *
     * @throws NotFoundException when it is not yours or has been deleted
     */
    public function webhook(WebhookEndpoint|int $webhook): WebhookEndpoint
    {
        $data = $this->send(new GetWebhookRequest($this->webhookId($webhook)));

        return WebhookEndpoint::fromArray(is_array($data) ? $data : []);
    }

    /**
     * POST /v2/partners/webhooks — register an endpoint.
     *
     * The response is the ONLY time the signing secret is returned (apart
     * from rotateWebhookSecret()), so store `->secret` server-side straight
     * away. At most 10 endpoints per partner.
     *
     * @param  string  $url  Absolute `https://` URL on a public host name.
     * @param  list<WebhookEventType>  $events  Types to receive; empty = every type. PING cannot be subscribed to.
     * @param  string|null  $description  Up to 255 characters, for your own reference.
     *
     * @throws InvalidArgumentException for a URL, event or description the API would reject
     * @throws InvalidRequestException for anything else the API rejects, including an 11th endpoint
     * @throws ServerException on 503: secret storage was unavailable and nothing was created
     */
    public function createWebhook(string $url, array $events = [], ?string $description = null, bool $active = true): WebhookEndpointWithSecret
    {
        $this->assertWebhookUrl($url);
        $this->assertWebhookEvents($events);

        if ($description !== null) {
            $this->assertWebhookDescription($description);
        }

        $data = $this->send(new CreateWebhookRequest($url, $events, $description, $active));

        return WebhookEndpointWithSecret::fromArray(is_array($data) ? $data : []);
    }

    /**
     * PATCH /v2/partners/webhooks/{id} — change the URL, events, description
     * or active flag. Only the arguments you pass are sent; at least one is
     * required.
     *
     * `events: []` subscribes to every type and `description: ''` clears it.
     * `active: true` re-enables an endpoint (including one DrawHouse switched
     * off after 50 failures) and resets its failure counter. `active: false`
     * stops new deliveries being queued; deliveries already queued go DEAD
     * when due, and re-enabling does not resend them.
     *
     * @param  list<WebhookEventType>|null  $events
     *
     * @throws InvalidArgumentException when nothing is passed, or for a value the API would reject
     * @throws NotFoundException
     */
    public function updateWebhook(
        WebhookEndpoint|int $webhook,
        ?string $url = null,
        ?array $events = null,
        ?string $description = null,
        ?bool $active = null,
    ): WebhookEndpoint {
        $changes = [];

        if ($url !== null) {
            $this->assertWebhookUrl($url);
            $changes['url'] = $url;
        }

        if ($events !== null) {
            $this->assertWebhookEvents($events);
            $changes['events'] = array_values(array_unique(array_map(static fn (WebhookEventType $type): string => $type->value, $events)));
        }

        if ($description !== null) {
            $this->assertWebhookDescription($description);
            $changes['description'] = $description;
        }

        if ($active !== null) {
            $changes['active'] = $active;
        }

        if ($changes === []) {
            throw new InvalidArgumentException('DrawHouse updateWebhook: pass at least one of url, events, description or active');
        }

        $data = $this->send(new UpdateWebhookRequest($this->webhookId($webhook), $changes));

        return WebhookEndpoint::fromArray(is_array($data) ? $data : []);
    }

    /**
     * DELETE /v2/partners/webhooks/{id}. Deliveries still queued to it are
     * marked DEAD; the id is not reused.
     *
     * @throws NotFoundException
     */
    public function deleteWebhook(WebhookEndpoint|int $webhook): void
    {
        $this->send(new DeleteWebhookRequest($this->webhookId($webhook)));
    }

    /**
     * POST /v2/partners/webhooks/{id}/test — queue a `webhook.ping` to this
     * endpoint, signed and delivered like a real event, and return it. The
     * delivery normally arrives within seconds; check webhookDeliveriesPage()
     * for the result. On an inactive endpoint the ping is not sent and its
     * delivery shows DEAD (`endpoint inactive`).
     *
     * @throws NotFoundException
     */
    public function testWebhook(WebhookEndpoint|int $webhook): WebhookEvent
    {
        $data = $this->send(new TestWebhookRequest($this->webhookId($webhook)));

        return WebhookEvent::fromArray(is_array($data) ? $data : []);
    }

    /**
     * POST /v2/partners/webhooks/{id}/rotate-secret — replace the signing
     * secret, returned this once.
     *
     * Deliveries switch to the new secret straight away. Verify with both the
     * old and the new secret (WebhookVerifier takes several) until requests
     * verify with the new one, then drop the old.
     *
     * @throws NotFoundException
     * @throws ServerException on 503: secret storage was unavailable and the old secret is still in force
     */
    public function rotateWebhookSecret(WebhookEndpoint|int $webhook): WebhookEndpointWithSecret
    {
        $data = $this->send(new RotateWebhookSecretRequest($this->webhookId($webhook)));

        return WebhookEndpointWithSecret::fromArray(is_array($data) ? $data : []);
    }

    /**
     * GET /v2/partners/webhooks/{id}/deliveries — one cursor page of the
     * delivery log, oldest first. Pass the previous page's `nextCursor` as
     * `$after`; null means the end. Use allWebhookDeliveries() to walk every
     * page.
     *
     * Use it to debug a failing endpoint: `lastResponseCode` and `lastError`
     * say what your server answered. Rows do not carry the event body.
     *
     * @param  list<WebhookDeliveryStatus>  $statuses  Filter; repeatable.
     * @param  CarbonInterface|string|null  $createdAfter  Only deliveries queued at or after this time.
     * @param  int  $limit  Default 100, clamped to 500.
     *
     * @throws NotFoundException
     */
    public function webhookDeliveriesPage(
        WebhookEndpoint|int $webhook,
        array $statuses = [],
        CarbonInterface|string|null $createdAfter = null,
        int $limit = GetWebhookDeliveriesRequest::DEFAULT_LIMIT,
        ?string $after = null,
    ): WebhookDeliveriesPage {
        $request = new GetWebhookDeliveriesRequest(
            webhookId: $this->webhookId($webhook),
            statuses: $statuses,
            createdAfter: is_string($createdAfter) ? CarbonImmutable::parse($createdAfter) : $createdAfter,
            limit: max(1, min($limit, GetWebhookDeliveriesRequest::MAX_LIMIT)),
            after: $after,
        );

        $data = $this->send($request);

        return WebhookDeliveriesPage::fromArray(is_array($data) ? $data : []);
    }

    /**
     * Lazily walk every page of the delivery log, following `nextCursor`
     * until it is null. Nothing is fetched until the collection is iterated.
     *
     *   DrawHouse::allWebhookDeliveries($id, [WebhookDeliveryStatus::DEAD], now()->subDay())
     *       ->each(fn (WebhookDelivery $d) => ...);
     *
     * @param  list<WebhookDeliveryStatus>  $statuses
     * @return LazyCollection<int, WebhookDelivery>
     */
    public function allWebhookDeliveries(
        WebhookEndpoint|int $webhook,
        array $statuses = [],
        CarbonInterface|string|null $createdAfter = null,
        int $limit = GetWebhookDeliveriesRequest::MAX_LIMIT,
    ): LazyCollection {
        return LazyCollection::make(function () use ($webhook, $statuses, $createdAfter, $limit): Generator {
            $after = null;

            do {
                $page = $this->webhookDeliveriesPage($webhook, $statuses, $createdAfter, $limit, $after);

                foreach ($page->items as $delivery) {
                    yield $delivery;
                }

                // A full page always carries a cursor, so the page after it
                // can be empty; stop on that as well as on a null cursor.
                $after = $page->items === [] ? null : $page->nextCursor;
            } while ($after !== null);
        });
    }

    // -------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------

    /**
     * @return LazyCollection<int, MoneyEvent>
     */
    private function walkTransactions(TransactionsQuery $query, ?int $playerId): LazyCollection
    {
        return LazyCollection::make(function () use ($query, $playerId): Generator {
            $after = null;

            do {
                $page = $playerId === null
                    ? $this->transactionsPage($query, $after)
                    : $this->playerTransactionsPage($playerId, $query, $after);

                foreach ($page->items as $event) {
                    yield $event;
                }

                $after = $page->items === [] ? null : $page->nextCursor;
            } while ($after !== null);
        });
    }

    private function webhookId(WebhookEndpoint|int $webhook): int
    {
        return is_int($webhook) ? $webhook : (int) $webhook->id;
    }

    /**
     * The checks the API would answer 400 for that can be made locally: an
     * absolute https:// URL with a host, no credentials and no fragment.
     * Whether the host is public is left to DrawHouse.
     */
    private function assertWebhookUrl(string $url): void
    {
        $parts = parse_url($url);

        $valid = is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && ($parts['host'] ?? '') !== ''
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['fragment']);

        if (! $valid) {
            throw new InvalidArgumentException('DrawHouse webhook url must be an absolute https:// URL with a host name, no credentials and no fragment');
        }
    }

    /**
     * @param  array<mixed>  $events
     */
    private function assertWebhookEvents(array $events): void
    {
        foreach ($events as $event) {
            if (! $event instanceof WebhookEventType) {
                throw new InvalidArgumentException(sprintf('DrawHouse webhook events must be WebhookEventType cases, %s given', get_debug_type($event)));
            }

            if (! $event->isSubscribable()) {
                throw new InvalidArgumentException('DrawHouse webhook.ping cannot be subscribed to; use testWebhook() to send one');
            }
        }
    }

    private function assertWebhookDescription(string $description): void
    {
        if (mb_strlen($description) > 255) {
            throw new InvalidArgumentException('DrawHouse webhook description must be 255 characters or fewer');
        }
    }

    /**
     * Send a request, attaching the access token where required, handling
     * 401 → refresh → retry (falling back to login), and unwrapping the
     * `{ errorCode, data }` envelope. Returns `data`.
     *
     * @throws DrawHouseException
     */
    private function send(Request $request): mixed
    {
        if (! $request instanceof RequiresAccessToken) {
            return $this->unwrap($this->connector->send($request));
        }

        $request->authenticate(new HeaderAuthenticator($this->accessToken(), self::ACCESS_TOKEN_HEADER));

        $response = $this->connector->send($request);

        if ($response->status() === 401) {
            $this->reauthenticate();

            $request->authenticate(new HeaderAuthenticator($this->accessToken(), self::ACCESS_TOKEN_HEADER));

            $response = $this->connector->send($request);

            if ($response->status() === 401) {
                throw AuthenticationException::stillUnauthorised();
            }
        }

        return $this->unwrap($response);
    }

    /**
     * send(), retrying ONCE on the documented transient failure (HTTP 500 /
     * errorCode 3). This is safe to retry with backoff; a single immediate
     * retry covers the blip case, and anything beyond that is for
     * the caller (catch ServerException / isRetryable()).
     */
    private function sendWithRetry(Request $request): mixed
    {
        try {
            return $this->send($request);
        } catch (DrawHouseException $e) {
            if (! $e->isRetryable()) {
                throw $e;
            }

            return $this->send($request);
        }
    }

    /**
     * A usable access token: cached, else refreshed, else freshly logged in.
     */
    private function accessToken(): string
    {
        if (($token = $this->tokens->accessToken()) !== null) {
            return $token;
        }

        $this->reauthenticate();

        return $this->tokens->accessToken() ?? throw new AuthenticationException('Unable to obtain a DrawHouse access token', null, 0);
    }

    /**
     * Refresh if we hold a refresh token, otherwise (or if refresh fails) log in.
     */
    private function reauthenticate(): void
    {
        if ($this->tokens->refreshToken() !== null) {
            try {
                $this->refreshAccessToken();

                return;
            } catch (AuthenticationException) {
                $this->tokens->forgetTokens();
            }
        }

        $this->login();
    }

    /**
     * Validate the response envelope and return `data`.
     *
     * The standard envelope is `{ errorCode, data }`; success responses may
     * omit `errorCode`, so it is treated as 0 when absent. A non-zero `errorCode` means `data` is an error string.
     *
     * With `$bare` (only /login) the body itself is the payload when it has
     * no `data` key. `$successCodes` lists the errorCode values that mean
     * success on this route: 0 everywhere except /refresh-access-token,
     * which answers 200.
     *
     * Failures are mapped to typed exceptions by DrawHouseException::fromResponse().
     *
     * @param  list<int>  $successCodes
     *
     * @throws DrawHouseException
     */
    private function unwrap(Response $response, bool $bare = false, array $successCodes = [0]): mixed
    {
        try {
            $json = $response->json();
        } catch (JsonException) {
            $json = null;
        }

        if (! is_array($json)) {
            throw $response->failed()
                ? DrawHouseException::http($response->status())
                : DrawHouseException::malformed($response->status());
        }

        $errorCode = (int) ($json['errorCode'] ?? 0);

        if (! in_array($errorCode, $successCodes, true)) {
            throw DrawHouseException::fromResponse($response->status(), $errorCode, $json['data'] ?? null);
        }

        if ($response->failed()) {
            throw DrawHouseException::fromResponse($response->status(), null, $json['data'] ?? $json['error'] ?? null);
        }

        if (array_key_exists('data', $json)) {
            return $json['data'];
        }

        if ($bare && ! array_key_exists('errorCode', $json)) {
            return $json;
        }

        // A success envelope always carries `data`. Treating its absence as an
        // empty list would turn a truncated response into a genuine empty report.
        throw DrawHouseException::missingData($response->status());
    }

    private function dateParam(CarbonInterface|string $date): string
    {
        return $date instanceof CarbonInterface ? $date->toDateString() : $date;
    }
}
