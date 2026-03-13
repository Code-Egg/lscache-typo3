<?php

declare(strict_types=1);

namespace LiteSpeed\Lscache\Hook;

use LiteSpeed\Lscache\Configuration\ExtensionConfig;
use LiteSpeed\Lscache\Service\PurgeQueue;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class ClearCacheHook
{
    /**
     * Hook: clearCachePostProc
     */
    public function clearCachePostProc(array $params, DataHandler $dataHandler): void
    {
        $config = $this->buildConfig();
        if (!$config->isEnabled()) {
            return;
        }

        $cacheCmd = $params['cacheCmd'] ?? null;
        if ($cacheCmd === null || $cacheCmd === '') {
            return;
        }

        $commands = $this->normalizeCacheCmd($cacheCmd);
        $purgeAll = false;
        $tags = [];

        foreach ($commands as $command) {
            if ($this->isPurgeAll($command)) {
                $purgeAll = true;
                break;
            }

            $pageTags = $this->resolvePageTags($command, $config->getTagPrefix());
            if ($pageTags !== []) {
                $tags = array_merge($tags, $pageTags);
                continue;
            }

            $cmdTags = $this->resolveTagsFromCommand($command, $config->getTagPrefix());
            if ($cmdTags !== []) {
                $tags = array_merge($tags, $cmdTags);
            }
        }

        $queue = GeneralUtility::makeInstance(PurgeQueue::class);

        if ($purgeAll) {
            header('X-LiteSpeed-Purge: *');
            $queue->add(true);
            return;
        }

        $tags = array_unique($tags);
        if ($tags !== []) {
            $parts = array_map(static fn(string $t): string => 'tag=' . $t, $tags);
            header('X-LiteSpeed-Purge: public,' . implode(',', $parts));
            $queue->add(false, $tags);
        }
    }

    private function isPurgeAll(mixed $command): bool
    {
        $value = strtolower(trim((string)$command));
        return $value === 'pages' || $value === 'all';
    }

    private function resolvePageTags(mixed $command, string $prefix): array
    {
        if (!is_int($command) && !(is_string($command) && ctype_digit($command))) {
            return [];
        }

        $pageId = (int)$command;
        if ($pageId <= 0) {
            return [];
        }

        $tag = $prefix !== '' ? $prefix . '_page_' . $pageId : 'page_' . $pageId;
        return [$tag];
    }

    private function resolveTagsFromCommand(mixed $command, string $prefix): array
    {
        $value = trim((string)$command);
        if (!preg_match('/^(tag|tags)[:=]/i', $value)) {
            return [];
        }

        $list = preg_replace('/^(tag|tags)[:=]/i', '', $value);
        $parts = array_filter(array_map('trim', explode(',', (string)$list)));
        $result = [];
        foreach ($parts as $tag) {
            if ($prefix !== '' && !str_starts_with($tag, $prefix . '_')) {
                $tag = $prefix . '_' . $tag;
            }
            $result[] = $tag;
        }
        return $result;
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

    private function buildConfig(): ExtensionConfig
    {
        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
        return new ExtensionConfig($extensionConfiguration);
    }
}
