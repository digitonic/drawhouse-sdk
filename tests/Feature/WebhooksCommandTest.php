<?php

declare(strict_types=1);

use Digitonic\DrawHouse\Http\Requests\CreateWebhookRequest;
use Digitonic\DrawHouse\Http\Requests\GetWebhookDeliveriesRequest;
use Digitonic\DrawHouse\Http\Requests\ListWebhooksRequest;
use Digitonic\DrawHouse\Http\Requests\RotateWebhookSecretRequest;
use Digitonic\DrawHouse\Http\Requests\TestWebhookRequest;
use Saloon\Http\Faking\MockClient;

beforeEach(fn () => seedTokens());

it('lists endpoints, flagging one DrawHouse switched off', function () {
    MockClient::global([
        ListWebhooksRequest::class => envelope(['items' => [
            webhookEndpointFixture(7),
            webhookEndpointFixture(8, ['events' => [], 'active' => false, 'consecutiveFailures' => 50]),
        ]]),
    ]);

    // One expectation per table row: each output line satisfies only one.
    $this->artisan('drawhouse:webhooks')
        ->expectsOutputToContain('| 7  | https://hooks.example.com/drawhouse | round.state_changed | yes')
        ->expectsOutputToContain('| 8  | https://hooks.example.com/drawhouse | all events          | no (auto-disabled)')
        ->assertSuccessful();
});

it('says when no endpoints are registered', function () {
    MockClient::global([ListWebhooksRequest::class => envelope(['items' => []])]);

    $this->artisan('drawhouse:webhooks', ['action' => 'list'])
        ->expectsOutputToContain('No webhook endpoints registered.')
        ->assertSuccessful();
});

it('registers an endpoint and prints the secret once', function () {
    $mock = MockClient::global([
        CreateWebhookRequest::class => envelope(webhookEndpointFixture(7, ['events' => ['round.state_changed', 'player.updated']]) + ['secret' => webhookSecret()], 201),
    ]);

    $this->artisan('drawhouse:webhooks', [
        'action' => 'register',
        '--url' => 'https://hooks.example.com/drawhouse',
        '--event' => ['round.state_changed', 'player.updated'],
        '--description' => 'test receiver',
    ])
        ->expectsOutputToContain('Registered endpoint 7')
        ->expectsOutputToContain('shown once')
        ->expectsOutput(webhookSecret())
        ->assertSuccessful();

    expect(sentJsonBody($mock, 0))->toBe([
        'url' => 'https://hooks.example.com/drawhouse',
        'events' => ['round.state_changed', 'player.updated'],
        'active' => true,
        'description' => 'test receiver',
    ]);
});

it('refuses an unknown event type or a missing url without calling DrawHouse', function (array $args, string $message) {
    $mock = MockClient::global([]);

    $this->artisan('drawhouse:webhooks', ['action' => 'register'] + $args)
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect($mock->getRecordedResponses())->toBe([]);
})->with([
    'unknown event' => [['--url' => 'https://hooks.example.com/', '--event' => ['draw.unknown']], 'Unknown event type draw.unknown'],
    'ping' => [['--url' => 'https://hooks.example.com/', '--event' => ['webhook.ping']], 'cannot be subscribed to'],
    'no url' => [[], '--url'],
    'http url' => [['--url' => 'http://hooks.example.com/'], 'https://'],
]);

it('queues a ping', function () {
    MockClient::global([TestWebhookRequest::class => envelope(pingEventFixture(7), 202)]);

    $this->artisan('drawhouse:webhooks', ['action' => 'ping', 'id' => '7'])
        ->expectsOutputToContain('Queued webhook.ping event 3001 to endpoint 7')
        ->assertSuccessful();
});

it('needs an endpoint id for ping, rotate and deliveries', function (string $action) {
    $this->artisan('drawhouse:webhooks', ['action' => $action])
        ->expectsOutputToContain('Pass the endpoint id')
        ->assertFailed();
})->with(['ping', 'rotate', 'deliveries']);

it('rotates a secret after confirmation and prints the new one', function () {
    MockClient::global([
        RotateWebhookSecretRequest::class => envelope(webhookEndpointFixture(7) + ['secret' => webhookSecret('rotated')]),
    ]);

    $this->artisan('drawhouse:webhooks', ['action' => 'rotate', 'id' => '7'])
        ->expectsConfirmation('Rotate the signing secret of endpoint 7? Deliveries switch to the new secret straight away.', 'yes')
        ->expectsOutputToContain('Keep accepting the old secret')
        ->expectsOutput(webhookSecret('rotated'))
        ->assertSuccessful();
});

it('does not rotate when the confirmation is declined', function () {
    $mock = MockClient::global([]);

    $this->artisan('drawhouse:webhooks', ['action' => 'rotate', 'id' => '7'])
        ->expectsConfirmation('Rotate the signing secret of endpoint 7? Deliveries switch to the new secret straight away.', 'no')
        ->assertSuccessful();

    expect($mock->getRecordedResponses())->toBe([]);
});

it('shows the delivery log, filtered by status', function () {
    $mock = MockClient::global([
        GetWebhookDeliveriesRequest::class => envelope(['items' => [webhookDeliveryFixture(501, ['status' => 'DEAD'])], 'nextCursor' => null]),
    ]);

    $this->artisan('drawhouse:webhooks', ['action' => 'deliveries', 'id' => '7', '--status' => ['dead']])
        ->expectsOutputToContain('| DEAD   | 2       | 500       | http 500')
        ->assertSuccessful();

    expect(sentQueryString($mock, 0))->toBe('status=DEAD&limit=500');
});

it('reports a partner that is not LIVE', function () {
    MockClient::global([ListWebhooksRequest::class => partnerNotLiveResponse('ONBOARDING')]);

    $this->artisan('drawhouse:webhooks')
        ->expectsOutputToContain('need a LIVE partner; current status is ONBOARDING')
        ->assertFailed();
});

it('rejects an unknown action', function () {
    $this->artisan('drawhouse:webhooks', ['action' => 'explode'])
        ->expectsOutputToContain('Unknown action')
        ->assertFailed();
});
