<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Token
    |--------------------------------------------------------------------------
    |
    | MachShip authenticates every request with a `token` header. Tokens are
    | created against a MachShip user and inherit that user's permissions.
    | MachShip has no sandbox host: create the token on a user that is in
    | "test mode" to create test consignments that never reach a carrier.
    |
    */

    'token' => env('MACHSHIP_TOKEN'),

    'base_url' => env('MACHSHIP_BASE_URL', 'https://live.machship.com'),

    /*
    |--------------------------------------------------------------------------
    | Default Company
    |--------------------------------------------------------------------------
    |
    | Optional MachShip company ID applied to route and consignment requests
    | that don't set one. Leave null to use the token user's own company.
    |
    */

    'company_id' => env('MACHSHIP_COMPANY_ID'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Options
    |--------------------------------------------------------------------------
    |
    | Retries only apply to GET requests (and to connection failures), so a
    | create call is never sent twice and duplicate consignments can't occur.
    |
    */

    'timeout' => (int) env('MACHSHIP_TIMEOUT', 30),

    'connect_timeout' => (int) env('MACHSHIP_CONNECT_TIMEOUT', 10),

    'retry' => [
        'times' => (int) env('MACHSHIP_RETRY_TIMES', 2),
        'sleep_ms' => (int) env('MACHSHIP_RETRY_SLEEP_MS', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Label Storage
    |--------------------------------------------------------------------------
    |
    | Default filesystem disk and directory used by Document::store().
    |
    */

    'labels' => [
        'disk' => env('MACHSHIP_LABEL_DISK'),
        'path' => env('MACHSHIP_LABEL_PATH', 'machship/labels'),
        'print_a4' => (bool) env('MACHSHIP_LABEL_PRINT_A4', false),
    ],

];
