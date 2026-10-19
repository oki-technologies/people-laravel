<?php

declare(strict_types=1);

use Oki\People\Laravel\Auth\PeopleUser;
use Oki\People\Tokens\AccessToken;

function people_user(): PeopleUser
{
    return new PeopleUser(new AccessToken('jwt', 'user-1', CLIENT, [], [AUDIENCE], new DateTimeImmutable, []));
}

it('is known by the token\'s subject', function () {
    expect(people_user()->getAuthIdentifier())->toBe('user-1')
        ->and(people_user()->getAuthIdentifierName())->toBe('sub');
});

it('keeps no password and no remember token', function () {
    $user = people_user();
    $user->setRememberToken('x');

    expect($user->getAuthPasswordName())->toBe('password')
        ->and($user->getRememberToken())->toBe('')
        ->and($user->getRememberTokenName())->toBe('')
        ->and(fn () => $user->getAuthPassword())->toThrow(RuntimeException::class);
});
