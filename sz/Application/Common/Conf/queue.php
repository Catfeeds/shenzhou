<?php

return [
    'QUEUE' => [
        'DRIVER' => 'redis',

        'REDIS' => [
            'QUEUE'         => 'queue',
            'DEFAULT_QUEUE' => 'queue',
            'PREFIX'        => 'queue:',
        ],

        'RETRY' => 3,
    ],
];