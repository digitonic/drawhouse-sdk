<?php

declare(strict_types=1);

use Digitonic\DrawHouse\DTO\Tickets\PartnerTicket;
use Digitonic\DrawHouse\DTO\Tickets\RaffleOutcome;
use Digitonic\DrawHouse\Enums\TicketPaymentStatus;
use Digitonic\DrawHouse\Enums\TicketPaymentType;
use Digitonic\DrawHouse\Enums\TicketStatus;
use Digitonic\DrawHouse\Enums\TicketType;
use Digitonic\DrawHouse\Http\Requests\GetPartnerTicketsRequest;
use Digitonic\DrawHouse\Http\Requests\GetTicketsRequest;
use Digitonic\DrawHouse\Support\TicketsQuery;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(fn () => seedTokens());

/**
 * A tickets-feed row, as in the v1.4 spec's example.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function partnerTicketFixture(int $id = 918342, array $overrides = []): array
{
    return array_replace_recursive([
        'id' => $id,
        'ticketRef' => 500000000918342,
        'playerId' => 123,
        'gameId' => 10,
        'roundId' => 20,
        'editionId' => 3,
        'status' => 'INPLAY',
        'ticketType' => 'REGULAR',
        'price' => 1,
        'paymentStatus' => 'SUCCEEDED',
        'paymentType' => 'DEBIT',
        'checkoutId' => 55012,
        'attribution' => ['utmSource' => 'facebook', 'utmMedium' => 'cpc', 'utmCampaign' => 'october-draw'],
        'raffle' => [
            'ticketNumber' => 777,
            'status' => 'SOLD',
            'hasPrize' => false,
            'prizeRevealed' => true,
            'prizeType' => null,
            'prizeName' => null,
            'prizeAmount' => null,
            'prizeClaimed' => false,
            'revealedAt' => '2026-10-01T12:00:05Z',
            'claimedAt' => null,
        ],
        'createdAt' => '2026-10-01T12:00:00Z',
        'updatedAt' => '2026-10-01T12:00:05Z',
    ], $overrides);
}

/**
 * One offset page of GET /v2/games/tickets with `count` tickets from `offset`.
 */
function gameTicketsPageResponse(int $offset, int $count, int $total): MockResponse
{
    $tickets = array_map(fn (int $i): array => [...ticketFixture(), 'id' => $offset + $i + 1], range(0, max(0, $count - 1)));

    return envelope(['tickets' => $count === 0 ? [] : $tickets, 'total' => $total, 'limit' => 5000, 'offset' => $offset]);
}

it('pages through every ticket in a draw now the route is offset-paged', function () {
    $mock = MockClient::global([
        gameTicketsPageResponse(0, 3, 5),
        gameTicketsPageResponse(3, 2, 5),
    ]);

    $tickets = drawhouse()->tickets(101);

    expect($tickets)->toHaveCount(5)
        ->and(array_map(fn ($t) => $t->id, $tickets))->toBe([1, 2, 3, 4, 5])
        ->and(sentQueryString($mock, 0))->toBe('gameId=101&limit=5000&offset=0')
        ->and(sentQueryString($mock, 1))->toBe('gameId=101&limit=5000&offset=3');
});

it('stops after one page when the response has no total, as before v1.4', function () {
    $mock = MockClient::global([GetTicketsRequest::class => envelope(['tickets' => [ticketFixture()]])]);

    expect(drawhouse()->tickets(101))->toHaveCount(1)
        ->and(sentRequests($mock))->toHaveCount(1);
});

it('fetches one offset page of a draw\'s tickets, clamping the page size', function () {
    $mock = MockClient::global([GetTicketsRequest::class => gameTicketsPageResponse(1000, 1, 2001)]);

    $page = drawhouse()->gameTicketsPage(101, limit: 9999, offset: 1000);

    expect($page->total)->toBe(2001)
        ->and($page->hasMore())->toBeTrue()
        ->and($page->nextOffset())->toBe(1001)
        ->and(sentQueryString($mock, 0))->toBe('gameId=101&limit=5000&offset=1000');
});

it('parses every field of a tickets-feed row', function () {
    MockClient::global([GetPartnerTicketsRequest::class => envelope(['items' => [partnerTicketFixture()], 'nextCursor' => null])]);

    $page = drawhouse()->ticketsPage();
    $ticket = $page->items[0];

    expect($page->hasMore())->toBeFalse()
        ->and($ticket)->toBeInstanceOf(PartnerTicket::class)
        ->and($ticket->id)->toBe(918342)
        ->and($ticket->ticketRef)->toBe(500000000918342)
        ->and($ticket->playerId)->toBe(123)
        ->and($ticket->gameId)->toBe(10)
        ->and($ticket->roundId)->toBe(20)
        ->and($ticket->editionId)->toBe(3)
        ->and($ticket->status)->toBe(TicketStatus::INPLAY)
        ->and($ticket->ticketType)->toBe(TicketType::REGULAR)
        ->and($ticket->price)->toBe(1.0)
        ->and($ticket->paymentStatus)->toBe(TicketPaymentStatus::SUCCEEDED)
        ->and($ticket->paymentType)->toBe(TicketPaymentType::DEBIT)
        ->and($ticket->checkoutId)->toBe(55012)
        ->and($ticket->attribution->utmCampaign)->toBe('october-draw')
        ->and($ticket->raffle)->toBeInstanceOf(RaffleOutcome::class)
        ->and($ticket->raffle?->ticketNumber)->toBe(777)
        ->and($ticket->raffle?->hasWon())->toBeFalse()
        ->and($ticket->createdAt?->toIso8601ZuluString())->toBe('2026-10-01T12:00:00Z')
        ->and($ticket->isPurchased())->toBeTrue()
        ->and($ticket->isRefunded())->toBeFalse();
});

it('reads a revealed prize, a refund, empty UTMs and a non-raffle ticket', function () {
    $won = PartnerTicket::fromArray(partnerTicketFixture(overrides: ['raffle' => ['hasPrize' => true, 'prizeType' => 'INSTANT_CASH', 'prizeName' => '£10', 'prizeAmount' => 10]]));
    $refunded = PartnerTicket::fromArray(partnerTicketFixture(overrides: ['status' => 'REFUNDED', 'attribution' => ['utmSource' => '', 'utmMedium' => '', 'utmCampaign' => '']]));
    $bonus = PartnerTicket::fromArray([...partnerTicketFixture(), 'ticketType' => 'BONUS', 'raffle' => null, 'checkoutId' => null]);

    expect($won->raffle?->hasWon())->toBeTrue()
        ->and($won->raffle?->prizeAmount)->toBe(10.0)
        ->and($refunded->isRefunded())->toBeTrue()
        ->and($refunded->attribution->hasUtm())->toBeFalse()
        ->and($bonus->raffle)->toBeNull()
        ->and($bonus->checkoutId)->toBeNull()
        ->and($bonus->ticketType?->isPaid())->toBeFalse();
});

it('sends every tickets-feed filter, repeating list parameters', function () {
    $mock = MockClient::global([GetPartnerTicketsRequest::class => envelope(['items' => [], 'nextCursor' => null])]);

    drawhouse()->ticketsPage(
        TicketsQuery::make()
            ->updatedAfter('2026-10-01T00:00:00Z')
            ->createdAfter('2026-09-01T00:00:00Z')
            ->createdBefore('2026-10-02T00:00:00Z')
            ->round(20, 21)
            ->game(10)
            ->edition(3)
            ->player(123)
            ->status(TicketStatus::INPLAY)
            ->paymentStatus(TicketPaymentStatus::SUCCEEDED)
            ->ticketType(TicketType::REGULAR)
            ->limit(9999),
        after: '918342',
    );

    expect(sentPendingRequest($mock, 0)->getUrl())->toBe('https://api.test.raffletown.co.uk/v2/partners/tickets')
        ->and(sentQueryString($mock, 0))->toBe(
            'limit=500&createdAfter=2026-09-01T00%3A00%3A00Z&createdBefore=2026-10-02T00%3A00%3A00Z&updatedAfter=2026-10-01T00%3A00%3A00Z'
            .'&gameId=10&roundId=20&roundId=21&editionId=3&playerId=123&status=INPLAY&paymentStatus=SUCCEEDED&ticketType=REGULAR&after=918342'
        );
});

it('walks every page of the tickets feed lazily, following nextCursor', function () {
    $mock = MockClient::global([
        envelope(['items' => [partnerTicketFixture(1), partnerTicketFixture(2)], 'nextCursor' => '2']),
        envelope(['items' => [partnerTicketFixture(3)], 'nextCursor' => null]),
    ]);

    $tickets = drawhouse()->allTickets(TicketsQuery::make()->limit(2));

    expect($mock->getRecordedResponses())->toBeEmpty()
        ->and($tickets->map(fn (PartnerTicket $t) => $t->id)->all())->toBe([1, 2, 3])
        ->and(sentQueryString($mock, 1))->toBe('limit=2&after=2');
});

it('refuses a createdBefore that is not later than createdAfter', function () {
    TicketsQuery::make()->createdAfter('2026-10-02T00:00:00Z')->createdBefore('2026-10-01T00:00:00Z');
})->throws(InvalidArgumentException::class, 'createdBefore');
