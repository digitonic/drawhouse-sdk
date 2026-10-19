<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Webhooks;

use Digitonic\DrawHouse\Support\Parse;

/**
 * The response to registering an endpoint or rotating its secret: the
 * endpoint plus its signing `secret`. These are the ONLY two responses that
 * carry the secret, so store it server-side straight away; it cannot be read
 * back later.
 *
 * The secret is `whsec_` plus 64 hex characters. Sign and verify with the
 * whole string, prefix included. It is left out of var_dump() and print_r()
 * output; read `$secret` directly.
 */
final readonly class WebhookEndpointWithSecret
{
    /**
     * @param  array<string, mixed>  $raw  Includes the secret.
     */
    public function __construct(
        public WebhookEndpoint $endpoint,
        #[\SensitiveParameter] public string $secret,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(#[\SensitiveParameter] array $data): self
    {
        $endpoint = $data;
        unset($endpoint['secret']);

        return new self(
            endpoint: WebhookEndpoint::fromArray($endpoint),
            secret: Parse::string($data['secret'] ?? null) ?? '',
            raw: $data,
        );
    }

    public function hasSecret(): bool
    {
        return $this->secret !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'endpoint' => $this->endpoint,
            'secret' => $this->secret === '' ? '' : '[redacted]',
        ];
    }
}
