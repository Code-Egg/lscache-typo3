<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'LiteSpeed Cache',
    'description' => 'LiteSpeed Cache integration for TYPO3.',
    'category' => 'fe',
    'author' => 'LiteSpeed Technologies',
    'author_email' => '',
    'author_company' => 'LiteSpeed Technologies',
    'state' => 'stable',
    'clearCacheOnLoad' => 1,
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-13.99.99',
            'php' => '8.1.0-8.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
