<?php

return [
    'influx' => [
        'url' => env('INFLUX_URL'),
        'username' => env('INFLUX_USERNAME'),
        'password' => env('INFLUX_PASSWORD'),
        'database' => env('INFLUX_DATABASE'),
        'measurement' => env('INFLUX_MEASUREMENT'),
        'retention-policy' => env('INFLUX_RETENTION_POLICY'),
    ],
];
