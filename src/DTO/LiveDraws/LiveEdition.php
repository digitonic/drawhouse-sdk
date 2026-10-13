<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\LiveDraws;

use Digitonic\DrawHouse\Support\Parse;

/**
 * `edition` on a live draw: YOUR branded edition of the game.
 * `url` is the deep link to your prize-draw page; send traffic there.
 */
final readonly class LiveEdition
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $id,
        public ?string $name,
        public ?string $slug,
        public ?string $description,
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
            name: Parse::string($data['name'] ?? null),
            slug: Parse::string($data['slug'] ?? null),
            description: Parse::nonEmptyString($data['description'] ?? null),
            url: Parse::nonEmptyString($data['url'] ?? null),
            raw: $data,
        );
    }
}
