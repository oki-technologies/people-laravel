<?php

declare(strict_types=1);

namespace Oki\People\Laravel;

use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Factory as Cache;
use Illuminate\Contracts\Config\Repository as Configuration;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Oki\People\Config;
use Oki\People\Laravel\Auth\PeopleGuard;
use Oki\People\Laravel\Console\SyncIdentitiesCommand;
use Oki\People\Laravel\Contracts\HandlesIdentityEvents;
use Oki\People\Laravel\Http\LaravelClient;
use Oki\People\People;
use Oki\People\Tokens\AccessTokenVerifier;
use RuntimeException;

/**
 * Wires the SDK into the application: config, the `people` guard driver and `people:sync`.
 *
 * `People` is a singleton built from `config/people.php`. To keep its cache
 * somewhere other than a store — a tenant-proof one, say — bind your own
 * `People` after this provider.
 */
final class PeopleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/people.php', 'people');

        $this->app->singleton(People::class, function (Container $app): People {
            $factory = new HttpFactory;
            $config = $app->make(Configuration::class);
            $timeout = self::number($config->get('people.timeout'), 5);

            return new People(
                new Config(
                    issuer: self::text($config->get('people.issuer')) ?? '',
                    clientId: self::text($config->get('people.client_id')),
                    clientSecret: self::text($config->get('people.client_secret')),
                    audience: self::text($config->get('people.audience')),
                    timeout: $timeout,
                    keysTtl: self::number($config->get('people.keys_ttl'), 900),
                ),
                new LaravelClient($app->make(Factory::class), $timeout),
                $factory,
                $factory,
                $app->make(Cache::class)->store(self::text($config->get('people.cache'))),
                new Clock,
            );
        });

        $this->app->bind(AccessTokenVerifier::class, fn (Container $app): AccessTokenVerifier => $app->make(People::class)->accessTokens());

        $this->app->bind(HandlesIdentityEvents::class, function (Container $app): HandlesIdentityEvents {
            $handler = $app->make(Configuration::class)->get('people.events.handler');

            if (! is_string($handler) || $handler === '') {
                throw new RuntimeException('Set people.events.handler to the class that applies identity events.');
            }

            $instance = $app->make($handler);

            return $instance instanceof HandlesIdentityEvents
                ? $instance
                : throw new RuntimeException("{$handler} does not implement ".HandlesIdentityEvents::class.'.');
        });
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/people.php' => config_path('people.php')], 'people-config');

        Auth::extend('people', function (Container $app): PeopleGuard {
            $guard = new PeopleGuard($app->make(AccessTokenVerifier::class), $app->make(Request::class));

            // A guard outlives a request under Octane and in tests; it reads each new one.
            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });

        if ($this->app->runningInConsole()) {
            $this->commands([SyncIdentitiesCommand::class]);
        }
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function number(mixed $value, int $fallback): int
    {
        return is_numeric($value) ? (int) $value : $fallback;
    }
}
