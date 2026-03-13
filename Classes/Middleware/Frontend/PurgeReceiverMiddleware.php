<?php

declare(strict_types=1);

namespace LiteSpeed\Lscache\Middleware\Frontend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;

/**
 * Intercepts POST purge requests sent by PurgeSender and returns
 * X-LiteSpeed-Purge on a frontend response that LiteSpeed always processes.
 * Has no constructor dependencies to survive any DI container state.
 */
final class PurgeReceiverMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() !== 'POST') {
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
            return $handler->handle($request);
        }

        return (new Response(204))->withHeader('X-LiteSpeed-Purge', $purgeValue);
    }
}
