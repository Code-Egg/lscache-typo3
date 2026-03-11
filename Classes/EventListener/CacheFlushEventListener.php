<?php

declare(strict_types=1);

namespace LiteSpeed\Lscache\EventListener;

use LiteSpeed\Lscache\Configuration\ExtensionConfig;
use TYPO3\CMS\Core\Cache\Event\CacheFlushEvent;

final class CacheFlushEventListener
{
    public function __construct(
        private readonly ExtensionConfig $config,
    ) {
    }

    public function __invoke(CacheFlushEvent $event): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        if (!$this->shouldPurge($event)) {
            return;
        }

        header('X-LiteSpeed-Purge: *');
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
