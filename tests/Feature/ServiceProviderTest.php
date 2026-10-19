<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Oki\People\Laravel\PeopleServiceProvider;
use Oki\People\People;

it('builds People from the configuration', function () {
    $people = app(People::class);

    expect($people->config->issuer)->toBe(ISSUER)
        ->and($people->config->clientId)->toBe(CLIENT)
        ->and($people->config->audience)->toBe(AUDIENCE)
        ->and(app(People::class))->toBe($people);
});

it('leaves out what is not configured', function () {
    config(['people.client_secret' => '', 'people.cache' => 'array']);
    app()->forgetInstance(People::class);

    expect(app(People::class)->config->clientSecret)->toBeNull();
});

it('offers its configuration for publishing', function () {
    expect(ServiceProvider::pathsToPublish(PeopleServiceProvider::class, 'people-config'))
        ->toHaveCount(1);
});
