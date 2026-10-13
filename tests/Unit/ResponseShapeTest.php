<?php

declare(strict_types=1);

use Digitonic\DrawHouse\Support\ResponseShape;

it('redacts PII keys at any depth while preserving structure and types', function () {
    $out = ResponseShape::redact([
        'errorCode' => 0,
        'data' => [
            'items' => [[
                'id' => 1,
                'email' => 'jo@example.com',
                'firstName' => '',
                'lastName' => 'Bloggs',
                'phone' => null,
                'dateOfBirth' => '1990-01-31',
                'attribution' => ['params' => ['utm_source' => 'acme']],
                'consents' => ['email' => ['granted' => true, 'recordedAt' => '2026-01-01T00:00:00Z']],
            ]],
        ],
        'accessToken' => 'secret',
        'pooledRevShare' => 60,
        'minimumGuaranteedIncome' => 5000.5,
        'launchProposalApproved' => true,
        'companyName' => 'Example Media',
        'affiliateId' => 20,
        'playerId' => 25,
    ]);

    expect($out['data']['items'][0]['id'])->toBe(1)
        ->and($out['data']['items'][0]['email'])->toBe('[redacted]')
        ->and($out['data']['items'][0]['firstName'])->toBe('')
        ->and($out['data']['items'][0]['lastName'])->toBe('[redacted]')
        ->and($out['data']['items'][0]['phone'])->toBeNull()
        ->and($out['data']['items'][0]['dateOfBirth'])->toBe('1990-01-01')
        ->and($out['data']['items'][0]['attribution']['params']['utm_source'])->toBe('acme')
        ->and($out['data']['items'][0]['consents']['email'])->toBe(['granted' => true, 'recordedAt' => '2026-01-01T00:00:00Z'])
        ->and($out['accessToken'])->toBe('[redacted]')
        ->and($out['pooledRevShare'])->toBe(0)
        ->and($out['minimumGuaranteedIncome'])->toBe(0.0)
        ->and($out['launchProposalApproved'])->toBeFalse()
        ->and($out['companyName'])->toBe('[redacted]')
        ->and($out['affiliateId'])->toBe(0)
        ->and($out['playerId'])->toBe(0)
        ->and(json_encode($out))->not->toContain('jo@example.com')
        ->and(json_encode($out))->not->toContain('secret');
});

it('describes a body by path and JSON type, descending into the first list element', function () {
    $shape = ResponseShape::describe([
        'errorCode' => 0,
        'data' => ['draws' => [['gameId' => 40, 'tickets' => ['price' => '5.00'], 'liveDraw' => null, 'prizes' => []]]],
    ]);

    expect($shape)->toBe([
        'errorCode' => 'int',
        'data' => 'object',
        'data.draws' => 'list',
        'data.draws[].gameId' => 'int',
        'data.draws[].tickets' => 'object',
        'data.draws[].tickets.price' => 'string',
        'data.draws[].liveDraw' => 'null',
        'data.draws[].prizes' => 'list(empty)',
    ]);
});

it('reports removed, added and retyped paths but tolerates null, int/float and empty-list differences', function () {
    $recorded = ['a' => 'int', 'b' => 'string', 'c' => 'null', 'd' => 'list(empty)', 'e' => 'float', 'gone' => 'bool'];
    $live = ['a' => 'string', 'b' => 'string', 'c' => 'object', 'd' => 'list', 'e' => 'int', 'new' => 'string'];

    expect(ResponseShape::diff($recorded, $live))->toBe([
        '~ a changed from int to string',
        '- gone (bool) is no longer returned',
        '+ new (string) is new',
    ]);
});
