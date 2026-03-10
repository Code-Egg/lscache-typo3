<?php

declare(strict_types=1);

namespace LiteSpeed\Lscache\EventListener;

use LiteSpeed\Lscache\Configuration\ExtensionConfig;
use LiteSpeed\Lscache\Service\PurgeService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\Event\CacheFlushEvent;

final class CacheFlushEventListener
{
    public function __construct(
        private readonly ExtensionConfig $config,
        private readonly PurgeService $purgeService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(CacheFlushEvent $event): void
    {
        if (!$this->config->isEnabled() || !$this->config->purgeOnCacheFlush()) {
            return;
        }

        if (!$this->shouldPurge($event)) {
            return;
        }

        $result = $this->purgeService->purgeAllSites();
        if ($result['failed'] > 0) {
            $this->logger->warning('LSCache purge completed with errors.', $result);
        }
    }

    private function shouldPurge(CacheFlushEvent $event): bool
    {
        if (!method_exists($event, 'getGroups')) {
            return true;
        }

        $groups = $event->getGroups();
        if ($groups === [] || $groups === null) {
            return true;
        }

        $groups = array_map('strtolower', $groups);
        return in_array('pages', $groups, true) || in_array('all', $groups, true);
    }
}
