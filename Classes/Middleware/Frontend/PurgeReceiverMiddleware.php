<?php

declare(strict_types=1);

namespace LiteSpeed\Lscache\Middleware\Frontend;

use LiteSpeed\Lscache\Configuration\ExtensionConfig;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;

/**
 * Intercepts POST purge requests sent by PurgeSender.
 *
 * Since this is a frontend request (not the backend), LiteSpeed reliably reads
 * the X-LiteSpeed-Purge header from the response and purges the cache.
 * POST requests are never served from LiteSpeed cache, so PHP always handles them.
 */
final class PurgeReceiverMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ExtensionConfig $config,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->config->isEnabled() || $request->getMethod() !== 'POST') {
            return $handler->handle($request);
        }

        $purgeValue = $request->getHeaderLine('X-LiteSpeed-Purge-Request');
        $token = $request->getHeaderLine('X-LiteSpeed-Purge-Token');

        if ($purgeValue === '' || $token === '') {
            return $handler->handle($request);
        }

        $encryptionKey = $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? '';
        $expected = hash_hmac('sha256', $purgeValue, $encryptionKey);

        if (!hash_equals($expected, $token)) {
            error_log('[lscache] PurgeReceiverMiddleware: invalid token for purgeValue=' . $purgeValue);
            return $handler->handle($request);
        }

        error_log('[lscache] PurgeReceiverMiddleware: sending X-LiteSpeed-Purge=' . $purgeValue);
        return (new Response(204))->withHeader('X-LiteSpeed-Purge', $purgeValue);
    }
}
