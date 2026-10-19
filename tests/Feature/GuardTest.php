<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Oki\People\Laravel\Auth\PeopleGuard;
use Oki\People\Laravel\Auth\PeopleUser;
use Oki\People\Tokens\AccessToken;

beforeEach(function () {
    people_answers();
    PeopleGuard::resolveUsersUsing(null);

    Route::middleware('auth:people')->get('/whoami', fn (Request $request) => ['id' => $request->user()?->getAuthIdentifier()]);
});

it('signs in whoever a genuine token names', function () {
    $this->withToken(access_token('user-1'))->getJson('/whoami')->assertOk()->assertJson(['id' => 'user-1']);
});

it('turns away a request with no token', function () {
    $this->getJson('/whoami')->assertUnauthorized();
});

it('turns away a token that does not verify', function () {
    $this->withToken('not-a-token')->getJson('/whoami')->assertUnauthorized();
});

it('answers a new token with whoever it names, not the last one', function () {
    $this->withToken(access_token('user-1'))->getJson('/whoami')->assertJson(['id' => 'user-1']);
    $this->withToken(access_token('user-2'))->getJson('/whoami')->assertJson(['id' => 'user-2']);
});

it('finds the application\'s own user when told how', function () {
    PeopleGuard::resolveUsersUsing(fn (AccessToken $token) => new GenericUser(['id' => 'local-'.$token->subject]));

    $this->withToken(access_token('user-1'))->getJson('/whoami')->assertJson(['id' => 'local-user-1']);
});

it('turns away a token whose user the application does not know', function () {
    PeopleGuard::resolveUsersUsing(fn (AccessToken $token) => null);

    $this->withToken(access_token())->getJson('/whoami')->assertUnauthorized();
});

it('hands over the verified token behind the user', function () {
    request()->headers->set('Authorization', 'Bearer '.access_token('user-1'));

    /** @var PeopleGuard $guard */
    $guard = Auth::guard('people');

    expect($guard->token()?->subject)->toBe('user-1')
        ->and($guard->token()?->can('orders.read'))->toBeTrue()
        ->and($guard->user())->toBeInstanceOf(PeopleUser::class)
        ->and($guard->validate(['password' => 'x']))->toBeFalse();
});

it('forgets the user once a request carries no token', function () {
    /** @var PeopleGuard $guard */
    $guard = Auth::guard('people');
    $guard->setRequest(Request::create('/', server: ['HTTP_AUTHORIZATION' => 'Bearer '.access_token()]));
    expect($guard->check())->toBeTrue();

    $guard->setRequest(Request::create('/'));

    expect($guard->user())->toBeNull()
        ->and($guard->token())->toBeNull();
});
