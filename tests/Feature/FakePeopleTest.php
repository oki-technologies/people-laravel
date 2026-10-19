<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Oki\People\Laravel\Testing\FakePeople;

it('signs a test request in with nothing going over the network', function () {
    Http::fake();
    $people = FakePeople::install();
    Route::middleware('auth:people')->get('/whoami', fn (Request $request) => ['id' => $request->user()?->getAuthIdentifier()]);

    $this->withToken($people->token('user-7'))->getJson('/whoami')->assertJson(['id' => 'user-7']);

    Http::assertNothingSent();
});

it('falls back to example settings where the application has none', function () {
    config(['people.issuer' => null, 'people.client_id' => null, 'people.audience' => null]);

    $people = FakePeople::install(kid: '');

    expect($people->issuer)->toBe('https://people.example.com')
        ->and($people->clientId)->toBe('client-1')
        ->and($people->audience)->toBe('api.example.com')
        ->and($people->kid)->toBe('people-fake');
});
