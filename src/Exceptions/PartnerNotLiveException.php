<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Exceptions;

use Digitonic\DrawHouse\Support\Parse;

/**
 * HTTP 403 whose `data` is `{ "error": "partner_not_live", "status": "..." }`.
 *
 * DrawHouse has not yet made the partner account LIVE, so the gated data
 * endpoints are closed. Nothing in the SDK
 * can fix this: contact the DrawHouse onboarding engineer. `partnerStatus`
 * carries the current onboarding status so it can be surfaced to operators.
 */
final class PartnerNotLiveException extends DrawHouseException
{
    public const ERROR = 'partner_not_live';

    public function __construct(
        string $message,
        ?int $errorCode,
        int $status,
        public readonly ?string $partnerStatus = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $errorCode, $status, $previous);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromData(array $data, ?int $errorCode, int $status): self
    {
        $partnerStatus = Parse::nonEmptyString($data['status'] ?? null);

        return new self(
            sprintf(
                'DrawHouse partner account is not LIVE (status: %s); data endpoints are unavailable until onboarding completes',
                $partnerStatus ?? 'unknown',
            ),
            $errorCode,
            $status,
            $partnerStatus,
        );
    }

    /**
     * /v2/partners/me answered without an `affiliateId`, which usually means
     * the partner is not LIVE yet.
     */
    public static function affiliateIdPending(?string $partnerStatus): self
    {
        return new self(
            sprintf(
                'DrawHouse /v2/partners/me returned no affiliateId (partner status: %s)',
                $partnerStatus ?? 'unknown',
            ),
            null,
            200,
            $partnerStatus,
        );
    }
}
