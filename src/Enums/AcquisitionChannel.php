<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Enums;

/**
 * `attribution.acquisitionChannel` on the players feed, derived
 * by DRAWHOUSE from the registration landing URL:
 *
 *  - PAID     any click-id parameter is present (gclid, fbclid, msclkid,
 *             ttclid, clickid, click_id, cid, dclid, twclid), or utm_medium
 *             is one of cpc, ppc, paid, paid_social, paidsocial, display,
 *             affiliate, cpm, cpa.
 *  - ORGANIC  utm_medium is organic, referral, social, email or editorial,
 *             or the landing URL carried no parameters at all.
 *  - UNKNOWN  anything else.
 *
 * This is DrawHouse's rule, exposed as-is. It may not match your own
 * tracked-link vocabulary (a link of yours with utm_medium=social is ORGANIC
 * here, and custom paid mediums may land in UNKNOWN). If it does not, classify
 * from `attribution.params` / the UTM triple with your own rule for
 * organic-vs-paid reporting.
 */
enum AcquisitionChannel: string
{
    case PAID = 'PAID';
    case ORGANIC = 'ORGANIC';
    case UNKNOWN = 'UNKNOWN';
}
