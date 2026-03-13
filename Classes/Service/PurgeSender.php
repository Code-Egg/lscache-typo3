<?php

declare(strict_types=1);

namespace LiteSpeed\Lscache\Service;

use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Sends purge signals by making a POST request to the frontend.
 *
 * LiteSpeed never serves POST requests from cache, so PHP always handles them.
 * The PurgeReceiverMiddleware intercepts these requests and returns the
 * X-LiteSpeed-Purge header, which LiteSpeed processes to purge the cache.
 *
 * This works even when the entire site is served from LiteSpeed cache,
 * unlike the header() approach on backend responses.
 */
final class PurgeSender
{
    public function __construct(
        private readonly SiteFinder $siteFinder,
        private readonly RequestFactory $requestFactory,
    ) {
    }

    public function send(string $purgeValue): void
    {
        $encryptionKey = $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? '';
        $token = hash_hmac('sha256', $purgeValue, $encryptionKey);

        foreach ($this->siteFinder->getAllSites() as $site) {
            $base = $site->getBase();
            if ($base->getHost() === '') {
                continue;
            }
            try {
                $this->requestFactory->request((string)$base, 'POST', [
                    'headers' => [
                        'X-LiteSpeed-Purge-Request' => $purgeValue,
                        'X-LiteSpeed-Purge-Token' => $token,
                        'Connection' => 'close',
                    ],
                    'timeout' => 2,
                    'connect_timeout' => 1,
                    'http_errors' => false,
                    'verify' => false,
                ]);
            } catch (\Throwable) {
                // Ignore — PurgeQueue in CacheHeadersMiddleware acts as fallback
            }
        }
    }
}
