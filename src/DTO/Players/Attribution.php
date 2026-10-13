<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Players;

use Digitonic\DrawHouse\Enums\AcquisitionChannel;
use Digitonic\DrawHouse\Support\Parse;

/**
 * `attribution` on a players-feed row: the registration landing URL and
 * everything that was on it.
 *
 * `params` holds every query parameter present at sign-up: keys lower-cased
 * and cut at 64 characters, values cut at 255, at most 50 keys. The site has
 * to send them at sign-up, so players who registered before that was in
 * place have an empty `params` and only the UTM triple.
 *
 * `acquisitionChannel` is DrawHouse's own derivation and does not match our
 * tracked-link vocabulary — see AcquisitionChannel. Classify from `params`.
 */
final readonly class Attribution
{
    /**
     * @param  array<string, string>  $params  Raw landing-URL query parameters.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $utmSource,
        public ?string $utmMedium,
        public ?string $utmCampaign,
        public ?AcquisitionChannel $acquisitionChannel,
        public ?string $landingUrl,
        public array $params,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $params = [];

        foreach (is_array($data['params'] ?? null) ? $data['params'] : [] as $key => $value) {
            if (($string = Parse::string($value)) !== null) {
                $params[(string) $key] = $string;
            }
        }

        return new self(
            utmSource: Parse::nonEmptyString($data['utmSource'] ?? null),
            utmMedium: Parse::nonEmptyString($data['utmMedium'] ?? null),
            utmCampaign: Parse::nonEmptyString($data['utmCampaign'] ?? null),
            acquisitionChannel: Parse::enum(AcquisitionChannel::class, $data['acquisitionChannel'] ?? null),
            landingUrl: Parse::nonEmptyString($data['landingUrl'] ?? null),
            params: $params,
            raw: $data,
        );
    }

    /**
     * A single landing-URL parameter (keys are lower-cased on the wire).
     */
    public function param(string $key): ?string
    {
        return $this->params[strtolower($key)] ?? null;
    }

    public function hasUtm(): bool
    {
        return $this->utmSource !== null || $this->utmMedium !== null || $this->utmCampaign !== null;
    }
}
