<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests\Concerns;

use GuzzleHttp\Psr7\Query;
use Psr\Http\Message\RequestInterface;
use Saloon\Http\PendingRequest;

/**
 * DrawHouse's repeatable query parameters (`state=READY&state=INPROGRESS`,
 * `status=ACTIVE&status=TIMED_OUT`) are a repeated key, not a comma list and
 * not PHP's `state[0]=` bracket form. Saloon serialises arrays with
 * http_build_query, which would produce brackets, so the query string is
 * rebuilt with Guzzle's builder, which repeats the key for list values.
 */
trait SendsRepeatableQuery
{
    public function handlePsrRequest(RequestInterface $request, PendingRequest $pendingRequest): RequestInterface
    {
        $request = parent::handlePsrRequest($request, $pendingRequest);

        $query = Query::build($pendingRequest->query()->all(), PHP_QUERY_RFC3986);

        return $request->withUri($request->getUri()->withQuery($query));
    }
}
