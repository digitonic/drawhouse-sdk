<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Contracts;

/**
 * Marker for requests that need the `access-token` header. The client
 * attaches the token and handles 401 → refresh → retry for these only.
 */
interface RequiresAccessToken {}
