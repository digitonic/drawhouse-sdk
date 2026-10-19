<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Webhooks;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Digitonic\DrawHouse\DTO\Webhooks\WebhookEvent;
use Digitonic\DrawHouse\Exceptions\WebhookVerificationException;
use InvalidArgumentException;

/**
 * Verifies the signature on an incoming DrawHouse webhook request and parses
 * the body into a typed WebhookEvent. Stateless: it stores nothing, does not
 * deduplicate and dispatches nothing.
 *
 * Every request carries
 *
 *   X-DrawHouse-Signature: t=<unix seconds>,v1=<hex HMAC-SHA256(secret, t + "." + body)>
 *
 * computed over the RAW request body, so pass the body exactly as received,
 * before any JSON decoding.
 *
 *   $verifier = new WebhookVerifier([$secret, $previousSecret]);
 *   $event = $verifier->event($request->getContent(), $request->header('X-DrawHouse-Signature'));
 *
 * Pass several secrets while rotating: any one of them verifies, so both the
 * old and the new secret work until the switch-over finishes.
 *
 * The timestamp must be within `$tolerance` seconds of now in either
 * direction (default 300, i.e. 5 minutes); null disables the check.
 */
final class WebhookVerifier
{
    public const SIGNATURE_HEADER = 'X-DrawHouse-Signature';

    public const EVENT_HEADER = 'X-DrawHouse-Event';

    public const DELIVERY_HEADER = 'X-DrawHouse-Delivery';

    public const DEFAULT_TOLERANCE = 300;

    /**
     * The only signature scheme DrawHouse send.
     */
    private const SCHEME = 'v1';

    /**
     * @var list<string>
     */
    private readonly array $secrets;

    /**
     * @param  string|list<string>  $secrets  Current secret first. Blank entries are ignored.
     * @param  int|null  $tolerance  Seconds either side of now; null disables the timestamp check.
     *
     * @throws InvalidArgumentException when no secret is given or the tolerance is negative
     */
    public function __construct(
        #[\SensitiveParameter] string|array $secrets,
        private readonly ?int $tolerance = self::DEFAULT_TOLERANCE,
    ) {
        $secrets = array_values(array_filter(
            array_map(static fn (mixed $s): string => is_string($s) ? trim($s) : '', (array) $secrets),
            static fn (string $s): bool => $s !== '',
        ));

        if ($secrets === []) {
            throw new InvalidArgumentException('DrawHouse webhook verifier needs at least one signing secret (DRAWHOUSE_WEBHOOK_SECRET)');
        }

        if ($tolerance !== null && $tolerance < 0) {
            throw new InvalidArgumentException('DrawHouse webhook signature tolerance must be zero or more seconds');
        }

        $this->secrets = $secrets;
    }

    /**
     * Verify the request and return its body as a typed event.
     *
     * @throws WebhookVerificationException
     */
    public function event(string $payload, ?string $signatureHeader, ?CarbonInterface $now = null): WebhookEvent
    {
        $this->verify($payload, $signatureHeader, $now);

        try {
            return WebhookEvent::fromJson($payload);
        } catch (InvalidArgumentException $e) {
            throw WebhookVerificationException::malformedPayload($e->getMessage(), $e);
        }
    }

    /**
     * Check the signature header against the raw body.
     *
     * Returns the index (in the order given to the constructor) of the secret
     * that matched, so during a rotation you can tell when deliveries have
     * switched to the new secret and the old one can be dropped.
     *
     * @throws WebhookVerificationException
     */
    public function verify(string $payload, ?string $signatureHeader, ?CarbonInterface $now = null): int
    {
        [$timestamp, $signatures] = self::parseHeader($signatureHeader);

        if ($this->tolerance !== null) {
            $current = ($now ?? CarbonImmutable::now())->getTimestamp();

            if (abs($current - $timestamp) > $this->tolerance) {
                throw WebhookVerificationException::timestampOutsideTolerance($timestamp, $current, $this->tolerance);
            }
        }

        foreach ($this->secrets as $index => $secret) {
            $expected = self::compute($payload, $secret, $timestamp);

            foreach ($signatures as $signature) {
                if (hash_equals($expected, $signature)) {
                    return $index;
                }
            }
        }

        throw WebhookVerificationException::signatureMismatch(count($this->secrets));
    }

    /**
     * Whether the request verifies, without saying why not.
     */
    public function isValid(string $payload, ?string $signatureHeader, ?CarbonInterface $now = null): bool
    {
        try {
            $this->verify($payload, $signatureHeader, $now);

            return true;
        } catch (WebhookVerificationException) {
            return false;
        }
    }

    /**
     * Build the header DrawHouse would send for `$payload`. For tests in the
     * consuming application: sign a fixture and post it at your receiver.
     */
    public static function signatureHeader(string $payload, #[\SensitiveParameter] string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= CarbonImmutable::now()->getTimestamp();

        return sprintf('t=%d,%s=%s', $timestamp, self::SCHEME, self::compute($payload, $secret, $timestamp));
    }

    private static function compute(string $payload, #[\SensitiveParameter] string $secret, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }

    /**
     * `t=<unix>,v1=<hex>`. More than one `v1` is accepted (any may match);
     * other schemes are ignored.
     *
     * @return array{int, list<string>}
     *
     * @throws WebhookVerificationException
     */
    private static function parseHeader(?string $header): array
    {
        if ($header === null || trim($header) === '') {
            throw WebhookVerificationException::missingSignature();
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);

            if (count($pair) !== 2) {
                continue;
            }

            [$key, $value] = [trim($pair[0]), trim($pair[1])];

            if ($key === 't') {
                if (! ctype_digit($value)) {
                    throw WebhookVerificationException::malformedSignature('t is not a unix timestamp');
                }

                $timestamp = (int) $value;
            } elseif ($key === self::SCHEME && $value !== '') {
                $signatures[] = strtolower($value);
            }
        }

        if ($timestamp === null) {
            throw WebhookVerificationException::malformedSignature('no t= timestamp');
        }

        if ($signatures === []) {
            throw WebhookVerificationException::malformedSignature('no v1= signature');
        }

        return [$timestamp, $signatures];
    }
}
