<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO;

use Digitonic\DrawHouse\Support\Parse;

/**
 * GET /v2/partners/me — the authenticated partner's profile.
 *
 * `affiliateId` is nullable. Use `status` / isLive() to tell whether the
 * partner is live, not the presence of `affiliateId`. The players feed
 * answers 403 `partner_not_live` until LIVE. The
 * commercial fields (`pooledRevShare`, `ringFencedRevShare`,
 * `minimumGuaranteedIncome` and `launchProposal*`) describe the partner
 * agreement and are not needed for integration; they are exposed as-is.
 */
final readonly class Partner
{
    public const STATUS_LIVE = 'LIVE';

    /**
     * @param  int|null  $affiliateId  Required by the unauthenticated endpoints. Nullable.
     * @param  string|null  $status  Partner onboarding status, e.g. LIVE.
     * @param  int|null  $playerId  The partner user account's player id.
     * @param  bool|null  $crmInScope  Whether CRM is in scope of the partner agreement.
     * @param  array<string, mixed>  $launchProposal  Every `launchProposal*` field, keyed by its wire name.
     * @param  array<string, mixed>  $raw  Everything the API returned, untouched.
     */
    public function __construct(
        public ?int $affiliateId,
        public ?string $status = null,
        public ?int $playerId = null,
        public ?string $email = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $phone = null,
        public ?string $companyName = null,
        public ?bool $crmInScope = null,
        public ?float $pooledRevShare = null,
        public ?float $ringFencedRevShare = null,
        public ?float $minimumGuaranteedIncome = null,
        public array $launchProposal = [],
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $launchProposal = array_filter(
            $data,
            static fn (string $key): bool => str_starts_with($key, 'launchProposal'),
            ARRAY_FILTER_USE_KEY,
        );

        return new self(
            affiliateId: Parse::int($data['affiliateId'] ?? null),
            status: Parse::nonEmptyString($data['status'] ?? null),
            playerId: Parse::int($data['playerId'] ?? null),
            email: Parse::string($data['email'] ?? null),
            firstName: Parse::string($data['firstName'] ?? null),
            lastName: Parse::string($data['lastName'] ?? null),
            phone: Parse::string($data['phone'] ?? null),
            companyName: Parse::string($data['companyName'] ?? null),
            crmInScope: Parse::bool($data['crmInScope'] ?? null),
            pooledRevShare: Parse::float($data['pooledRevShare'] ?? null),
            ringFencedRevShare: Parse::float($data['ringFencedRevShare'] ?? null),
            minimumGuaranteedIncome: Parse::float($data['minimumGuaranteedIncome'] ?? null),
            launchProposal: $launchProposal,
            raw: $data,
        );
    }

    /**
     * True once DrawHouse has made the account LIVE. Until then
     * `affiliateId` is null and the data endpoints are closed.
     */
    public function isLive(): bool
    {
        return strtoupper((string) $this->status) === self::STATUS_LIVE;
    }
}
