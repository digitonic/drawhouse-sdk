# Changelog

All notable changes to `digitonic/drawhouse-sdk`. Versions follow [semver](https://semver.org).

## 1.2.0 — Unreleased

- **Laravel 13 apps on Saloon's Laravel plugin v5**: `saloonphp/laravel-plugin` now accepts `^4.0|^5.0`. Nothing in `src/` uses the plugin; it is only needed by the test suite, and by apps that fake requests with `Saloon::fake()`.
- **`TokenStore::accessTokenExpiry()`**: when the cached access token lapses (the API's expiry less the buffer), or null when none is cached. For health checks and dashboards; it is cleared with the other token keys by `forgetTokens()`.

## 1.1.0 — 2026-10-19

Adds DrawHouse partner webhooks.

- **Endpoint management** (`/v2/partners/webhooks`): `webhooks()`, `webhook()`, `createWebhook()`, `updateWebhook()` (sends only the changed fields), `deleteWebhook()`, `testWebhook()`, `rotateWebhookSecret()`, `webhookDeliveriesPage()` and `allWebhookDeliveries()` (a `LazyCollection` that follows `nextCursor`). DTOs: `WebhookEndpoint`, `WebhookEndpointWithSecret` (the secret is kept out of debug output), `WebhookDelivery`, `WebhookDeliveriesPage`.
- **Events**: `WebhookEvent` with typed `data`: `RoundStateChangedData` (with `WebhookEdition`), `PlayerData` (reusing the players-feed `Suppression`, `Consents` and `AccountStatus`, plus `changes`) and `PingData`. Enums `WebhookEventType`, `WebhookDeliveryStatus` and `PlayerChange`.
- **Signature verification**: `WebhookVerifier` checks `X-DrawHouse-Signature` (`HMAC-SHA256(secret, t + "." + body)`, compared with `hash_equals`), accepts several secrets for rotations, enforces a configurable timestamp tolerance (default 5 minutes) and throws `WebhookVerificationException` with a `reason`. `event()` verifies and parses in one call. It can be resolved from the container using the new `drawhouse.webhooks` config.
- **Errors**: HTTP 503 is now a retryable `ServerException`, for the webhook routes' "secret storage unavailable". The games and live-draw reads, which already retry a 500 once, now retry a 503 once too; webhook writes are never retried automatically.
- **Ops command**: `drawhouse:webhooks` lists, registers, pings and rotates endpoints and shows the delivery log.
- `Parse::enum()` now matches an exact value before the upper-snake form, so lowercase wire values parse.

## 1.0.0 — 2026-10-13

Initial release. Targets version **1.2** of the DrawHouse Partner API. Released under the MIT licence.

- **Auth**: `login()`, `refreshAccessToken()`, `forgetTokens()`. Tokens are cached; `401 → refresh → retry`, and a failed refresh (including `422`) falls back to a fresh login. Login 400 / 401 / 403 and refresh 400 / 422 are distinguished, with `AuthenticationException::isAccountBlocked()` / `isRefreshTokenExpired()`.
- **Partner profile**: `me()` → `Partner` (with `isLive()`), and `affiliateId()`, cached after the first call or read from config.
- **Players feed** (`GET /v2/partners/players`, `/{id}`): `playersPage()`, `allPlayers()` (a `LazyCollection` that follows `nextCursor`), `player()`, the `PlayersQuery` filter builder, and `PlayerDetail` with typed `Attribution`, `Activity`, `Suppression`, `Consents` and `Lifetime`. `isTimedOutAt()`, `effectiveStatusAt()` and `isContactableAt()` evaluate `TIMED_OUT`, which DrawHouse compute at read time.
- **Daily reports**: `dailyReports()` (cumulative snapshots, days or weeks), `dailyReportDeltas()` / `DailyReport::deltaFrom()` for per-day activity, and `utms()` for the UTM breakdown.
- **Games**: `games()` and `allGames()` (paginates until an empty page).
- **Live draws**: `liveDraws()` and `liveDraw()` → `LiveDraw`, with `countdownTarget()` / `secondsUntilDraw()`.
- **Tickets and balances**: `tickets()` and `balances()`.
- **Errors**: every failure is a `DrawHouseException`: `InvalidRequestException` (400 / 7), `PartnerNotLiveException` (403 `partner_not_live`), `NotFoundException` (404), `ServerException` (500 / 3, retried once) or `AuthenticationException`.
- **Opt-in commands**: `drawhouse:smoke` for a live check, and `drawhouse:fixtures --record` / `--check` to record redacted response shapes or compare the live API against them. Neither is run by tests or schedulers.

### Deprecated

- `players()` (`GET /v2/partners/reports/players`): still works, but has no paging, status, attribution or consent data. Use the players feed.
- `PrizeType::INSTANT_CASH`, `INSTANT_SITE_CREDIT`, `INSTANT_PHYSICAL`: v1.2 documents only `DRAW` and `INSTANT`, with the kind in `Prize::$category`. The cases remain so these values still parse.
