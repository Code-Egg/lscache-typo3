<?php

declare(strict_types=1);

namespace LiteSpeed\Lscache\Hook;

use LiteSpeed\Lscache\Configuration\ExtensionConfig;
use LiteSpeed\Lscache\Service\PurgeService;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Site\SiteFinder;
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

        $purgeService = $this->buildPurgeService($config);
        foreach ($this->normalizeCacheCmd($cacheCmd) as $command) {
            if ($this->handleCommand($command, $purgeService)) {
                continue;
            }
        }
    }

    private function handleCommand(mixed $command, PurgeService $purgeService): bool
    {
        if (is_int($command) || (is_string($command) && ctype_digit($command))) {
            $pageId = (int)$command;
            if ($pageId > 0) {
                $purgeService->purgePageId($pageId);
                return true;
            }
        }

        $value = trim((string)$command);
        if ($value === '') {
            return true;
        }

        $lower = strtolower($value);
        if ($lower === 'pages' || $lower === 'all') {
            $purgeService->purgeAllSites();
            return true;
        }

        if (preg_match('/^(tag|tags)[:=]/i', $value) === 1) {
            $list = preg_replace('/^(tag|tags)[:=]/i', '', $value);
            $tags = $this->splitTags($list);
            if ($tags !== []) {
                $purgeService->purgeTags($tags);
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

    private function buildConfig(): ExtensionConfig
    {
        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
        return new ExtensionConfig($extensionConfiguration);
    }

    private function buildPurgeService(ExtensionConfig $config): PurgeService
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(PurgeService::class);

        return new PurgeService($config, $siteFinder, $requestFactory, $logger);
    }
}
