<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\LiveDraws;

use Digitonic\DrawHouse\Support\Parse;

/**
 * One image surface on a live draw: `{ base, variants }`. Surfaces are
 * whatever was configured (`hero`, `lobby-card`, and so on); `variants` is
 * keyed by variant name, e.g. `2x`.
 */
final readonly class ImageSet
{
    /**
     * @param  array<string, string>  $variants
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $base,
        public array $variants,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $variants = [];

        foreach (is_array($data['variants'] ?? null) ? $data['variants'] : [] as $name => $url) {
            if (($string = Parse::nonEmptyString($url)) !== null) {
                $variants[(string) $name] = $string;
            }
        }

        return new self(
            base: Parse::nonEmptyString($data['base'] ?? null),
            variants: $variants,
            raw: $data,
        );
    }

    /**
     * The named variant, falling back to `base`.
     */
    public function url(?string $variant = null): ?string
    {
        if ($variant !== null && isset($this->variants[$variant])) {
            return $this->variants[$variant];
        }

        return $this->base;
    }
}
