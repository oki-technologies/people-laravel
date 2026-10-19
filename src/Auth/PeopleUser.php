<?php

declare(strict_types=1);

namespace Oki\People\Laravel\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Oki\People\Tokens\AccessToken;
use RuntimeException;

/**
 * Whoever a verified token names, when the application keeps no user of its own.
 *
 * Replace it with your own model through `PeopleGuard::resolveUsersUsing()`.
 */
final readonly class PeopleUser implements Authenticatable
{
    public function __construct(public AccessToken $token) {}

    public function getAuthIdentifierName(): string
    {
        return 'sub';
    }

    public function getAuthIdentifier(): string
    {
        return $this->token->subject;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        throw new RuntimeException('People keeps the password. There is none here.');
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }
}
