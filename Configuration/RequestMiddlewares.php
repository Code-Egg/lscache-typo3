<?php

declare(strict_types=1);

use LiteSpeed\Lscache\Middleware\Frontend\CacheHeadersMiddleware;
use LiteSpeed\Lscache\Middleware\Frontend\PurgeReceiverMiddleware;

return [
    'frontend' => [
        'litespeed/lscache-purge-receiver' => [
            'target' => PurgeReceiverMiddleware::class,
            'before' => [
                'typo3/cms-frontend/site',
            ],
        ],
        'litespeed/lscache-headers' => [
            'target' => CacheHeadersMiddleware::class,
            'after' => [
                'typo3/cms-frontend/tsfe',
            ],
        ],
    ],
];
