<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Webhooks;

use Digitonic\DrawHouse\Support\Parse;

/**
 * One of YOUR active editions of the game, on a `round.state_changed` event.
 */
final readonly class WebhookEdition
{
    /**
     * @param  string|null  $url  Player-facing prize-draw page on your site, built from your website
     *                            domain and the edition slug. Null when no domain is configured.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $id,
        public ?string $slug,
        public ?string $name,
        public ?bool $isDefault,
        public ?string $url,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Parse::int($data['id'] ?? null),
            slug: Parse::nonEmptyString($data['slug'] ?? null),
            name: Parse::nonEmptyString($data['name'] ?? null),
            isDefault: Parse::bool($data['isDefault'] ?? null),
            url: Parse::nonEmptyString($data['url'] ?? null),
            raw: $data,
        );
    }
}
