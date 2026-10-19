<?php

return [

    /*
    |--------------------------------------------------------------------------
    | People
    |--------------------------------------------------------------------------
    |
    | Where People is, and who this application is to it. Register the
    | application at People to get a client id, and a secret for a server-side
    | application.
    |
    */

    // People's address, exactly as its discovery document names it.
    'issuer' => env('PEOPLE_ISSUER', ''),

    'client_id' => env('PEOPLE_CLIENT_ID'),

    // Only for an application running on a server. Never ship it to a browser.
    'client_secret' => env('PEOPLE_CLIENT_SECRET'),

    // What this API is called in an access token's `aud`: its hostname. A
    // token naming another API, or none, is refused.
    'audience' => env('PEOPLE_AUDIENCE', parse_url((string) env('APP_URL'), PHP_URL_HOST)),

    // Seconds before a call to People is given up on.
    'timeout' => env('PEOPLE_TIMEOUT', 5),

    // Seconds a fetched key set and discovery document stay fresh.
    'keys_ttl' => env('PEOPLE_KEYS_TTL', 900),

    // The cache store for keys, discovery and the client's own token. Shared
    // across requests and processes; null is the default store.
    'cache' => env('PEOPLE_CACHE_STORE'),

    // What `people:sync` hands each identity event to: a class implementing
    // Oki\People\Laravel\Contracts\HandlesIdentityEvents.
    'events' => [
        'handler' => null,
    ],

];
