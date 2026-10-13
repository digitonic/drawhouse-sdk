<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Players;

/**
 * `consents` on a players-feed row. Read-only: there is still no write
 * endpoint for consent. No wording version is exposed.
 *
 * `partnerOffers.recordedAt` is ALWAYS null; DrawHouse keep no timestamp for
 * that flag.
 */
final readonly class Consents
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public Consent $email,
        public Consent $sms,
        public Consent $partnerOffers,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $consent = static fn (mixed $value): Consent => Consent::fromArray(is_array($value) ? $value : []);

        return new self(
            email: $consent($data['email'] ?? null),
            sms: $consent($data['sms'] ?? null),
            partnerOffers: $consent($data['partnerOffers'] ?? null),
            raw: $data,
        );
    }
}
