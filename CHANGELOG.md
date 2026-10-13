# Changelog

All notable changes to `digitonic/drawhouse-sdk`. Versions follow [semver](https://semver.org).

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
