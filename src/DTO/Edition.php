<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO;

use Digitonic\DrawHouse\Support\Parse;

/**
 * One brand's edition of a game. `Game.editions` lists every active edition
 * across ALL partners; `isMine` marks the one belonging to the requesting
 * affiliateId.
 */
final readonly class Edition
{
    /**
     * @param  int|null  $affiliateId  The partner that owns the edition.
     * @param  string|null  $name  Edition name as shown to that brand's players.
     * @param  string|null  $slug  Used in the prize-draw link.
     * @param  bool|null  $isDefault  The owning partner's default edition of the game.
     * @param  bool|null  $isMine  Belongs to the requesting affiliateId.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $id,
        public ?int $affiliateId,
        public ?string $name,
        public ?string $slug,
        public ?bool $isDefault,
        public ?bool $isMine,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Parse::int($data['id'] ?? null),
            affiliateId: Parse::int($data['affiliateId'] ?? null),
            name: Parse::string($data['name'] ?? null),
            slug: Parse::string($data['slug'] ?? null),
            isDefault: Parse::bool($data['isDefault'] ?? null),
            isMine: Parse::bool($data['isMine'] ?? null),
            raw: $data,
        );
    }
}
