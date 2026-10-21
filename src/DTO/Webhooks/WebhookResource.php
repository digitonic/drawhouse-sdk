<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Webhooks;

use Digitonic\DrawHouse\Support\Parse;

/**
 * `resource` on a webhook event or delivery: what the event is about.
 */
final readonly class WebhookResource
{
    /**
     * @param  string|null  $type  `round`, `player`, `checkout`, `ticket`, `wallet` or `webhook`.
     * @param  string|null  $id  The round, player, checkout, raffle ticket, wallet or endpoint id. A string on the wire; see intId().
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $type,
        public ?string $id,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: Parse::nonEmptyString($data['type'] ?? null),
            id: Parse::nonEmptyString($data['id'] ?? null),
            raw: $data,
        );
    }

    /**
     * The id as an integer, matching the ids everywhere else in the API.
     */
    public function intId(): ?int
    {
        return Parse::int($this->id);
    }
}
