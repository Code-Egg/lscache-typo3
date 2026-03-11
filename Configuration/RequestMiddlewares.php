<?php

declare(strict_types=1);

use LiteSpeed\Lscache\Middleware\Frontend\CacheHeadersMiddleware;

return [
    'frontend' => [
        'litespeed/lscache-headers' => [
            'target' => CacheHeadersMiddleware::class,
            'after' => [
                'typo3/cms-frontend/tsfe',
            ],
        ],
    ],
];
