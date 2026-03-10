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

    /**
     * @param Site[] $sites
     */
    public function purgeSites(array $sites): array
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
                $this->sendPurgeRequest($site, $token);
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

    private function sendPurgeRequest(Site $site, string $token): void
    {
        $baseUri = $site->getBase();
        $basePath = rtrim($baseUri->getPath(), '/');
        $purgePath = $basePath . $this->config->getPurgePath();

        $purgeUri = $baseUri
            ->withPath($purgePath)
            ->withQuery('');

        $this->requestFactory->request((string)$purgeUri, 'GET', [
            'query' => [
                'token' => $token,
                'all' => '1',
            ],
            'timeout' => $this->config->getPurgeTimeout(),
            'headers' => [
                'User-Agent' => 'TYPO3-LSCache-Purger',
            ],
        ]);
    }
}
