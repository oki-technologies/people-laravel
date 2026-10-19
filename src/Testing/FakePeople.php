<?php

declare(strict_types=1);

namespace Oki\People\Laravel\Testing;

use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Oki\People\Laravel\Clock;
use Oki\People\People;
use Oki\People\Testing\PeopleFake;

/**
 * Swaps People for one in memory, for the rest of a test.
 *
 *     $people = FakePeople::install();
 *     $this->withToken($people->token('user-1'))->getJson('/orders')->assertOk();
 *
 * Configured as `config/people.php` says, so a token it issues is one this
 * application accepts. Nothing goes over the network, and `Http::fake()`
 * never sees its calls.
 */
final class FakePeople
{
    /**
     * @param  array{private: non-empty-string, public: non-empty-string}|null  $keys
     */
    public static function install(?array $keys = null, string $kid = 'people-fake'): PeopleFake
    {
        $factory = new HttpFactory;
        $fake = new PeopleFake(
            $factory,
            issuer: self::text(config('people.issuer'), 'https://people.example.com'),
            clientId: self::text(config('people.client_id'), 'client-1'),
            audience: self::text(config('people.audience'), 'api.example.com'),
            kid: $kid !== '' ? $kid : 'people-fake',
            keys: $keys,
        );

        app()->instance(People::class, $fake->people(new Repository(new ArrayStore), new Clock, $factory));

        return $fake;
    }

    /**
     * @param  non-empty-string  $fallback
     * @return non-empty-string
     */
    private static function text(mixed $value, string $fallback): string
    {
        return is_string($value) && $value !== '' ? $value : $fallback;
    }
}
