<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Exceptions;

/**
 * HTTP 404 — the resource does not exist, or does not belong to your
 * affiliate. Raised by /v2/partners/players/{id} for a player that is not
 * yours, by /v2/partners/charity-raffles/live/{gameId} when that game has no
 * live draw for your affiliate, and by /v2/partners/me when no partner
 * profile exists for the user.
 */
class NotFoundException extends DrawHouseException {}
