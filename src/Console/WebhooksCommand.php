<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Console;

use Digitonic\DrawHouse\DrawHouse;
use Digitonic\DrawHouse\DTO\Webhooks\WebhookEndpoint;
use Digitonic\DrawHouse\DTO\Webhooks\WebhookEndpointWithSecret;
use Digitonic\DrawHouse\Enums\WebhookDeliveryStatus;
use Digitonic\DrawHouse\Enums\WebhookEventType;
use Digitonic\DrawHouse\Exceptions\DrawHouseException;
use Digitonic\DrawHouse\Exceptions\PartnerNotLiveException;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Ops helper for the partner's webhook endpoints: list them, register one,
 * send a test ping, rotate a secret, or show the delivery log. Never run by
 * the test suite or a scheduler.
 *
 * `register` and `rotate` print the signing secret, because that is the only
 * time DrawHouse return it. Run them somewhere the output is not logged, and
 * copy the secret straight into the receiving application's secret store.
 */
final class WebhooksCommand extends Command
{
    protected $signature = 'drawhouse:webhooks
        {action=list : list, register, ping, rotate or deliveries}
        {id? : Endpoint id (ping, rotate, deliveries)}
        {--url= : register: the https:// URL to deliver to}
        {--event=* : register: an event type to subscribe to (repeatable; none = every type)}
        {--description= : register: free text, up to 255 characters}
        {--status=* : deliveries: only these statuses (PENDING, SENDING, DELIVERED, DEAD; repeatable)}
        {--limit=20 : deliveries: how many rows to show}';

    protected $description = 'List, register, ping or rotate DrawHouse webhook endpoints, or show an endpoint\'s delivery log';

    public function handle(DrawHouse $drawhouse): int
    {
        try {
            return match ((string) $this->argument('action')) {
                'list' => $this->list($drawhouse),
                'register' => $this->register($drawhouse),
                'ping' => $this->ping($drawhouse),
                'rotate' => $this->rotate($drawhouse),
                'deliveries' => $this->deliveries($drawhouse),
                default => $this->failWith('Unknown action; use list, register, ping, rotate or deliveries.'),
            };
        } catch (PartnerNotLiveException $e) {
            $this->error('DrawHouse webhooks need a LIVE partner; current status is '.($e->partnerStatus ?? 'unknown').'.');
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
        } catch (DrawHouseException $e) {
            $this->error(sprintf('[HTTP %d / errorCode %s] %s', $e->status, $e->errorCode ?? 'null', $e->getMessage()));
        }

        return self::FAILURE;
    }

    private function list(DrawHouse $drawhouse): int
    {
        $endpoints = $drawhouse->webhooks();

        if ($endpoints === []) {
            $this->line('No webhook endpoints registered.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'url', 'events', 'active', 'failures', 'last success', 'last failure', 'description'],
            array_map(fn (WebhookEndpoint $e): array => [
                $e->id,
                $e->url,
                $this->events($e),
                $e->wasAutoDisabled() ? 'no (auto-disabled)' : ($e->isActive() ? 'yes' : 'no'),
                $e->consecutiveFailures,
                $e->lastSuccessAt?->toIso8601ZuluString() ?? '-',
                $e->lastFailureAt?->toIso8601ZuluString() ?? '-',
                $e->description ?? '',
            ], $endpoints),
        );

        return self::SUCCESS;
    }

    private function register(DrawHouse $drawhouse): int
    {
        $url = (string) $this->option('url');

        if ($url === '') {
            return $this->failWith('Pass --url=https://… for the endpoint to register.');
        }

        $events = [];
        foreach ((array) $this->option('event') as $value) {
            $events[] = WebhookEventType::tryFrom((string) $value)
                ?? throw new InvalidArgumentException("Unknown event type {$value}; use round.state_changed, player.registered or player.updated.");
        }

        $description = $this->option('description');

        $created = $drawhouse->createWebhook($url, $events, is_string($description) && $description !== '' ? $description : null);

        $this->info("Registered endpoint {$created->endpoint->id} for {$created->endpoint->url} ({$this->events($created->endpoint)}).");
        $this->printSecret($created);

        return self::SUCCESS;
    }

    private function ping(DrawHouse $drawhouse): int
    {
        $id = $this->endpointId();
        $event = $drawhouse->testWebhook($id);

        $this->info("Queued webhook.ping event {$event->id} to endpoint {$id}. Check `drawhouse:webhooks deliveries {$id}` for the result.");

        return self::SUCCESS;
    }

    private function rotate(DrawHouse $drawhouse): int
    {
        $id = $this->endpointId();

        if (! $this->confirm("Rotate the signing secret of endpoint {$id}? Deliveries switch to the new secret straight away.", true)) {
            return self::SUCCESS;
        }

        $rotated = $drawhouse->rotateWebhookSecret($id);

        $this->info("Rotated the secret of endpoint {$id}. Keep accepting the old secret until requests verify with this one.");
        $this->printSecret($rotated);

        return self::SUCCESS;
    }

    private function deliveries(DrawHouse $drawhouse): int
    {
        $id = $this->endpointId();

        $statuses = [];
        foreach ((array) $this->option('status') as $value) {
            $statuses[] = WebhookDeliveryStatus::tryFrom(strtoupper((string) $value))
                ?? throw new InvalidArgumentException("Unknown delivery status {$value}; use PENDING, SENDING, DELIVERED or DEAD.");
        }

        $limit = max(1, (int) $this->option('limit'));
        $rows = $drawhouse->allWebhookDeliveries($id, $statuses)->take($limit)->all();

        if ($rows === []) {
            $this->line("No deliveries for endpoint {$id}.");

            return self::SUCCESS;
        }

        $this->table(
            ['delivery', 'event', 'type', 'status', 'attempt', 'last code', 'last error', 'next attempt', 'queued'],
            array_map(static fn ($d): array => [
                $d->id,
                $d->eventId,
                $d->eventTypeName,
                $d->status?->value,
                $d->attempt,
                $d->lastResponseCode ?? '-',
                $d->lastError ?? '-',
                $d->nextAttemptAt?->toIso8601ZuluString() ?? '-',
                $d->createdAt?->toIso8601ZuluString() ?? '-',
            ], array_values($rows)),
        );

        return self::SUCCESS;
    }

    private function printSecret(WebhookEndpointWithSecret $endpoint): void
    {
        if (! $endpoint->hasSecret()) {
            $this->warn('DrawHouse returned no secret.');

            return;
        }

        $this->warn('Signing secret (shown once; store it now, it cannot be read back):');
        $this->line($endpoint->secret);
    }

    private function endpointId(): int
    {
        $id = $this->argument('id');

        if (! is_string($id) || ! ctype_digit($id)) {
            throw new InvalidArgumentException('Pass the endpoint id, e.g. `drawhouse:webhooks ping 7`.');
        }

        return (int) $id;
    }

    private function events(WebhookEndpoint $endpoint): string
    {
        return $endpoint->events === []
            ? 'all events'
            : implode(', ', array_map(static fn (WebhookEventType $t): string => $t->value, $endpoint->events));
    }

    private function failWith(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
