<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder;
use Oki\People\Laravel\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

const ISSUER = 'https://people.example.com';
const CLIENT = 'client-1';
const AUDIENCE = 'api.example.com';
const KID = 'key-1';

/** @return array{private: non-empty-string, public: non-empty-string} */
function keypair(): array
{
    static $pair = null;

    if ($pair !== null) {
        return $pair;
    }

    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]) ?: throw new RuntimeException('No key.');
    openssl_pkey_export($key, $private);
    $details = openssl_pkey_get_details($key);

    if (! is_string($private) || $private === '' || ! is_array($details) || ! is_string($details['key']) || $details['key'] === '') {
        throw new RuntimeException('The keypair could not be read.');
    }

    return $pair = ['private' => $private, 'public' => $details['key']];
}

/** People, publishing its discovery document and the suite's key, and answering whatever else is given. */
function people_answers(array $more = []): void
{
    $details = openssl_pkey_get_details(openssl_pkey_get_public(keypair()['public']) ?: throw new RuntimeException('No key.'));
    $encode = fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

    Http::fake($more + [
        ISSUER.'/.well-known/openid-configuration' => Http::response([
            'issuer' => ISSUER,
            'token_endpoint' => ISSUER.'/oauth/token',
            'jwks_uri' => ISSUER.'/oauth/jwks',
        ]),
        ISSUER.'/oauth/jwks' => Http::response(['keys' => [[
            'kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => KID,
            'n' => $encode($details['rsa']['n']), 'e' => $encode($details['rsa']['e']),
        ]]]),
    ]);
}

/** An access token People would issue to this API. */
function access_token(string $subject = 'user-1'): string
{
    $now = new DateTimeImmutable('-1 minute');

    return (new Builder(new JoseEncoder, ChainedFormatter::default()))
        ->withHeader('kid', KID)
        ->issuedBy(ISSUER)
        ->relatedTo($subject)
        ->permittedFor(CLIENT, AUDIENCE)
        ->issuedAt($now)
        ->canOnlyBeUsedAfter($now)
        ->expiresAt($now->modify('+15 minutes'))
        ->withClaim('scopes', ['orders.read'])
        ->withClaim('client_id', CLIENT)
        ->getToken(new Sha256, InMemory::plainText(keypair()['private']))
        ->toString();
}
