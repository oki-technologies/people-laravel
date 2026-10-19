<?php

declare(strict_types=1);

namespace Oki\People\Laravel\Auth;

use Closure;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Oki\People\Exceptions\TokenRejected;
use Oki\People\Tokens\AccessToken;
use Oki\People\Tokens\AccessTokenVerifier;

/**
 * `auth:people` — whoever the bearer token People issued names.
 *
 * Verified locally on every request: no session, no call to People. What
 * comes back is a `PeopleUser`, or your own model once
 * `resolveUsersUsing()` says how to find one.
 */
final class PeopleGuard implements Guard
{
    use GuardHelpers;

    /** @var (Closure(AccessToken): ?Authenticatable)|null */
    private static ?Closure $resolver = null;

    /** The token the current user was resolved from, so a new one is not answered with an old user. */
    private ?string $resolvedFrom = null;

    private ?AccessToken $token = null;

    public function __construct(
        private readonly AccessTokenVerifier $verifier,
        private Request $request,
    ) {}

    /**
     * How a verified token becomes this application's user. Null turns the request away.
     *
     * @param  (Closure(AccessToken): ?Authenticatable)|null  $resolver
     */
    public static function resolveUsersUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    public function user(): ?Authenticatable
    {
        $jwt = $this->request->bearerToken();

        if ($jwt === null || $jwt === '') {
            return $this->user = $this->token = null;
        }

        if ($this->resolvedFrom === $jwt) {
            return $this->user;
        }

        $this->resolvedFrom = $jwt;

        try {
            $this->token = $this->verifier->verify($jwt);
        } catch (TokenRejected) {
            return $this->user = $this->token = null;
        }

        return $this->user = self::$resolver !== null ? (self::$resolver)($this->token) : new PeopleUser($this->token);
    }

    /** The verified token behind the current user. */
    public function token(): ?AccessToken
    {
        $this->user();

        return $this->token;
    }

    /** @param  array<string, mixed>  $credentials */
    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function setRequest(Request $request): self
    {
        $this->request = $request;

        return $this;
    }
}
