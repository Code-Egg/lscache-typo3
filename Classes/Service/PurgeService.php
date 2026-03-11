<?php

declare(strict_types=1);

namespace LiteSpeed\Lscache\Service;

use LiteSpeed\Lscache\Configuration\ExtensionConfig;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

final class PurgeService
{
    public function __construct(
        private readonly ExtensionConfig $config,
        private readonly SiteFinder $siteFinder,
        private readonly RequestFactory $requestFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function purgeAllSites(): array
    {
        $sites = $this->siteFinder->getAllSites();
        return $this->purgeSites($sites);
    }

    public function purgeSiteByIdentifier(string $identifier): array
    {
        $site = $this->siteFinder->getSiteByIdentifier($identifier);
        return $this->purgeSites([$site]);
    }

    public function purgeTags(array $tags): array
    {
        $tags = $this->prefixTags($tags);
        if ($tags === []) {
            return [
                'success' => 0,
                'failed' => 0,
                'errors' => ['No tags to purge.'],
            ];
        }

        $sites = $this->siteFinder->getAllSites();
        return $this->purgeSitesWithTags($sites, $tags);
    }

    public function purgePageId(int $pageId): array
    {
        if ($pageId <= 0) {
            return [
                'success' => 0,
                'failed' => 0,
                'errors' => ['Invalid page id for purge.'],
            ];
        }

        $tags = $this->buildPageTags($pageId);
        return $this->purgeTags($tags);
    }

    /**
     * @param Site[] $sites
     */
    public function purgeSites(array $sites): array
    {
        return $this->purgeSitesWithTags($sites, null);
    }

    private function purgeSitesWithTags(array $sites, ?array $tags): array
    {
        $token = $this->config->getPurgeToken();
        if ($token === '') {
            return [
                'success' => 0,
                'failed' => count($sites),
                'errors' => ['Purge token is not configured.'],
            ];
        }

        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($sites as $site) {
            try {
                $this->sendPurgeRequest($site, $token, $tags);
                $results['success']++;
            } catch (\Throwable $exception) {
                $results['failed']++;
                $message = sprintf('LSCache purge failed for site "%s": %s', $site->getIdentifier(), $exception->getMessage());
                $results['errors'][] = $message;
                $this->logger->warning($message, ['exception' => $exception]);
            }
        }

        return $results;
    }

    private function sendPurgeRequest(Site $site, string $token, ?array $tags): void
    {
        $baseUri = $site->getBase();
        $basePath = rtrim($baseUri->getPath(), '/');
        $purgePath = $basePath . $this->config->getPurgePath();

        $purgeUri = $baseUri
            ->withPath($purgePath)
            ->withQuery('');

        $query = ['token' => $token];
        if ($tags === null) {
            $query['all'] = '1';
        } else {
            $query['tags'] = implode(',', $tags);
        }

        $this->requestFactory->request((string)$purgeUri, 'GET', [
            'query' => $query,
            'timeout' => $this->config->getPurgeTimeout(),
            'headers' => [
                'User-Agent' => 'TYPO3-LSCache-Purger',
            ],
        ]);
    }

    private function buildPageTags(int $pageId): array
    {
        $tags = [
            'page_' . $pageId,
            'pageId_' . $pageId,
        ];

        return $this->prefixTags($tags);
    }

    private function prefixTags(array $tags): array
    {
        $prefix = $this->config->getTagPrefix();
        $normalized = [];

        foreach ($tags as $tag) {
            $tag = trim((string)$tag);
            if ($tag === '') {
                continue;
            }

            if ($prefix !== '' && !str_starts_with($tag, $prefix . '_')) {
                $tag = $prefix . '_' . $tag;
            }

            $normalized[$tag] = true;
        }

        return array_keys($normalized);
    }
}
