<?php

declare(strict_types=1);

namespace LiteSpeed\Lscache\Middleware\Frontend;

use LiteSpeed\Lscache\Configuration\ExtensionConfig;
use LiteSpeed\Lscache\Service\HeaderBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\Cache\CacheDataCollector;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;
use TYPO3\CMS\Frontend\Page\PageArguments;

final class CacheHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ExtensionConfig $config,
        private readonly HeaderBuilder $headerBuilder,
        private readonly Context $context,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (!$this->config->isEnabled()) {
            return $response;
        }

        if ($request->getUri()->getPath() === $this->config->getPurgePath()) {
            return $response;
        }

        if (!$this->isCacheCandidate($request, $response)) {
            return $this->withLiteSpeedHeader($response, 'no-cache');
        }

        $isLoggedIn = $this->isFrontendUserLoggedIn();
        $loggedInBehavior = $this->config->getLoggedInBehavior();
        if ($isLoggedIn && $loggedInBehavior === 'no-cache') {
            return $this->withLiteSpeedHeader($response, 'no-cache');
        }

        $cacheMode = $this->config->getCacheControl();
        if ($isLoggedIn && $loggedInBehavior === 'private') {
            $cacheMode = 'private';
        }

        if (!$response->hasHeader('X-LiteSpeed-Cache-Control')) {
            $maxAge = $this->resolveLifetime($request);
            if ($maxAge <= 0) {
                $maxAge = $this->config->getDefaultMaxAge();
            }
            $response = $response->withHeader(
                'X-LiteSpeed-Cache-Control',
                $this->headerBuilder->buildCacheControl($cacheMode, $maxAge)
            );
        }

        if (!$response->hasHeader('X-LiteSpeed-Tag')) {
            $tags = $this->buildTags($request, $cacheMode === 'private');
            if ($tags !== []) {
                $response = $response->withHeader('X-LiteSpeed-Tag', implode(',', $tags));
            }
        }

        if (!$response->hasHeader('X-LiteSpeed-Vary')) {
            $vary = $this->headerBuilder->buildVaryHeader($this->config->getVaryCookies());
            if ($vary !== null) {
                $response = $response->withHeader('X-LiteSpeed-Vary', $vary);
            }
        }

        return $response;
    }

    private function withLiteSpeedHeader(ResponseInterface $response, string $value): ResponseInterface
    {
        if ($response->hasHeader('X-LiteSpeed-Cache-Control')) {
            return $response;
        }

        return $response->withHeader('X-LiteSpeed-Cache-Control', $value);
    }

    private function isCacheCandidate(ServerRequestInterface $request, ResponseInterface $response): bool
    {
        if (!in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        $cacheInstruction = $request->getAttribute('frontend.cache.instruction');
        if ($cacheInstruction instanceof CacheInstruction && !$cacheInstruction->isCachingAllowed()) {
            return false;
        }

        $cacheControl = $response->getHeaderLine('Cache-Control');
        if ($cacheControl !== '' && preg_match('/no-cache|no-store/i', $cacheControl)) {
            return false;
        }

        return true;
    }

    private function isFrontendUserLoggedIn(): bool
    {
        try {
            return (bool)$this->context->getPropertyFromAspect('frontend.user', 'isLoggedIn');
        } catch (\Throwable) {
            return false;
        }
    }

    private function resolveLifetime(ServerRequestInterface $request): int
    {
        $collector = $request->getAttribute('frontend.cache.collector');
        if (!$collector instanceof CacheDataCollector) {
            return 0;
        }

        $lifetime = $collector->resolveLifetime();
        if (!is_int($lifetime) || $lifetime === PHP_INT_MAX) {
            return 0;
        }

        return max(0, $lifetime);
    }

    private function buildTags(ServerRequestInterface $request, bool $isPrivate): array
    {
        if ($isPrivate && !$this->config->tagPrivateResponses()) {
            return [];
        }

        $tags = [];
        $prefix = $this->config->getTagPrefix();

        foreach ($this->config->getExtraTags() as $tag) {
            $tags[] = $this->applyPrefix($prefix, $tag);
        }

        $site = $request->getAttribute('site');
        if ($site instanceof Site) {
            $tags[] = $this->applyPrefix($prefix, 'site_' . $site->getIdentifier());
        }

        if ($this->config->addPageIdTag()) {
            $pageId = $this->resolvePageId($request);
            if ($pageId > 0) {
                $tags[] = $this->applyPrefix($prefix, 'page_' . $pageId);
            }
        }

        $collector = $request->getAttribute('frontend.cache.collector');
        if ($collector instanceof CacheDataCollector) {
            foreach ($collector->getCacheTags() as $cacheTag) {
                $name = $this->extractTagName($cacheTag);
                if ($name !== '') {
                    $tags[] = $this->applyPrefix($prefix, $name);
                }
            }
        }

        return $this->headerBuilder->normalizeTags($tags, $this->config->getMaxTags(), $this->config->getMaxHeaderLength());
    }

    private function resolvePageId(ServerRequestInterface $request): int
    {
        $pageArguments = $request->getAttribute('routing');
        if ($pageArguments instanceof PageArguments) {
            return (int)$pageArguments->getPageId();
        }

        return 0;
    }

    private function extractTagName(mixed $cacheTag): string
    {
        if (is_object($cacheTag)) {
            if (property_exists($cacheTag, 'name')) {
                return (string)$cacheTag->name;
            }
            if (method_exists($cacheTag, 'getTag')) {
                return (string)$cacheTag->getTag();
            }
            if (method_exists($cacheTag, '__toString')) {
                return (string)$cacheTag;
            }
        }

        if (is_string($cacheTag)) {
            return $cacheTag;
        }

        return '';
    }

    private function applyPrefix(string $prefix, string $tag): string
    {
        if ($prefix === '') {
            return $tag;
        }

        return $prefix . '_' . $tag;
    }
}
