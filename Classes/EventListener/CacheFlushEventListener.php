<?php

declare(strict_types=1);

namespace LiteSpeed\Lscache\EventListener;

use LiteSpeed\Lscache\Configuration\ExtensionConfig;
use LiteSpeed\Lscache\Service\PurgeQueue;
use LiteSpeed\Lscache\Service\PurgeSender;
use TYPO3\CMS\Core\Cache\Event\CacheFlushEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class CacheFlushEventListener
{
    public function __construct(
        private readonly ExtensionConfig $config,
        private readonly PurgeQueue $purgeQueue,
    ) {
    }

    public function __invoke(CacheFlushEvent $event): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        header('X-LiteSpeed-Purge: *');
        try {
            $this->purgeQueue->add(true);
        } catch (\Throwable) {
        }
        /** @var PurgeSender $sender */
        $sender = GeneralUtility::makeInstance(PurgeSender::class);
        $sender->send('*');
    }
}
