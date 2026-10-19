<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\DTO\Webhooks\WebhookDelivery;
use Digitonic\DrawHouse\DTO\Webhooks\WebhookEndpoint;
use Digitonic\DrawHouse\DTO\Webhooks\WebhookEndpointWithSecret;
use Digitonic\DrawHouse\DTO\Webhooks\WebhookResource;
use Digitonic\DrawHouse\Enums\WebhookDeliveryStatus;
use Digitonic\DrawHouse\Enums\WebhookEventType;
use Digitonic\DrawHouse\Exceptions\InvalidRequestException;
use Digitonic\DrawHouse\Exceptions\NotFoundException;
use Digitonic\DrawHouse\Exceptions\PartnerNotLiveException;
use Digitonic\DrawHouse\Exceptions\ServerException;
use Digitonic\DrawHouse\Http\Requests\CreateWebhookRequest;
use Digitonic\DrawHouse\Http\Requests\DeleteWebhookRequest;
use Digitonic\DrawHouse\Http\Requests\GetWebhookDeliveriesRequest;
use Digitonic\DrawHouse\Http\Requests\GetWebhookRequest;
use Digitonic\DrawHouse\Http\Requests\ListWebhooksRequest;
use Digitonic\DrawHouse\Http\Requests\RotateWebhookSecretRequest;
use Digitonic\DrawHouse\Http\Requests\TestWebhookRequest;
use Digitonic\DrawHouse\Http\Requests\UpdateWebhookRequest;
use Illuminate\Support\LazyCollection;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(fn () => seedTokens());

// ---------------------------------------------------------------------------
// DTOs
// ---------------------------------------------------------------------------

it('parses every field of a webhook endpoint', function () {
    $e = WebhookEndpoint::fromArray(webhookEndpointFixture());

    expect($e->id)->toBe(7)
        ->and($e->url)->toBe('https://hooks.example.com/drawhouse')
        ->and($e->events)->toBe([WebhookEventType::ROUND_STATE_CHANGED])
        ->and($e->active)->toBeTrue()
        ->and($e->isActive())->toBeTrue()
        ->and($e->description)->toBe('test receiver')
        ->and($e->consecutiveFailures)->toBe(0)
        ->and($e->lastSuccessAt?->toIso8601ZuluString())->toBe('2026-10-07T14:03:14Z')
        ->and($e->lastFailureAt)->toBeNull()
        ->and($e->createdAt?->toIso8601ZuluString())->toBe('2026-10-01T09:00:00Z')
        ->and($e->updatedAt?->toIso8601ZuluString())->toBe('2026-10-07T14:03:14Z')
        ->and($e->wasAutoDisabled())->toBeFalse()
        ->and($e->raw)->toBe(webhookEndpointFixture());
});

it('treats an empty events list as every type, and always receives a ping', function () {
    $all = WebhookEndpoint::fromArray(webhookEndpointFixture(overrides: ['events' => []]));
    $rounds = WebhookEndpoint::fromArray(webhookEndpointFixture());

    expect($all->receives(WebhookEventType::PLAYER_UPDATED))->toBeTrue()
        ->and($rounds->receives(WebhookEventType::ROUND_STATE_CHANGED))->toBeTrue()
        ->and($rounds->receives(WebhookEventType::PLAYER_UPDATED))->toBeFalse()
        ->and($rounds->receives(WebhookEventType::PING))->toBeTrue();
});

it('drops unknown event types and spots an endpoint switched off after 50 failures', function () {
    $e = WebhookEndpoint::fromArray(webhookEndpointFixture(overrides: [
        'events' => ['round.state_changed', 'draw.something_new'],
        'active' => false,
        'consecutiveFailures' => 50,
        'description' => '',
    ]));

    expect($e->events)->toBe([WebhookEventType::ROUND_STATE_CHANGED])
        ->and($e->wasAutoDisabled())->toBeTrue()
        ->and($e->description)->toBeNull()
        ->and($e->raw['events'])->toContain('draw.something_new');
});

it('keeps the secret apart from the endpoint and out of debug output', function () {
    $secret = webhookSecret();
    $e = WebhookEndpointWithSecret::fromArray(webhookEndpointFixture() + ['secret' => $secret]);

    expect($e->secret)->toBe($secret)
        ->and($e->hasSecret())->toBeTrue()
        ->and($e->endpoint->id)->toBe(7)
        ->and($e->endpoint->raw)->not->toHaveKey('secret')
        ->and(print_r($e, true))->not->toContain($secret)
        ->and(print_r($e, true))->toContain('[redacted]');
});

it('parses every field of a delivery log row', function () {
    $d = WebhookDelivery::fromArray(webhookDeliveryFixture());

    expect($d->id)->toBe(501)
        ->and($d->eventId)->toBe(1234)
        ->and($d->eventType)->toBe(WebhookEventType::ROUND_STATE_CHANGED)
        ->and($d->eventTypeName)->toBe('round.state_changed')
        ->and($d->resource)->toBeInstanceOf(WebhookResource::class)
        ->and($d->resource?->type)->toBe('round')
        ->and($d->resource?->id)->toBe('88')
        ->and($d->resource?->intId())->toBe(88)
        ->and($d->occurredAt?->toIso8601ZuluString())->toBe('2026-10-07T14:03:11Z')
        ->and($d->endpointId)->toBe(7)
        ->and($d->status)->toBe(WebhookDeliveryStatus::PENDING)
        ->and($d->status?->isFinal())->toBeFalse()
        ->and($d->attempt)->toBe(2)
        ->and($d->nextAttemptAt?->toIso8601ZuluString())->toBe('2026-10-07T14:39:20Z')
        ->and($d->lastResponseCode)->toBe(500)
        ->and($d->lastError)->toBe('http 500')
        ->and($d->deliveredAt)->toBeNull()
        ->and($d->createdAt?->toIso8601ZuluString())->toBe('2026-10-07T14:03:11Z')
        ->and($d->updatedAt?->toIso8601ZuluString())->toBe('2026-10-07T14:09:20Z')
        ->and($d->isDelivered())->toBeFalse()
        ->and($d->isDead())->toBeFalse();
});

it('reads a null response code as a failed connection', function () {
    $d = WebhookDelivery::fromArray(webhookDeliveryFixture(overrides: [
        'status' => 'DEAD',
        'attempt' => 8,
        'lastResponseCode' => null,
        'lastError' => 'dial tcp: connection refused',
    ]));

    expect($d->lastResponseCode)->toBeNull()
        ->and($d->isDead())->toBeTrue()
        ->and($d->status?->isFinal())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Endpoints
// ---------------------------------------------------------------------------

it('lists endpoints from GET /v2/partners/webhooks with the access token', function () {
    $mock = MockClient::global([
        ListWebhooksRequest::class => envelope(['items' => [webhookEndpointFixture(7), webhookEndpointFixture(8, ['events' => []])]]),
    ]);

    $endpoints = drawhouse()->webhooks();

    expect($endpoints)->toHaveCount(2)
        ->and($endpoints[0])->toBeInstanceOf(WebhookEndpoint::class)
        ->and($endpoints[1]->id)->toBe(8)
        ->and($endpoints[1]->events)->toBe([]);

    $pending = sentPendingRequest($mock, 0);
    expect($pending->getMethod())->toBe(Method::GET)
        ->and($pending->getUrl())->toBe('https://api.test.raffletown.co.uk/v2/partners/webhooks')
        ->and($pending->headers()->get('access-token'))->toBe('access-old');
});

it('returns an empty list when no endpoints are registered', function () {
    MockClient::global([ListWebhooksRequest::class => envelope(['items' => []])]);

    expect(drawhouse()->webhooks())->toBe([]);
});

it('fetches one endpoint from GET /v2/partners/webhooks/{id}', function () {
    $mock = MockClient::global([GetWebhookRequest::class => envelope(webhookEndpointFixture(7))]);

    $endpoint = drawhouse()->webhook(7);

    expect($endpoint->id)->toBe(7);
    expect(sentPendingRequest($mock, 0)->getUrl())->toEndWith('/v2/partners/webhooks/7');
});

it('accepts a WebhookEndpoint wherever an id is expected', function () {
    $mock = MockClient::global([GetWebhookRequest::class => envelope(webhookEndpointFixture(9))]);

    drawhouse()->webhook(WebhookEndpoint::fromArray(webhookEndpointFixture(9)));

    expect(sentPendingRequest($mock, 0)->getUrl())->toEndWith('/v2/partners/webhooks/9');
});

it('registers an endpoint with POST /v2/partners/webhooks and returns the secret once', function () {
    $secret = webhookSecret();
    $mock = MockClient::global([
        CreateWebhookRequest::class => envelope(webhookEndpointFixture(7, ['events' => ['round.state_changed', 'player.updated']]) + ['secret' => $secret], 201),
    ]);

    $created = drawhouse()->createWebhook(
        'https://hooks.example.com/drawhouse',
        [WebhookEventType::ROUND_STATE_CHANGED, WebhookEventType::PLAYER_UPDATED, WebhookEventType::ROUND_STATE_CHANGED],
        'test receiver',
    );

    expect($created)->toBeInstanceOf(WebhookEndpointWithSecret::class)
        ->and($created->secret)->toBe($secret)
        ->and($created->endpoint->events)->toBe([WebhookEventType::ROUND_STATE_CHANGED, WebhookEventType::PLAYER_UPDATED]);

    $pending = sentPendingRequest($mock, 0);
    expect($pending->getMethod())->toBe(Method::POST)
        ->and($pending->getUrl())->toEndWith('/v2/partners/webhooks')
        ->and($pending->headers()->get('Content-Type'))->toBe('application/json')
        ->and(sentJsonBody($mock, 0))->toBe([
            'url' => 'https://hooks.example.com/drawhouse',
            'events' => ['round.state_changed', 'player.updated'],
            'active' => true,
            'description' => 'test receiver',
        ]);
});

it('sends an empty events list (every type) and no description by default', function () {
    $mock = MockClient::global([
        CreateWebhookRequest::class => envelope(webhookEndpointFixture(7, ['events' => []]) + ['secret' => webhookSecret()], 201),
    ]);

    drawhouse()->createWebhook('https://hooks.example.com/drawhouse', active: false);

    expect(sentJsonBody($mock, 0))->toBe([
        'url' => 'https://hooks.example.com/drawhouse',
        'events' => [],
        'active' => false,
    ]);
});

it('rejects what the API would answer 400 for before sending anything', function (Closure $call, string $message) {
    $mock = MockClient::global([]);

    expect(fn () => $call(drawhouse()))->toThrow(InvalidArgumentException::class, $message);
    expect($mock->getRecordedResponses())->toBe([]);
})->with([
    'http url' => [fn ($d) => $d->createWebhook('http://hooks.example.com/drawhouse'), 'https://'],
    'relative url' => [fn ($d) => $d->createWebhook('/drawhouse'), 'https://'],
    'credentials in url' => [fn ($d) => $d->createWebhook('https://user:pass@hooks.example.com/'), 'credentials'],
    'fragment' => [fn ($d) => $d->createWebhook('https://hooks.example.com/#x'), 'fragment'],
    'ping subscription' => [fn ($d) => $d->createWebhook('https://hooks.example.com/', [WebhookEventType::PING]), 'webhook.ping'],
    'string event' => [fn ($d) => $d->createWebhook('https://hooks.example.com/', ['round.state_changed']), 'WebhookEventType'],
    'long description' => [fn ($d) => $d->createWebhook('https://hooks.example.com/', description: str_repeat('x', 256)), '255'],
    'empty update' => [fn ($d) => $d->updateWebhook(7), 'at least one'],
    'bad update url' => [fn ($d) => $d->updateWebhook(7, url: 'ftp://hooks.example.com/'), 'https://'],
]);

it('maps a 400 from register (e.g. the 10-endpoint limit) to InvalidRequestException', function () {
    MockClient::global([
        CreateWebhookRequest::class => envelopeError(7, 'createWebhook - handler - endpoint limit reached', 400),
    ]);

    expect(fn () => drawhouse()->createWebhook('https://hooks.example.com/drawhouse'))
        ->toThrow(InvalidRequestException::class, 'endpoint limit');
});

it('maps a 503 from register to a retryable ServerException', function () {
    MockClient::global([
        CreateWebhookRequest::class => envelopeError(503, 'secret storage unavailable', 503),
    ]);

    try {
        drawhouse()->createWebhook('https://hooks.example.com/drawhouse');
        $this->fail('Expected ServerException');
    } catch (ServerException $e) {
        expect($e->status)->toBe(503)
            ->and($e->errorCode)->toBe(503)
            ->and($e->isRetryable())->toBeTrue();
    }
});

it('does not retry webhook writes automatically', function () {
    $mock = MockClient::global([
        RotateWebhookSecretRequest::class => envelopeError(503, 'secret storage unavailable', 503),
    ]);

    expect(fn () => drawhouse()->rotateWebhookSecret(7))->toThrow(ServerException::class);
    expect(sentRequests($mock))->toBe([RotateWebhookSecretRequest::class]);
});

it('sends only the changed fields with PATCH /v2/partners/webhooks/{id}', function () {
    $mock = MockClient::global([
        UpdateWebhookRequest::class => envelope(webhookEndpointFixture(7, ['active' => false])),
    ]);

    $endpoint = drawhouse()->updateWebhook(7, active: false);

    expect($endpoint->active)->toBeFalse();

    $pending = sentPendingRequest($mock, 0);
    expect($pending->getMethod())->toBe(Method::PATCH)
        ->and($pending->getUrl())->toEndWith('/v2/partners/webhooks/7')
        ->and(sentJsonBody($mock, 0))->toBe(['active' => false]);
});

it('can change every field at once, clear the description and subscribe to every type', function () {
    $mock = MockClient::global([UpdateWebhookRequest::class => envelope(webhookEndpointFixture(7))]);

    drawhouse()->updateWebhook(7, url: 'https://hooks.example.com/v2', events: [], description: '', active: true);

    expect(sentJsonBody($mock, 0))->toBe([
        'url' => 'https://hooks.example.com/v2',
        'events' => [],
        'description' => '',
        'active' => true,
    ]);
});

it('deletes an endpoint with DELETE /v2/partners/webhooks/{id}', function () {
    $mock = MockClient::global([DeleteWebhookRequest::class => envelope(['status' => 'deleted'])]);

    drawhouse()->deleteWebhook(7);

    $pending = sentPendingRequest($mock, 0);
    expect($pending->getMethod())->toBe(Method::DELETE)
        ->and($pending->getUrl())->toEndWith('/v2/partners/webhooks/7');
});

it('maps a 404 on an endpoint that is not yours or was deleted', function (Closure $call, string $request) {
    MockClient::global([$request => envelopeError(404, 'webhook endpoint not found', 404)]);

    expect(fn () => $call(drawhouse()))->toThrow(NotFoundException::class);
})->with([
    'get' => [fn ($d) => $d->webhook(99), GetWebhookRequest::class],
    'update' => [fn ($d) => $d->updateWebhook(99, active: true), UpdateWebhookRequest::class],
    'delete' => [fn ($d) => $d->deleteWebhook(99), DeleteWebhookRequest::class],
    'test' => [fn ($d) => $d->testWebhook(99), TestWebhookRequest::class],
    'rotate' => [fn ($d) => $d->rotateWebhookSecret(99), RotateWebhookSecretRequest::class],
    'deliveries' => [fn ($d) => $d->webhookDeliveriesPage(99), GetWebhookDeliveriesRequest::class],
]);

it('maps 403 partner_not_live on the webhook routes', function () {
    MockClient::global([ListWebhooksRequest::class => partnerNotLiveResponse('ONBOARDING')]);

    expect(fn () => drawhouse()->webhooks())->toThrow(PartnerNotLiveException::class, 'ONBOARDING');
});

it('queues a test ping with POST /v2/partners/webhooks/{id}/test and returns the event', function () {
    $mock = MockClient::global([TestWebhookRequest::class => envelope(pingEventFixture(7), 202)]);

    $event = drawhouse()->testWebhook(7);

    expect($event->isPing())->toBeTrue()
        ->and($event->id)->toBe(3001)
        ->and($event->ping()?->endpointId)->toBe(7)
        ->and($event->ping()?->message)->toBe('ping');

    $pending = sentPendingRequest($mock, 0);
    expect($pending->getMethod())->toBe(Method::POST)
        ->and($pending->getUrl())->toEndWith('/v2/partners/webhooks/7/test');
});

it('rotates a secret with POST /v2/partners/webhooks/{id}/rotate-secret', function () {
    $secret = webhookSecret('rotated');
    $mock = MockClient::global([
        RotateWebhookSecretRequest::class => envelope(webhookEndpointFixture(7) + ['secret' => $secret]),
    ]);

    $rotated = drawhouse()->rotateWebhookSecret(7);

    expect($rotated->secret)->toBe($secret)
        ->and($rotated->endpoint->id)->toBe(7);
    expect(sentPendingRequest($mock, 0)->getUrl())->toEndWith('/v2/partners/webhooks/7/rotate-secret');
});

it('pages the delivery log with repeated status, createdAfter, a clamped limit and after', function () {
    $mock = MockClient::global([
        GetWebhookDeliveriesRequest::class => envelope(['items' => [webhookDeliveryFixture(501)], 'nextCursor' => '501']),
    ]);

    $page = drawhouse()->webhookDeliveriesPage(
        7,
        statuses: [WebhookDeliveryStatus::PENDING, WebhookDeliveryStatus::DEAD],
        createdAfter: CarbonImmutable::parse('2026-10-07T15:03:11+01:00'),
        limit: 9999,
        after: '500',
    );

    expect($page->items)->toHaveCount(1)
        ->and($page->items[0]->id)->toBe(501)
        ->and($page->nextCursor)->toBe('501')
        ->and($page->hasMore())->toBeTrue()
        ->and($page->count())->toBe(1);

    expect(sentPendingRequest($mock, 0)->getUrl())->toEndWith('/v2/partners/webhooks/7/deliveries')
        ->and(sentQueryString($mock, 0))->toBe('status=PENDING&status=DEAD&createdAfter=2026-10-07T14%3A03%3A11Z&limit=500&after=500');
});

it('sends only the default limit when no filter is given', function () {
    $mock = MockClient::global([
        GetWebhookDeliveriesRequest::class => envelope(['items' => [], 'nextCursor' => null]),
    ]);

    $page = drawhouse()->webhookDeliveriesPage(7);

    expect($page->items)->toBe([])
        ->and($page->hasMore())->toBeFalse()
        ->and(sentQueryString($mock, 0))->toBe('limit=100');
});

it('walks every delivery page lazily and stops on the empty page after a full one', function () {
    $calls = 0;
    MockClient::global([
        GetWebhookDeliveriesRequest::class => function () use (&$calls): MockResponse {
            $calls++;

            return match ($calls) {
                1 => envelope(['items' => [webhookDeliveryFixture(1), webhookDeliveryFixture(2)], 'nextCursor' => '2']),
                2 => envelope(['items' => [webhookDeliveryFixture(3), webhookDeliveryFixture(4)], 'nextCursor' => '4']),
                default => envelope(['items' => [], 'nextCursor' => '4']),
            };
        },
    ]);

    $all = drawhouse()->allWebhookDeliveries(7, limit: 2);

    expect($all)->toBeInstanceOf(LazyCollection::class)
        ->and($calls)->toBe(0);

    expect($all->map(fn (WebhookDelivery $d) => $d->id)->all())->toBe([1, 2, 3, 4])
        ->and($calls)->toBe(3);
});

it('takes only the pages it needs', function () {
    $calls = 0;
    MockClient::global([
        GetWebhookDeliveriesRequest::class => function () use (&$calls): MockResponse {
            $calls++;

            return envelope(['items' => [webhookDeliveryFixture($calls * 10)], 'nextCursor' => (string) ($calls * 10)]);
        },
    ]);

    $first = drawhouse()->allWebhookDeliveries(7, [WebhookDeliveryStatus::DEAD], limit: 1)->take(1)->all();

    expect($first)->toHaveCount(1)
        ->and($calls)->toBe(1);
});
