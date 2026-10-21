<?php

declare(strict_types=1);

use Digitonic\DrawHouse\DTO\Webhooks\DrawWonData;
use Digitonic\DrawHouse\DTO\Webhooks\InstantWinData;
use Digitonic\DrawHouse\DTO\Webhooks\TicketPurchasedData;
use Digitonic\DrawHouse\DTO\Webhooks\WalletMovementData;
use Digitonic\DrawHouse\DTO\Webhooks\WebhookEvent;
use Digitonic\DrawHouse\Enums\InstantWinPrizeType;
use Digitonic\DrawHouse\Enums\PrizeCategory;
use Digitonic\DrawHouse\Enums\PrizeFulfilment;
use Digitonic\DrawHouse\Enums\WalletMovementKind;
use Digitonic\DrawHouse\Enums\WebhookEventType;
use Digitonic\DrawHouse\Webhooks\WebhookVerifier;

/**
 * An activity event body, as in the Partner API v1.4 spec's examples.
 *
 * @param  array<string, mixed>  $data
 * @param  array{type: string, id: string}  $resource
 * @return array<string, mixed>
 */
function activityEventFixture(string $type, array $resource, array $data, int $id = 3301): array
{
    return [
        'id' => $id,
        'type' => $type,
        'occurredAt' => '2026-10-20T19:02:11Z',
        'affiliateId' => 17,
        'resource' => $resource,
        'data' => $data,
    ];
}

function ticketPurchasedEventFixture(): array
{
    return activityEventFixture('ticket.purchased', ['type' => 'checkout', 'id' => '90412'], [
        'playerId' => 18342,
        'checkoutId' => 90412,
        'gameId' => 61,
        'gameName' => 'Weekly Prize Draw',
        'roundId' => 1180,
        'editionId' => 5,
        'quantity' => 3,
        'amount' => 6,
        'cashAmount' => 4,
        'siteCreditAmount' => 2,
        'currency' => 'GBP',
        'ticketNumbers' => [4471, 4472, 4473],
        'instantWins' => 1,
        'attribution' => ['utmSource' => 'joe', 'utmMedium' => 'email', 'utmCampaign' => 'oct-launch'],
    ]);
}

it('parses a ticket.purchased event into one typed checkout', function () {
    $event = WebhookEvent::fromJson(webhookBody(ticketPurchasedEventFixture()));
    $purchase = $event->ticketPurchased();

    expect($event->type)->toBe(WebhookEventType::TICKET_PURCHASED)
        ->and($event->resource->type)->toBe('checkout')
        ->and($event->resource->intId())->toBe(90412)
        ->and($purchase)->toBeInstanceOf(TicketPurchasedData::class)
        ->and($purchase?->playerId)->toBe(18342)
        ->and($purchase?->checkoutId)->toBe(90412)
        ->and($purchase?->gameId)->toBe(61)
        ->and($purchase?->gameName)->toBe('Weekly Prize Draw')
        ->and($purchase?->roundId)->toBe(1180)
        ->and($purchase?->editionId)->toBe(5)
        ->and($purchase?->quantity)->toBe(3)
        ->and($purchase?->amount)->toBe(6.0)
        ->and($purchase?->cashAmount)->toBe(4.0)
        ->and($purchase?->siteCreditAmount)->toBe(2.0)
        ->and($purchase?->currency)->toBe('GBP')
        ->and($purchase?->ticketNumbers)->toBe([4471, 4472, 4473])
        ->and($purchase?->instantWins)->toBe(1)
        ->and($purchase?->attribution->utmCampaign)->toBe('oct-launch')
        ->and($event->player())->toBeNull();
});

it('parses an instant_win.revealed event', function () {
    $event = WebhookEvent::fromArray(activityEventFixture('instant_win.revealed', ['type' => 'ticket', 'id' => '7718842'], [
        'playerId' => 18342,
        'ticketId' => 5529931,
        'raffleTicketId' => 7718842,
        'ticketNumber' => 4472,
        'gameId' => 61,
        'roundId' => 1180,
        'checkoutId' => 90412,
        'editionId' => 5,
        'prizeType' => 'INSTANT_CASH',
        'prizeName' => '£10 Instant Win',
        'prizeAmount' => 10,
    ], 3302));
    $win = $event->instantWin();

    expect($event->type)->toBe(WebhookEventType::INSTANT_WIN_REVEALED)
        ->and($win)->toBeInstanceOf(InstantWinData::class)
        ->and($win?->raffleTicketId)->toBe(7718842)
        ->and($win?->ticketId)->toBe(5529931)
        ->and($win?->ticketNumber)->toBe(4472)
        ->and($win?->checkoutId)->toBe(90412)
        ->and($win?->prizeType)->toBe(InstantWinPrizeType::INSTANT_CASH)
        ->and($win?->prizeName)->toBe('£10 Instant Win')
        ->and($win?->prizeAmount)->toBe(10.0);
});

it('parses wallet.credited and wallet.debited and tells them apart', function (string $type, string $kind, array $reference, bool $credit) {
    $event = WebhookEvent::fromArray(activityEventFixture($type, ['type' => 'wallet', 'id' => '18342'], [
        'playerId' => 18342,
        'walletId' => 18342,
        'kind' => $kind,
        'amount' => 20,
        'balanceAfter' => 24.5,
        'currency' => 'GBP',
        'reference' => $reference,
        'gameId' => 61,
        'roundId' => 1180,
    ]));
    $movement = $event->walletMovement();

    expect($movement)->toBeInstanceOf(WalletMovementData::class)
        ->and($event->isWalletCredit())->toBe($credit)
        ->and($movement?->kind)->toBe(WalletMovementKind::from($kind))
        ->and($movement?->amount)->toBe(20.0)
        ->and($movement?->balanceAfter)->toBe(24.5)
        ->and($movement?->reference?->type)->toBe($reference['type'])
        ->and($movement?->reference?->id)->toBe($reference['id'])
        ->and($movement?->roundId)->toBe(1180);
})->with([
    'a deposit' => ['wallet.credited', 'DEPOSIT', ['type' => 'deposit', 'id' => 'd4b1c3e0-7a1e-4f0b-9b2a-3c6d1e8f9a01'], true],
    'a purchase' => ['wallet.debited', 'PURCHASE', ['type' => 'checkout', 'id' => '90412'], false],
]);

it('reads a wallet movement with no ledger reference', function () {
    $event = WebhookEvent::fromArray(activityEventFixture('wallet.credited', ['type' => 'wallet', 'id' => '1'], [
        'playerId' => 1, 'walletId' => 1, 'kind' => 'BONUS', 'amount' => 5, 'balanceAfter' => 5, 'currency' => 'GBP', 'reference' => null,
    ]));

    expect($event->walletMovement()?->reference)->toBeNull()
        ->and($event->walletMovement()?->gameId)->toBeNull();
});

it('parses a draw.won event', function () {
    $event = WebhookEvent::fromArray(activityEventFixture('draw.won', ['type' => 'ticket', 'id' => '7718842'], [
        'playerId' => 18342,
        'gameId' => 61,
        'gameName' => 'Weekly Prize Draw',
        'roundId' => 1180,
        'editionId' => 5,
        'ticketId' => 5529931,
        'raffleTicketId' => 7718842,
        'ticketNumber' => 4472,
        'drawPrizeId' => 2207,
        'prizePoolId' => 310,
        'prizeName' => '£500 Cash',
        'prizeAmount' => 500,
        'prizeCategory' => 'CASH',
        'fulfilment' => 'PAID_TO_WALLET',
    ], 3410));
    $won = $event->drawWon();

    expect($event->type)->toBe(WebhookEventType::DRAW_WON)
        ->and($won)->toBeInstanceOf(DrawWonData::class)
        ->and($won?->drawPrizeId)->toBe(2207)
        ->and($won?->prizePoolId)->toBe(310)
        ->and($won?->prizeName)->toBe('£500 Cash')
        ->and($won?->prizeAmount)->toBe(500.0)
        ->and($won?->prizeCategory)->toBe(PrizeCategory::CASH)
        ->and($won?->fulfilment)->toBe(PrizeFulfilment::PAID_TO_WALLET)
        ->and($won?->editionId)->toBe(5);
});

it('verifies and parses an activity event in one call', function () {
    $secret = webhookSecret();
    $body = webhookBody(ticketPurchasedEventFixture());

    $event = (new WebhookVerifier([$secret]))->event($body, WebhookVerifier::signatureHeader($body, $secret));

    expect($event->ticketPurchased()?->checkoutId)->toBe(90412);
});

it('lets an endpoint subscribe to the activity events', function () {
    expect(WebhookEventType::subscribable())->toContain(
        WebhookEventType::TICKET_PURCHASED,
        WebhookEventType::INSTANT_WIN_REVEALED,
        WebhookEventType::WALLET_CREDITED,
        WebhookEventType::WALLET_DEBITED,
        WebhookEventType::DRAW_WON,
    )->not->toContain(WebhookEventType::PING);
});
