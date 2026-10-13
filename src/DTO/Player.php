<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO;

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\Support\Parse;

/**
 * One item of GET /v2/partners/reports/players.
 *
 * Contains full PII (email, DOB, phone). The SDK passes it through verbatim;
 * the consuming application is responsible for minimisation.
 *
 * `registrationDate` arrives as `YYYYMMDD`, `dateOfBirth` as `YYYY-MM-DD`.
 * `firstName` / `lastName` may be empty strings.
 */
final readonly class Player
{
    /**
     * @param  int|null  $id  Matches `playerId` on draw tickets.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $id,
        public ?string $email,
        public ?string $firstName,
        public ?string $lastName,
        public ?CarbonImmutable $dateOfBirth,
        public ?string $phone,
        public ?CarbonImmutable $registrationDate,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Parse::int($data['id'] ?? null),
            email: Parse::string($data['email'] ?? null),
            firstName: Parse::string($data['firstName'] ?? null),
            lastName: Parse::string($data['lastName'] ?? null),
            dateOfBirth: Parse::date($data['dateOfBirth'] ?? null),
            phone: Parse::string($data['phone'] ?? null),
            registrationDate: Parse::compactDate($data['registrationDate'] ?? null),
            raw: $data,
        );
    }
}
