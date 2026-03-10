<?php

declare(strict_types=1);

namespace LiteSpeed\Lscache\Middleware\Frontend;

use LiteSpeed\Lscache\Configuration\ExtensionConfig;
use LiteSpeed\Lscache\Service\HeaderBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;

final class PurgeEndpointMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ExtensionConfig $config,
        private readonly HeaderBuilder $headerBuilder,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->config->isEnabled()) {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();
        if ($path !== $this->config->getPurgePath()) {
            return $handler->handle($request);
        }

        $token = $this->config->getPurgeToken();
        if ($token === '') {
            return new Response(404, ['Content-Type' => 'text/plain'], 'Not Found');
        }

        $queryParams = $request->getQueryParams();
        if (($queryParams['token'] ?? '') !== $token) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        if (!in_array($request->getMethod(), ['GET', 'POST'], true)) {
            return new Response(405, ['Allow' => 'GET, POST'], 'Method Not Allowed');
        }

        $purgeHeader = $this->buildPurgeHeader($queryParams);
        return new Response(200, [
            'Content-Type' => 'text/plain',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-LiteSpeed-Purge' => $purgeHeader,
        ], 'OK');
    }

    private function buildPurgeHeader(array $queryParams): string
    {
        if (!empty($queryParams['all'])) {
            return '*';
        }

        $tags = [];
        foreach (['tags', 'tag'] as $key) {
            if (!empty($queryParams[$key])) {
                $tags = array_merge($tags, explode(',', (string)$queryParams[$key]));
            }
        }

        $tags = $this->headerBuilder->normalizeTags($tags, 0, 0);
        if ($tags === []) {
            return '*';
        }

        $parts = ['public'];
        foreach ($tags as $tag) {
            $parts[] = 'tag=' . $tag;
        }

        return implode(',', $parts);
    }
}
