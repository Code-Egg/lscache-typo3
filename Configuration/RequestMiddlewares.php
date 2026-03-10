<?php

declare(strict_types=1);

use LiteSpeed\Lscache\Middleware\Frontend\CacheHeadersMiddleware;
use LiteSpeed\Lscache\Middleware\Frontend\PurgeEndpointMiddleware;

return [
    'frontend' => [
        'litespeed/lscache-purge-endpoint' => [
            'target' => PurgeEndpointMiddleware::class,
            'before' => [
                'typo3/cms-frontend/tsfe',
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
