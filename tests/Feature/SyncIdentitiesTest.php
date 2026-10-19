<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Oki\People\Events\IdentityEvent;
use Oki\People\Laravel\Contracts\HandlesIdentityEvents;

final class RecordingHandler implements HandlesIdentityEvents
{
    public int $cursor = 0;

    /** @var list<int> */
    public array $handled = [];

    public function cursor(): int
    {
        return $this->cursor;
    }

    public function handle(IdentityEvent $event): void
    {
        $this->handled[] = $event->sequence;
    }

    public function advance(int $sequence): void
    {
        $this->cursor = $sequence;
    }
}

function event_at(int $sequence): array
{
    return ['sequence' => $sequence, 'event_id' => "event-{$sequence}", 'type' => IdentityEvent::UPDATED, 'subject_id' => 'user-1', 'payload' => [], 'occurred_at' => '2026-10-19T09:00:00+00:00'];
}

beforeEach(function () {
    $this->handler = new RecordingHandler;
    app()->instance(RecordingHandler::class, $this->handler);
    config(['people.events.handler' => RecordingHandler::class]);
});

it('applies every page until up to date, resuming where it stopped', function () {
    people_answers([
        ISSUER.'/oauth/token' => Http::response(['access_token' => 'client-token', 'expires_in' => 900]),
        ISSUER.'/v1/identity/events*' => Http::sequence()
            ->push(['data' => [event_at(1), event_at(2)], 'meta' => ['next' => 2]])
            ->push(['data' => [event_at(3)], 'meta' => ['next' => 3]]),
    ]);

    $this->artisan('people:sync', ['--limit' => 2])
        ->expectsOutputToContain('3 identity events applied.')
        ->assertSuccessful();

    expect($this->handler->handled)->toBe([1, 2, 3])
        ->and($this->handler->cursor)->toBe(3);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'after=2'));
});

it('stops when there is nothing new', function () {
    people_answers([
        ISSUER.'/oauth/token' => Http::response(['access_token' => 'client-token', 'expires_in' => 900]),
        ISSUER.'/v1/identity/events*' => Http::response(['data' => [], 'meta' => ['next' => null]]),
    ]);

    $this->artisan('people:sync')->expectsOutputToContain('0 identity events applied.')->assertSuccessful();
});

it('says how to set it up when no handler is configured', function () {
    config(['people.events.handler' => null]);

    $this->artisan('people:sync');
})->throws(RuntimeException::class, 'people.events.handler');

it('refuses a handler that cannot apply events', function () {
    config(['people.events.handler' => stdClass::class]);

    $this->artisan('people:sync');
})->throws(RuntimeException::class, 'does not implement');
