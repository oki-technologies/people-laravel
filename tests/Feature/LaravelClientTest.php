<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Oki\People\Laravel\Clock;
use Oki\People\Laravel\Http\LaravelClient;
use Psr\Http\Client\NetworkExceptionInterface;

it('sends the SDK\'s requests through Laravel\'s client, headers and body intact', function () {
    Http::fake(['people.example.com/*' => Http::response(['ok' => true], 201)]);
    $factory = new HttpFactory;

    $response = (new LaravelClient(app(Factory::class), 5))->sendRequest(
        $factory->createRequest('POST', 'https://people.example.com/oauth/token')
            ->withHeader('Accept', 'application/json')
            ->withBody($factory->createStream('grant_type=client_credentials')),
    );

    expect($response->getStatusCode())->toBe(201)
        ->and(json_decode((string) $response->getBody(), true))->toBe(['ok' => true]);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->body() === 'grant_type=client_credentials'
        && $request->hasHeader('Accept', 'application/json'));
});

it('reports a failed connection as PSR-18 does', function () {
    Http::fake(fn () => throw new ConnectionException('Down.'));
    $request = (new HttpFactory)->createRequest('GET', 'https://people.example.com/oauth/jwks');

    try {
        (new LaravelClient(app(Factory::class), 5))->sendRequest($request);
    } catch (NetworkExceptionInterface $e) {
        expect($e->getRequest())->toBe($request)
            ->and($e->getMessage())->toBe('Down.');

        return;
    }

    $this->fail('No network exception was raised.');
});

it('tells the time the application tells', function () {
    $this->travelTo(new DateTimeImmutable('2030-01-01 00:00:00'));

    expect((new Clock)->now()->format('Y-m-d'))->toBe('2030-01-01');
});
