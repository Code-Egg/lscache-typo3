<?php

declare(strict_types=1);

namespace LiteSpeed\Lscache\Hook;

use LiteSpeed\Lscache\Configuration\ExtensionConfig;
use LiteSpeed\Lscache\Service\PurgeService;
use TYPO3\CMS\Core\DataHandling\DataHandler;

final class ClearCacheHook
{
    public function __construct(
        private readonly ExtensionConfig $config,
        private readonly PurgeService $purgeService,
    ) {
    }

    /**
     * Hook: clearCachePostProc
     */
    public function clearCachePostProc(array $params, DataHandler $dataHandler): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $cacheCmd = $params['cacheCmd'] ?? null;
        if ($cacheCmd === null || $cacheCmd === '') {
            return;
        }

        foreach ($this->normalizeCacheCmd($cacheCmd) as $command) {
            if ($this->handleCommand($command)) {
                continue;
            }
        }
    }

    private function handleCommand(mixed $command): bool
    {
        if (is_int($command) || (is_string($command) && ctype_digit($command))) {
            $pageId = (int)$command;
            if ($pageId > 0) {
                $this->purgeService->purgePageId($pageId);
                return true;
            }
        }

        $value = trim((string)$command);
        if ($value === '') {
            return true;
        }

        $lower = strtolower($value);
        if ($lower === 'pages' || $lower === 'all') {
            $this->purgeService->purgeAllSites();
            return true;
        }

        if (preg_match('/^(tag|tags)[:=]/i', $value) === 1) {
            $list = preg_replace('/^(tag|tags)[:=]/i', '', $value);
            $tags = $this->splitTags($list);
            if ($tags !== []) {
                $this->purgeService->purgeTags($tags);
            }
            return true;
        }

        return false;
    }

    private function normalizeCacheCmd(mixed $cacheCmd): array
    {
        if (is_array($cacheCmd)) {
            $normalized = [];
            foreach ($cacheCmd as $item) {
                if (is_array($item)) {
                    $normalized = array_merge($normalized, $item);
                } else {
                    $normalized[] = $item;
                }
            }
            return $normalized;
        }

        if (is_string($cacheCmd) && str_contains($cacheCmd, ',')) {
            return array_map('trim', explode(',', $cacheCmd));
        }

        return [$cacheCmd];
    }

    private function splitTags(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', $value));
        $parts = array_filter($parts, static fn(string $item): bool => $item !== '');
        return array_values(array_unique($parts));
    }
}
