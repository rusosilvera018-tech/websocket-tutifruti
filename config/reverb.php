<?php

return [
    'default' => 'reverb',

    'servers' => [
        'reverb' => [
            'host' => '0.0.0.0',
            'port' => 8080,
            'hostname' => 'localhost',
            'options' => [
                'tls' => [],
            ],
            'scaling' => [
                'enabled' => false,
            ],
            'pulse_ingest_interval' => 15,
            'telescope_ingest_interval' => 15,
        ],
    ],

    'apps' => [
        'provider' => 'config',
        'apps' => [
            [
                'app_id' => '622505',
                'key' => 'uoj2o6k8snqvfgox3ewd',
                'secret' => 'sa98tgrijzlm7janzkhr',
                'slug' => 'emisor-receptor',
                'allowed_origins' => ['*'],
                'ping_interval' => 60,         // <-- Agrega esto
                'activity_timeout' => 30,      // <-- Agrega esto
                'max_connections' => null,     // <-- Agrega esto
                'max_message_size' => 10000,   // <-- Agrega esto
            ],
        ],
    ],
];
