# DrawHouse Partner API SDK

A thin, dependency-light Laravel package that wraps the [DrawHouse](https://api.prod.raffletown.co.uk) Partner API one-to-one, built on [Saloon](https://docs.saloon.dev). Targets version **1.2** of the Partner API.

- One typed method per endpoint, every response a readonly DTO.
- Transparent auth: login, cached tokens, `401 → refresh → retry`, re-login if the refresh fails (including a `422` refresh rejection).
- The `{ errorCode, data }` envelope is unwrapped for you; failures throw a typed `DrawHouseException` subclass per the API's error table.
- No database, migrations, scheduler or events. Persistence and sync belong in the consuming app.

## Installation

```bash
composer require digitonic/drawhouse-sdk
```

The service provider and `DrawHouse` facade are auto-discovered. Publish the config if you want to tweak it:

```bash
php artisan vendor:publish --tag=drawhouse-config
```

Put these in the **consuming application's** `.env`, never in this repository (it is a library with no `.env` of its own).

```dotenv
DRAWHOUSE_BASE_URL=https://api.prod.raffletown.co.uk
DRAWHOUSE_EMAIL=partner@example.com
DRAWHOUSE_PASSWORD=secret
# Optional: skip the /v2/partners/me lookup entirely. Leave blank on the first run and copy the value
# the smoke command prints from /v2/partners/me.
DRAWHOUSE_AFFILIATE_ID=
# Optional: cache store for tokens + affiliate id (defaults to the app default)
DRAWHOUSE_CACHE_STORE=
```

## Usage

Inject `Digitonic\DrawHouse\DrawHouse` or use the `DrawHouse` facade. List methods return a plain PHP `list<Dto>`; `allPlayers()` returns a `LazyCollection`.

### Auth

You never need to call these directly. They are here for completeness and for warming the cache.

```php
use Digitonic\DrawHouse\Facades\DrawHouse;

$tokens = DrawHouse::login();              // POST /login  (400 malformed, 401 wrong credentials, 403 blocked)
$tokens = DrawHouse::refreshAccessToken(); // POST /refresh-access-token  (answers errorCode 200, not 0)
DrawHouse::forgetTokens();                 // drop the cached pair
```

A `422` from the refresh route means the refresh token has expired or does not match; the client logs in again automatically. A `400` means a token was missing from the body. `AuthenticationException::isRefreshTokenExpired()` and `isAccountBlocked()` tell the two apart if you catch them directly.

### Partner profile

```php
$me = DrawHouse::me();            // GET /v2/partners/me → Partner
$me->affiliateId;                 // int|null
$me->isLive();                    // bool
$me->status; $me->playerId; $me->email; $me->firstName; $me->lastName; $me->phone; $me->companyName; $me->crmInScope;
$me->pooledRevShare; $me->ringFencedRevShare; $me->minimumGuaranteedIncome; $me->launchProposal; // commercial, as-is

$id = DrawHouse::affiliateId();   // int, cached forever after the first call (or from config)
```

Rate guidance: call once and cache the affiliate id. `affiliateId()` does that for you, and throws `PartnerNotLiveException` (with `->partnerStatus`) if the profile has no `affiliateId`.

Use `$me->status` / `isLive()` to decide whether the partner is live, not the presence of `affiliateId`. The players feed answers `403 partner_not_live` until the partner is `LIVE`.

### Players feed

The endpoint for player pulls: attribution, account status, activity and marketing consent in one row, cursor-paged. The affiliate is taken from the token; the partner must be `LIVE`.

```php
use Digitonic\DrawHouse\DTO\PlayerDetail;
use Digitonic\DrawHouse\Enums\AccountStatus;
use Digitonic\DrawHouse\Enums\AcquisitionChannel;
use Digitonic\DrawHouse\Support\PlayersQuery;

$query = PlayersQuery::make()
    ->updatedAfter($lastRunAt)                                // daily incremental pulls
    ->limit(500)                                              // default 100, clamped to 500
    ->status(AccountStatus::ACTIVE, AccountStatus::TIMED_OUT) // repeatable
    ->acquisitionChannel(AcquisitionChannel::PAID)            // repeatable
    ->marketable()                                            // bool filter
    ->includeLifetime();                                      // adds the GBP lifetime block
// also ->createdAfter() / ->createdBefore() — createdBefore must be later, validated client-side

// Every page, lazily (follows nextCursor until null). Nothing is fetched until iterated.
DrawHouse::allPlayers($query)->each(function (PlayerDetail $p) {
    $p->id; $p->email; $p->firstName; $p->lastName; $p->displayName; $p->phone;   // full PII – handle per your data-protection obligations
    $p->dateOfBirth; $p->gender; $p->registeredAt; $p->updatedAt;
    $p->accountStatus;        // AccountStatus: DELETED, DEACTIVATED, GAMSTOP_EXCLUDED, SELF_EXCLUDED, KYC_FAILED, TIMED_OUT, ACTIVE
    $p->statusChangedAt;
    $p->marketable;           // DrawHouse's own rule; false means do not contact on ANY channel

    $p->suppression->selfExcluded; ->selfExclusionUntil; ->selfExclusionForever; ->gamstop; ->timedOutUntil; ->kycStatus; ->emailSuppressed;
    $p->attribution->utmSource; ->utmMedium; ->utmCampaign; ->landingUrl; ->params; ->param('clickid');
    $p->attribution->acquisitionChannel;   // PAID | ORGANIC | UNKNOWN — DrawHouse's rule, see below
    $p->activity->lastLoginAt; ->firstPurchaseAt; ->lastPurchaseAt; ->firstDepositAt;
    $p->consents->email->granted; ->sms->granted; ->partnerOffers->granted;   // partnerOffers->recordedAt is always null
    $p->lifetime?->depositCount; ->depositValue; ->cashSpend; ->siteCreditSpend; ->refunds; ->cashPrizesWon; ->withdrawals;   // GBP
});

// One page at a time.
$page = DrawHouse::playersPage($query);           // PlayerPage
$page->items; $page->nextCursor; $page->hasMore();
$next = DrawHouse::playersPage($query, after: $page->nextCursor);

// One player (lifetime always included). 404 → NotFoundException when the player is not yours.
$player = DrawHouse::player(123);
```

Rate guidance: daily, with `updatedAfter`. Filtered pages are filled server-side, so a short page **is** the end of the data and `nextCursor` is `null` exactly then.

**`TIMED_OUT` is computed at read time.** DrawHouse derive it from `suppression.timedOutUntil` when you read; it clears when that time passes with no status-change event and without `updatedAt` moving, so an `updatedAfter` pull will never tell you it ended, and a cached `accountStatus` is stale as soon as the clock moves. Evaluate it yourself:

```php
$p->isTimedOutAt(now());        // from timedOutUntil, ignores the cached status
$p->effectiveStatusAt(now());   // accountStatus with TIMED_OUT ⇄ ACTIVE re-evaluated; other statuses unchanged
$p->isContactableAt(now());     // marketable && !isTimedOutAt()
```

**`acquisitionChannel` is DrawHouse's rule, not ours.** `PAID` = any click-id parameter or a paid `utm_medium`; `ORGANIC` = `utm_medium` in organic/referral/social/email/editorial or no parameters at all; `UNKNOWN` otherwise. If that does not match your own tracked-link vocabulary, classify from `attribution->params` / the UTM triple with your own rule for organic-vs-paid reporting. The SDK exposes the value as-is and deliberately ships no classifier.

### Legacy players report (deprecated)

```php
DrawHouse::players();   // GET /v2/partners/reports/players → list<Player>  — @deprecated, use the players feed
```

Still available but has no paging, status, attribution or consent data. Kept for compatibility.

### Daily affiliate reports and UTM breakdown

**Counts are cumulative snapshots** as at the end of the report day, not that day's activity.

```php
use Digitonic\DrawHouse\Enums\ReportInterval;

$reports = DrawHouse::dailyReports($start, $end);                         // GET /v2/affiliates/reports?interval=days → list<DailyReport>
$reports = DrawHouse::dailyReports($start, $end, ReportInterval::WEEKS);  // interval=weeks

foreach ($reports as $report) {
    $report->registrations;       // players registered up to the end of the day  (reconcile against this)
    $report->played;              // of those, ever bought an entry              (reconcile against this)
    $report->active;              // of those, bought in the report's CALENDAR MONTH (resets on the 1st)
    $report->internalMarketable;  // of those, email or SMS opt-in
    $report->externalMarketable;  // of those, opted in to partner offers
    $report->uniqueVisitors; $report->pageViews;   // all-time site traffic; 0 when analytics is not configured
    $report->startAt;             // CarbonImmutable, start of the day covered
}

// Per-day activity, derived from consecutive snapshots. Fetches one extra day
// before $start so the first day has something to subtract from.
foreach (DrawHouse::dailyReportDeltas($start, $end) as $delta) {
    $delta->day();               // the later snapshot's day
    $delta->registrations;       // new registrations that day (may be negative after an erasure)
    $delta->played; $delta->active; $delta->internalMarketable; ...
    $delta->days();              // 1 for consecutive snapshots; larger means a gap
    $delta->crossesMonthBoundary();   // true → ignore ->active, it has reset
}
$today->deltaFrom($yesterday);   // or DailyReportDelta::fromSnapshots($reports)

foreach (DrawHouse::utms($report) as $utm) {   // GET /v2/affiliates/reports/{dailyReportId}/utms → list<UtmBreakdown>
    $utm->source; $utm->medium; $utm->campaign;   // "default" when no UTM was present
    $utm->count; $utm->registered; $utm->isUntracked();
}
```

Rate guidance: daily, for the previous day. The daily job records the **previous** day, so today's figures appear tomorrow. Only `source`, `medium` and `campaign` UTMs exist; there is no `utm_content`. UTM `count` / `registered` are returned as the API sends them; the SDK derives no deltas from them.

### Games

```php
use Digitonic\DrawHouse\Enums\GameState;
use Digitonic\DrawHouse\Enums\Participation;

// One page. Unauthenticated – identified by affiliateId only.
$page = DrawHouse::games(
    participation: Participation::JOINED,               // ALL (default) | JOINED | AVAILABLE
    states: [GameState::READY, GameState::INPROGRESS],   // null = API defaults; sent as state=READY&state=INPROGRESS
    limit: 50,
    offset: 0,
);

// Every page, lazily.
foreach (DrawHouse::allGames(Participation::JOINED) as $game) {
    $game->state;                          // GameState enum
    $game->isParticipating;                // bool
    $game->myEdition()?->slug;             // your edition of the game
    $game->fields->raffleEntryType;        // RaffleEntryType (DRAW_ONLY | INSTANT_ONLY | INSTANT+DRAW)
    $game->fields->maxTickets;             // int   (wire: "550")
    $game->fields->hasCashPrize;           // bool  (wire: "true")
    $game->fields->scheduledDrawTime;      // CarbonImmutable — the ADVERTISED time, not the countdown target

    $round = $game->currentRound();
    $round?->state;                        // RoundState (SETUP, READY, OPEN, RESULTED, LIVE_DRAW, COMPLETED, CANCELLED, VOID)
    $round?->ticketPrice;                  // float (wire: "0.20")
    $round?->schedule->drawAt;             // CarbonImmutable — opensAt, drawAt, liveDrawAt, completesAt, cancelledAt
    $round?->nextTransition?->scheduledAt; // CarbonImmutable, with ->toState / ->state
    $round?->prizes[0]->type;              // PrizeType: DRAW | INSTANT   (v1.0's INSTANT_* still parse, deprecated)
    $round?->prizes[0]->category;          // PrizeCategory: PHYSICAL | CASH | SITE_CREDIT | VOUCHER
    $round?->prizes[0]->soldCount;         // and ->totalAvailable, ->remaining(), ->amount

    $game->raw;                            // everything DrawHouse sent, untouched
}
```

Paging is applied **before** the participation filter, so a page shorter than `limit` is not the last page. `allGames()` keeps going until a page comes back empty. Default states are `READY, INPROGRESS, REPORTED, RESULTED, COMPLETED`; `SETUP, ARCHIVED, DELETED, CANCELLED` are returned only when passed explicitly.

HTTP 500 / `errorCode 3` on this endpoint is documented as transient and is retried once automatically. HTTP 400 / `errorCode 7` is a bad query parameter and is not retried.

### Live draws (widgets)

Everything an "enter now" widget needs: your branded edition, ticket price, tickets sold and left, countdown timestamps, prizes, instant-win availability and live-draw status. Unauthenticated; `affiliateId` picks the edition that comes back. Built for a server of yours that polls it, not for browsers.

```php
use Digitonic\DrawHouse\Enums\RoundState;

$draws = DrawHouse::liveDraws(                      // GET /v2/partners/charity-raffles/live → list<LiveDraw>
    states: [RoundState::OPEN, RoundState::LIVE_DRAW],   // null = all four (OPEN, LIVE_DRAW, RESULTED, COMPLETED); repeatable
    editionId: 7,                                   // optional, when you run more than one edition on a game
    limit: 20,                                      // default 20, clamped to 100
);
$draw = DrawHouse::liveDraw($gameId);               // GET /v2/partners/charity-raffles/live/{gameId} → LiveDraw; 404 → NotFoundException

$draw->gameId; $draw->gameName; $draw->roundId; $draw->roundState; $draw->entryType;
$draw->edition->name; ->slug; ->description; ->url;        // deep link to your prize-draw page — send traffic here
$draw->images['hero']->base; ->variants['2x'];              // surface => ImageSet; $draw->image('hero', '2x')
$draw->tickets->price;                                      // STRING "5.00" (->priceAmount() for a float)
$draw->tickets->max; ->sold; ->available; ->maxPerPlayer;   // ints, exact; "tickets left" is ->available
$draw->schedule->opensAt; ->drawAt; ->liveDrawAt; ->completesAt;
$draw->nextTransition?->scheduledAt;
$draw->prizes[0]->name; ->type; ->category; ->amount; ->imageUrl; ->totalAvailable; ->displayOrder;   // your edition's names/images applied
$draw->instantWins?->remaining; ->totalPrizes; ->revealed; ->claimed; ->totalValue; ->perPrize[0]->remaining;   // null when the round has none
$draw->liveDraw?->state; ->armed; ->drawTime; ->revealed; ->revealedAt;   // null until the round is resulted
$draw->asOf;                                                // when the snapshot was built; up to 15s old

// Countdown
$draw->countdownTarget();        // liveDrawAt when a live draw is armed, else schedule.drawAt — NEVER scheduledDrawTime
$draw->secondsUntilDraw();       // int, floored at 0
$draw->isLiveDrawArmed(); $draw->isOpenForEntry();
```

Rate guidance: responses are **cached server-side for 15 seconds** (`Cache-Control: public, max-age=15`), so poll every 15–30 seconds; polling faster returns nothing newer. `drawAt` is when entries close and the draw runs, typically 15 minutes **before** the advertised `scheduledDrawTime`, which is why the countdown never targets the latter.

### Tickets

```php
$tickets = DrawHouse::tickets($game);   // GET /v2/games/tickets?gameId= → list<Ticket>  (Game or int)
$tickets[0]->price;                     // float, amount actually paid (discounted batches can be below base price)
$tickets[0]->playerId;                  // int, matches PlayerDetail::$id / Player::$id
$tickets[0]->paymentStatus;             // e.g. SUCCEEDED
$tickets[0]->createdAt;                 // CarbonImmutable — bucket by this to chart entries over a draw
$tickets[0]->utmSource;                 // null when empty on the wire; also utmMedium, utmCampaign, hasUtm()
```

Rate guidance: no more than once every 5 minutes. Aggregate sold/available counts for open draws are also on `liveDraws()`.

### Balances

```php
$balances = DrawHouse::balances();      // GET /v2/players/balances → Balances (one aggregate object)
$balances->cashBalance; $balances->siteCreditBalance; $balances->vaultKeyBalance;   // floats
```

Rate guidance: no more than once every 15 minutes.

## Rate guidance

Documented per method and **not enforced**. Throttling is the caller's job.

| Method | Endpoint | Guidance |
| --- | --- | --- |
| `me()` / `affiliateId()` | `GET /v2/partners/me` | once, cache `affiliateId` |
| `allPlayers()` / `playersPage()` / `player()` | `GET /v2/partners/players[/{id}]` | daily, with `updatedAfter` |
| `players()` (deprecated) | `GET /v2/partners/reports/players` | daily |
| `dailyReports()` / `dailyReportDeltas()` | `GET /v2/affiliates/reports` | daily, previous day |
| `utms()` | `GET /v2/affiliates/reports/{id}/utms` | daily, previous day |
| `games()` / `allGames()` | `GET /v2/partners/charity-raffles/games` | — |
| `liveDraws()` / `liveDraw()` | `GET /v2/partners/charity-raffles/live[/{gameId}]` | every 15–30 s (15 s server cache) |
| `tickets()` | `GET /v2/games/tickets` | every 5 min at most |
| `balances()` | `GET /v2/players/balances` | every 15 min at most |

## Errors

The API's error table is mapped to typed exceptions; all extend `DrawHouseException`.

```php
use Digitonic\DrawHouse\Exceptions\AuthenticationException;    // login/refresh rejected, or still 401 after re-auth
use Digitonic\DrawHouse\Exceptions\DrawHouseException;         // base: ->errorCode, ->status, ->isRetryable()
use Digitonic\DrawHouse\Exceptions\InvalidRequestException;    // 400 / errorCode 7 — fix the request
use Digitonic\DrawHouse\Exceptions\NotFoundException;          // 404 — not yours, or no live draw
use Digitonic\DrawHouse\Exceptions\PartnerNotLiveException;    // 403 { error: partner_not_live, status } → ->partnerStatus
use Digitonic\DrawHouse\Exceptions\ServerException;            // 500 / errorCode 3 — retry with backoff

try {
    DrawHouse::liveDraw($gameId);
} catch (NotFoundException) {
    // no live draw for that game
} catch (PartnerNotLiveException $e) {
    $e->partnerStatus;  // e.g. ONBOARDING — contact the DrawHouse onboarding engineer
} catch (ServerException $e) {
    // the SDK already retried once; back off and try again later
} catch (DrawHouseException $e) {
    $e->errorCode; $e->status;
}
```

| HTTP | errorCode | Exception | What to do |
| --- | --- | --- | --- |
| 400 | 7 | `InvalidRequestException` | Fix the request; the message names the parameter |
| 401 | 401 | handled: refresh, then re-login | `AuthenticationException` only if still 401 |
| 403 | 403 | `PartnerNotLiveException` / `DrawHouseException` | Partner not `LIVE` / not a partner |
| 404 | 404 | `NotFoundException` | Check the id |
| 422 | 422 | handled: re-login | Refresh route only |
| 500 | 3 | `ServerException` (retryable) | Retried once; back off further yourself |

Exception messages never contain credentials or tokens, and the SDK logs nothing.

## Token lifecycle

Tokens live in the Laravel cache under the `drawhouse:` prefix. `/login` returns `accessTokenExpiry` and `refreshTokenExpiry`, which set the cache TTLs (configured fallbacks apply if absent). A configurable buffer (default 30s) refreshes slightly early.

`/refresh-access-token` returns only a new access token (with `errorCode: 200`, the one route that does not answer 0); the refresh token is unchanged and kept until its own expiry. The access token issued alongside the refresh token is retained (never used for authentication) so the refresh body can always send both tokens, even after the access token has expired. A `422` from the refresh route drops the cached pair and logs in afresh.

## Live smoke test (opt-in)

Once DrawHouse have issued credentials, verify the v1.2 surface against the real API:

```bash
php artisan drawhouse:smoke --players --live --reports
```

Run it from the consuming application (the one holding the `DRAWHOUSE_*` env values above); the SDK repo itself has no application bootstrap. It prints ids, counts and statuses only (never tokens or PII) and is never run by the test suite or a scheduler. On the first run leave `DRAWHOUSE_AFFILIATE_ID` blank: the `/v2/partners/me` step prints the `affiliateId` and `status` DrawHouse return, and a `partner_not_live` warning on the later steps is expected until the status is `LIVE`.

## DTO notes

- Field names and types follow version 1.2 of the DrawHouse Partner API.
- All DTOs are `final readonly` with a `fromArray()` constructor and a `raw` property holding the untouched payload, so fields DrawHouse adds later are never lost.
- All ids are integers.
- `Game.fields.*`, `Round.ticketPrice` and `LiveDraw.tickets.price` are strings on the wire. The first two are normalised to `int` / `float` / `bool` / `CarbonImmutable`; the live price is kept as a string (`priceAmount()` for a float). `Ticket.price`, balances and the players-feed `lifetime` block are numbers.
- Optional fields the API **omits** (`currentRoundId`, `editions`, `nextTransition`, `prizes`, `ticketPrice`, `lifetime`, `instantWins`, several `fields.*` and `schedule.*`) come through as `null` or `[]`. `liveDraw` is explicitly `null` until resulted.
- Unknown enum values parse to `null` rather than throwing; the wire value is still in `raw`.
- All timestamps are `CarbonImmutable` in UTC, including DrawHouse's nanosecond-precision token expiries.

## Testing

```bash
composer test
composer lint
```

Tests use Orchestra Testbench and Saloon's `MockClient`. No test makes a live call, and every fixture is synthetic: all names, ids and values are fake.

### Response-shape fixtures (opt-in)

`tests/Fixtures/live/*.json` have the keys and types of real API responses, with fake values. `LiveFixturesTest` replays them through the SDK. From an application holding live credentials you can compare the real API against them, or re-record them:

```bash
php artisan drawhouse:fixtures --check    # report where live response shapes differ from the fixtures
php artisan drawhouse:fixtures --record   # overwrite the fixtures with redacted live responses
```

Recording redacts PII, tokens, commercial terms and account identifiers (`affiliateId`, `playerId`, `companyName`), but **not** game, prize or image values. Review a recording, and replace anything that identifies a real account, before committing it.

## License

MIT. See [LICENSE](LICENSE).
