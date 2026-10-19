<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Webhooks;

use Digitonic\DrawHouse\Support\Parse;

/**
 * `data` of a `webhook.ping` event: `{ endpointId, message: "ping" }`.
 */
final readonly class PingData
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $endpointId,
        public ?string $message,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            endpointId: Parse::int($data['endpointId'] ?? null),
            message: Parse::nonEmptyString($data['message'] ?? null),
            raw: $data,
        );
    }
}
