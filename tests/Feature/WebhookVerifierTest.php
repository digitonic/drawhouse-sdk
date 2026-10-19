<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\DTO\Webhooks\PingData;
use Digitonic\DrawHouse\DTO\Webhooks\PlayerData;
use Digitonic\DrawHouse\DTO\Webhooks\RoundStateChangedData;
use Digitonic\DrawHouse\DTO\Webhooks\WebhookEvent;
use Digitonic\DrawHouse\Enums\AccountStatus;
use Digitonic\DrawHouse\Enums\PlayerChange;
use Digitonic\DrawHouse\Enums\RoundState;
use Digitonic\DrawHouse\Enums\WebhookEventType;
use Digitonic\DrawHouse\Exceptions\WebhookVerificationException;
use Digitonic\DrawHouse\Webhooks\WebhookVerifier;

const SIGNED_AT = 1791000000;

function signedAt(): CarbonImmutable
{
    return CarbonImmutable::createFromTimestampUTC(SIGNED_AT);
}

/**
 * The header for `$body`, computed independently of WebhookVerifier.
 */
function drawhouseSignature(string $body, string $secret, int $timestamp = SIGNED_AT): string
{
    return sprintf('t=%d,v1=%s', $timestamp, hash_hmac('sha256', $timestamp.'.'.$body, $secret));
}

function verificationReason(Closure $call): ?string
{
    try {
        $call();
    } catch (WebhookVerificationException $e) {
        return $e->reason;
    }

    return null;
}

// ---------------------------------------------------------------------------
// Signature
// ---------------------------------------------------------------------------

it('verifies a signature computed as HMAC-SHA256(secret, t + "." + body)', function () {
    $body = webhookBody(roundStateChangedEventFixture());
    $verifier = new WebhookVerifier(webhookSecret());

    expect($verifier->verify($body, drawhouseSignature($body, webhookSecret()), signedAt()))->toBe(0)
        ->and($verifier->isValid($body, drawhouseSignature($body, webhookSecret()), signedAt()))->toBeTrue();
});

it('builds the same header DrawHouse send, for tests in the consuming app', function () {
    $body = webhookBody(pingEventFixture());

    expect(WebhookVerifier::signatureHeader($body, webhookSecret(), SIGNED_AT))->toBe(drawhouseSignature($body, webhookSecret()));
});

it('signs with the whole secret, whsec_ prefix included', function () {
    $body = webhookBody(pingEventFixture());
    $withoutPrefix = substr(webhookSecret(), strlen('whsec_'));

    expect((new WebhookVerifier(webhookSecret()))->isValid($body, drawhouseSignature($body, $withoutPrefix), signedAt()))->toBeFalse();
});

it('rejects a tampered body', function () {
    $body = webhookBody(roundStateChangedEventFixture());
    $header = drawhouseSignature($body, webhookSecret());
    $tampered = str_replace('"toState":"RESULTED"', '"toState":"CANCELLED"', $body);

    expect($tampered)->not->toBe($body)
        ->and(verificationReason(fn () => (new WebhookVerifier(webhookSecret()))->verify($tampered, $header, signedAt())))
        ->toBe(WebhookVerificationException::SIGNATURE_MISMATCH);
});

it('rejects a body re-encoded after decoding, because the signature covers the raw bytes', function () {
    $body = webhookBody(roundStateChangedEventFixture());
    $header = drawhouseSignature($body, webhookSecret());
    $reencoded = json_encode(json_decode($body, true), JSON_PRETTY_PRINT);

    expect((new WebhookVerifier(webhookSecret()))->isValid((string) $reencoded, $header, signedAt()))->toBeFalse();
});

it('rejects a tampered timestamp', function () {
    $body = webhookBody(pingEventFixture());
    $header = str_replace('t='.SIGNED_AT, 't='.(SIGNED_AT + 1), drawhouseSignature($body, webhookSecret()));

    expect(verificationReason(fn () => (new WebhookVerifier(webhookSecret()))->verify($body, $header, signedAt())))
        ->toBe(WebhookVerificationException::SIGNATURE_MISMATCH);
});

it('rejects a signature made with another secret', function () {
    $body = webhookBody(pingEventFixture());

    expect(verificationReason(fn () => (new WebhookVerifier(webhookSecret()))->verify($body, drawhouseSignature($body, webhookSecret('attacker')), signedAt())))
        ->toBe(WebhookVerificationException::SIGNATURE_MISMATCH);
});

it('rejects a timestamp older or newer than the tolerance, and accepts one at the edge', function () {
    $body = webhookBody(pingEventFixture());
    $header = drawhouseSignature($body, webhookSecret());
    $verifier = new WebhookVerifier(webhookSecret());

    expect($verifier->isValid($body, $header, signedAt()->addSeconds(300)))->toBeTrue()
        ->and($verifier->isValid($body, $header, signedAt()->subSeconds(300)))->toBeTrue();

    $expired = null;
    try {
        $verifier->verify($body, $header, signedAt()->addSeconds(301));
    } catch (WebhookVerificationException $e) {
        $expired = $e;
    }

    expect($expired?->reason)->toBe(WebhookVerificationException::TIMESTAMP_OUTSIDE_TOLERANCE)
        ->and($expired?->isExpired())->toBeTrue()
        ->and($expired?->getMessage())->toContain('301 seconds');

    expect(verificationReason(fn () => $verifier->verify($body, $header, signedAt()->subSeconds(301))))
        ->toBe(WebhookVerificationException::TIMESTAMP_OUTSIDE_TOLERANCE);
});

it('checks the timestamp before the signature, so a stale replay never reaches the HMAC', function () {
    $body = webhookBody(pingEventFixture());

    expect(verificationReason(fn () => (new WebhookVerifier(webhookSecret()))->verify($body, drawhouseSignature($body, webhookSecret('other')), signedAt()->addHour())))
        ->toBe(WebhookVerificationException::TIMESTAMP_OUTSIDE_TOLERANCE);
});

it('uses a configurable tolerance, and none at all when it is null', function () {
    $body = webhookBody(pingEventFixture());
    $header = drawhouseSignature($body, webhookSecret());

    expect((new WebhookVerifier(webhookSecret(), tolerance: 60))->isValid($body, $header, signedAt()->addSeconds(61)))->toBeFalse()
        ->and((new WebhookVerifier(webhookSecret(), tolerance: null))->isValid($body, $header, signedAt()->addYear()))->toBeTrue();
});

it('uses the current time when none is passed', function () {
    $body = webhookBody(pingEventFixture());
    $verifier = new WebhookVerifier(webhookSecret());

    expect($verifier->isValid($body, WebhookVerifier::signatureHeader($body, webhookSecret())))->toBeTrue()
        ->and($verifier->isValid($body, drawhouseSignature($body, webhookSecret())))->toBeFalse();
});

it('accepts the old and the new secret during a rotation and says which one matched', function () {
    $body = webhookBody(roundStateChangedEventFixture());
    $verifier = new WebhookVerifier([webhookSecret('new'), webhookSecret('old')]);

    expect($verifier->verify($body, drawhouseSignature($body, webhookSecret('new')), signedAt()))->toBe(0)
        ->and($verifier->verify($body, drawhouseSignature($body, webhookSecret('old')), signedAt()))->toBe(1);

    expect(verificationReason(fn () => (new WebhookVerifier(webhookSecret('new')))->verify($body, drawhouseSignature($body, webhookSecret('old')), signedAt())))
        ->toBe(WebhookVerificationException::SIGNATURE_MISMATCH);
});

it('ignores blank secrets, so an unset previous secret is harmless', function () {
    $body = webhookBody(pingEventFixture());
    $verifier = new WebhookVerifier(['', webhookSecret(), '  ']);

    expect($verifier->verify($body, drawhouseSignature($body, webhookSecret()), signedAt()))->toBe(0);
});

it('refuses to be built without a secret or with a negative tolerance', function () {
    expect(fn () => new WebhookVerifier([]))->toThrow(InvalidArgumentException::class, 'at least one signing secret')
        ->and(fn () => new WebhookVerifier(['', ' ']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new WebhookVerifier(webhookSecret(), tolerance: -1))->toThrow(InvalidArgumentException::class);
});

it('accepts any one of several v1 signatures, ignores other schemes, spaces and upper-case hex', function () {
    $body = webhookBody(pingEventFixture());
    $good = hash_hmac('sha256', SIGNED_AT.'.'.$body, webhookSecret());
    $header = sprintf('t=%d, v0=deadbeef, v1=%s, v1=%s', SIGNED_AT, str_repeat('0', 64), strtoupper($good));

    expect((new WebhookVerifier(webhookSecret()))->isValid($body, $header, signedAt()))->toBeTrue();
});

it('names what is wrong with a missing or malformed header', function (?string $header, string $reason) {
    $body = webhookBody(pingEventFixture());

    expect(verificationReason(fn () => (new WebhookVerifier(webhookSecret()))->verify($body, $header, signedAt())))->toBe($reason);
})->with([
    'missing' => [null, WebhookVerificationException::MISSING_SIGNATURE],
    'empty' => ['  ', WebhookVerificationException::MISSING_SIGNATURE],
    'no timestamp' => ['v1=abc', WebhookVerificationException::MALFORMED_SIGNATURE],
    'no signature' => ['t='.SIGNED_AT, WebhookVerificationException::MALFORMED_SIGNATURE],
    'empty signature' => ['t='.SIGNED_AT.',v1=', WebhookVerificationException::MALFORMED_SIGNATURE],
    'non-numeric timestamp' => ['t=yesterday,v1=abc', WebhookVerificationException::MALFORMED_SIGNATURE],
    'negative timestamp' => ['t=-5,v1=abc', WebhookVerificationException::MALFORMED_SIGNATURE],
    'garbage' => ['sha256=abc', WebhookVerificationException::MALFORMED_SIGNATURE],
]);

it('never puts a secret or the expected signature in the exception message', function () {
    $body = webhookBody(pingEventFixture());
    $expected = hash_hmac('sha256', SIGNED_AT.'.'.$body, webhookSecret());

    try {
        (new WebhookVerifier(webhookSecret()))->verify($body, drawhouseSignature($body, webhookSecret('other')), signedAt());
        $this->fail('Expected WebhookVerificationException');
    } catch (WebhookVerificationException $e) {
        expect($e->getMessage())->not->toContain(webhookSecret())
            ->and($e->getMessage())->not->toContain($expected)
            ->and($e->getMessage())->toContain('1 configured secret');
    }
});

// ---------------------------------------------------------------------------
// Verify + parse in one call
// ---------------------------------------------------------------------------

it('verifies a round.state_changed request and returns the typed event', function () {
    $body = webhookBody(roundStateChangedEventFixture());

    $event = (new WebhookVerifier(webhookSecret()))->event($body, drawhouseSignature($body, webhookSecret()), signedAt());

    expect($event->id)->toBe(1234)
        ->and($event->type)->toBe(WebhookEventType::ROUND_STATE_CHANGED)
        ->and($event->is(WebhookEventType::ROUND_STATE_CHANGED))->toBeTrue()
        ->and($event->typeName)->toBe('round.state_changed')
        ->and($event->occurredAt?->toIso8601ZuluString())->toBe('2026-10-07T14:03:11Z')
        ->and($event->affiliateId)->toBe(20)
        ->and($event->resource->type)->toBe('round')
        ->and($event->resource->intId())->toBe(88)
        ->and($event->data)->toBeInstanceOf(RoundStateChangedData::class)
        ->and($event->player())->toBeNull()
        ->and($event->ping())->toBeNull();

    $round = $event->roundStateChanged();
    expect($round?->gameId)->toBe(50)
        ->and($round?->gameName)->toBe('October Prize Draw')
        ->and($round?->roundId)->toBe(88)
        ->and($round?->roundNumber)->toBe(3)
        ->and($round?->fromState)->toBe(RoundState::OPEN)
        ->and($round?->toState)->toBe(RoundState::RESULTED)
        ->and($round?->isResulted())->toBeTrue()
        ->and($round?->isOpened())->toBeFalse()
        ->and($round?->editions)->toHaveCount(1)
        ->and($round?->editions[0]->id)->toBe(9)
        ->and($round?->editions[0]->slug)->toBe('acme-october-draw')
        ->and($round?->editions[0]->name)->toBe('Acme October Draw')
        ->and($round?->editions[0]->isDefault)->toBeTrue()
        ->and($round?->editions[0]->url)->toBe('https://acme.example/prize-draw/acme-october-draw')
        ->and($round?->defaultEdition()?->id)->toBe(9);
});

it('reads an empty edition url as null and an empty editions list as none', function () {
    $noDomain = RoundStateChangedData::fromArray(roundStateChangedEventFixture([
        'editions' => [['id' => 9, 'slug' => 'x', 'name' => 'X', 'isDefault' => false, 'url' => '']],
    ])['data']);
    $none = RoundStateChangedData::fromArray(roundStateChangedEventFixture(['editions' => [], 'fromState' => 'READY', 'toState' => 'OPEN'])['data']);

    expect($noDomain->editions[0]->url)->toBeNull()
        ->and($noDomain->defaultEdition()?->id)->toBe(9)
        ->and($none->editions)->toBe([])
        ->and($none->defaultEdition())->toBeNull()
        ->and($none->isOpened())->toBeTrue();
});

it('parses player.updated with the existing Suppression, Consents and AccountStatus', function () {
    $body = webhookBody(playerEventFixture('player.updated', ['changes' => ['status', 'consent', 'status', 'unknown']]));

    $event = (new WebhookVerifier(webhookSecret()))->event($body, drawhouseSignature($body, webhookSecret()), signedAt());
    $player = $event->player();

    expect($event->type)->toBe(WebhookEventType::PLAYER_UPDATED)
        ->and($player)->toBeInstanceOf(PlayerData::class)
        ->and($player?->playerId)->toBe(18342)
        ->and($player?->registeredAt?->toIso8601ZuluString())->toBe('2026-10-07T14:59:40Z')
        ->and($player?->accountStatus)->toBe(AccountStatus::SELF_EXCLUDED)
        ->and($player?->statusChangedAt?->toIso8601ZuluString())->toBe('2026-10-08T09:12:03Z')
        ->and($player?->marketable)->toBeFalse()
        ->and($player?->changes)->toBe([PlayerChange::STATUS, PlayerChange::CONSENT])
        ->and($player?->hasChange(PlayerChange::CONSENT))->toBeTrue()
        ->and($player?->needsProfileRefetch())->toBeFalse()
        ->and($player?->suppression->selfExcluded)->toBeTrue()
        ->and($player?->suppression->selfExclusionUntil?->toIso8601ZuluString())->toBe('2027-04-08T09:12:03Z')
        ->and($player?->suppression->isKycVerified())->toBeTrue()
        ->and($player?->consents->email->isGranted())->toBeTrue()
        ->and($player?->consents->sms->isGranted())->toBeFalse()
        ->and($player?->consents->partnerOffers->isGranted())->toBeTrue()
        ->and($player?->consents->partnerOffers->recordedAt)->toBeNull();
});

it('parses player.registered with no changes, and flags a profile change for a re-fetch', function () {
    $registered = WebhookEvent::fromArray(playerEventFixture('player.registered', ['accountStatus' => 'ACTIVE', 'marketable' => true]));
    $profile = WebhookEvent::fromArray(playerEventFixture('player.updated', ['changes' => ['profile']]));

    expect($registered->type)->toBe(WebhookEventType::PLAYER_REGISTERED)
        ->and($registered->player()?->changes)->toBe([])
        ->and($registered->player()?->accountStatus)->toBe(AccountStatus::ACTIVE)
        ->and($profile->player()?->needsProfileRefetch())->toBeTrue();
});

it('re-evaluates a time-out on a player event, since its expiry sends no event', function () {
    $timedOut = PlayerData::fromArray(playerEventFixture('player.updated', [
        'accountStatus' => 'TIMED_OUT',
        'marketable' => true,
        'suppression' => ['selfExcluded' => false, 'selfExclusionUntil' => null, 'timedOutUntil' => '2026-10-10T00:00:00Z'],
    ])['data']);

    expect($timedOut->isTimedOutAt(CarbonImmutable::parse('2026-10-09T00:00:00Z')))->toBeTrue()
        ->and($timedOut->effectiveStatusAt(CarbonImmutable::parse('2026-10-09T00:00:00Z')))->toBe(AccountStatus::TIMED_OUT)
        ->and($timedOut->effectiveStatusAt(CarbonImmutable::parse('2026-10-11T00:00:00Z')))->toBe(AccountStatus::ACTIVE)
        ->and($timedOut->isContactableAt(CarbonImmutable::parse('2026-10-09T00:00:00Z')))->toBeFalse()
        ->and($timedOut->isContactableAt(CarbonImmutable::parse('2026-10-11T00:00:00Z')))->toBeTrue();

    $excluded = PlayerData::fromArray(playerEventFixture()['data']);
    expect($excluded->effectiveStatusAt(CarbonImmutable::parse('2030-01-01')))->toBe(AccountStatus::SELF_EXCLUDED);
});

it('parses a ping', function () {
    $body = webhookBody(pingEventFixture(7));

    $event = (new WebhookVerifier(webhookSecret()))->event($body, drawhouseSignature($body, webhookSecret()), signedAt());

    expect($event->isPing())->toBeTrue()
        ->and($event->data)->toBeInstanceOf(PingData::class)
        ->and($event->ping()?->endpointId)->toBe(7)
        ->and($event->resource->type)->toBe('webhook');
});

it('keeps an unknown event type as raw data instead of failing', function () {
    $event = WebhookEvent::fromArray([
        'id' => 9, 'type' => 'draw.something_new', 'occurredAt' => '2026-10-08T10:00:00Z', 'affiliateId' => 20,
        'resource' => ['type' => 'draw', 'id' => '5'], 'data' => ['foo' => 'bar'],
    ]);

    expect($event->type)->toBeNull()
        ->and($event->typeName)->toBe('draw.something_new')
        ->and($event->data)->toBe(['foo' => 'bar'])
        ->and($event->roundStateChanged())->toBeNull();
});

it('rejects a correctly signed body that is not a JSON object', function (string $body) {
    $verifier = new WebhookVerifier(webhookSecret());

    expect(verificationReason(fn () => $verifier->event($body, drawhouseSignature($body, webhookSecret()), signedAt())))
        ->toBe(WebhookVerificationException::MALFORMED_PAYLOAD);
})->with([
    'not json' => ['not json'],
    'a list' => ['[1,2,3]'],
    'a string' => ['"ping"'],
]);

it('never parses the body when the signature is wrong', function () {
    expect(verificationReason(fn () => (new WebhookVerifier(webhookSecret()))->event('not json', drawhouseSignature('not json', webhookSecret('other')), signedAt())))
        ->toBe(WebhookVerificationException::SIGNATURE_MISMATCH);
});

// ---------------------------------------------------------------------------
// Container
// ---------------------------------------------------------------------------

it('builds the verifier from config, with the previous secret for rotations', function () {
    config()->set('drawhouse.webhooks.secret', webhookSecret('new'));
    config()->set('drawhouse.webhooks.previous_secret', webhookSecret('old'));
    config()->set('drawhouse.webhooks.tolerance', 60);

    $verifier = app(WebhookVerifier::class);
    $body = webhookBody(pingEventFixture());

    expect($verifier->verify($body, drawhouseSignature($body, webhookSecret('old')), signedAt()))->toBe(1)
        ->and($verifier->isValid($body, drawhouseSignature($body, webhookSecret('new')), signedAt()->addSeconds(61)))->toBeFalse();
});

it('disables the timestamp check when the configured tolerance is null', function () {
    config()->set('drawhouse.webhooks.secret', webhookSecret());
    config()->set('drawhouse.webhooks.tolerance', null);

    $body = webhookBody(pingEventFixture());

    expect(app(WebhookVerifier::class)->isValid($body, drawhouseSignature($body, webhookSecret()), signedAt()->addYear()))->toBeTrue();
});

it('needs no webhook secret until the verifier is resolved', function () {
    config()->set('drawhouse.webhooks.secret', null);

    expect(app('drawhouse'))->not->toBeNull()
        ->and(fn () => app(WebhookVerifier::class))->toThrow(InvalidArgumentException::class, 'DRAWHOUSE_WEBHOOK_SECRET');
});

/**
 * Evaluate config/drawhouse.php with DRAWHOUSE_WEBHOOK_TOLERANCE set as it
 * would be in a .env file, and load its webhooks section.
 */
function webhookConfigFromEnv(?string $tolerance): void
{
    $key = 'DRAWHOUSE_WEBHOOK_TOLERANCE';

    if ($tolerance !== null) {
        $_SERVER[$key] = $_ENV[$key] = $tolerance;
    }

    try {
        $config = require __DIR__.'/../../config/drawhouse.php';
    } finally {
        unset($_SERVER[$key], $_ENV[$key]);
    }

    config()->set('drawhouse.webhooks', ['secret' => webhookSecret()] + $config['webhooks']);
}

it('reads the tolerance from the environment, keeping null as "no check"', function (?string $env, ?int $expected) {
    webhookConfigFromEnv($env);

    $verifier = app(WebhookVerifier::class);
    $body = webhookBody(pingEventFixture());
    $header = drawhouseSignature($body, webhookSecret());

    if ($expected === null) {
        expect($verifier->isValid($body, $header, signedAt()->addYear()))->toBeTrue();

        return;
    }

    expect($verifier->isValid($body, $header, signedAt()->addSeconds($expected)))->toBeTrue()
        ->and($verifier->isValid($body, $header, signedAt()->addSeconds($expected + 1)))->toBeFalse();
})->with([
    'unset' => [null, 300],
    'blank' => ['', 300],
    'seconds' => ['60', 60],
    'null' => ['null', null],
    '(null)' => ['(null)', null],
]);

it('refuses a tolerance that is not a whole number of seconds', function (string $env) {
    webhookConfigFromEnv($env);

    expect(fn () => app(WebhookVerifier::class))->toThrow(InvalidArgumentException::class, 'whole number of seconds');
})->with(['five minutes', '-1', '1.5']);
