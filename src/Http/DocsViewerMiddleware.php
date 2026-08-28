<?php

namespace MdDocsViewer\Http;

use MdDocsViewer\Config\ViewerConfig;
use MdDocsViewer\Exception\DocsViewerException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 文档预览中间件：匹配 routePrefix 下 index / files / assets 路由
 */
final class DocsViewerMiddleware implements MiddlewareInterface
{
    /** @var ViewerConfig */
    private $config;
    /** @var RouteMatcher */
    private $matcher;
    /** @var IndexAction */
    private $indexAction;
    /** @var FileAction */
    private $fileAction;
    /** @var AssetAction */
    private $assetAction;
    /** @var ResponseFactoryInterface */
    private $responseFactory;

    /**
     * @param ViewerConfig $config
     * @param RouteMatcher $matcher
     * @param IndexAction $indexAction
     * @param FileAction $fileAction
     * @param AssetAction $assetAction
     * @param ResponseFactoryInterface $responseFactory
     */
    public function __construct(
        ViewerConfig $config,
        RouteMatcher $matcher,
        IndexAction $indexAction,
        FileAction $fileAction,
        AssetAction $assetAction,
        ResponseFactoryInterface $responseFactory
    ) {
        $this->config = $config;
        $this->matcher = $matcher;
        $this->indexAction = $indexAction;
        $this->fileAction = $fileAction;
        $this->assetAction = $assetAction;
        $this->responseFactory = $responseFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 1. 访问控制
        if (!$this->isAccessAllowed($request)) {
            return $this->errorResponse(403, 'Forbidden');
        }
        // 2. 路由匹配
        $path = $request->getUri()->getPath();
        $route = $this->matcher->match($path);
        if ($route === null) {
            return $handler->handle($request);
        }
        // 3. 分发
        try {
            if ($route['type'] === RouteMatcher::ROUTE_INDEX) {
                return $this->indexAction->handle($request);
            }
            if ($route['type'] === RouteMatcher::ROUTE_FILES) {
                if ($route['param'] === '') {
                    return $this->errorResponse(404, 'Not Found');
                }
                return $this->fileAction->handle($route['param']);
            }
            if ($route['type'] === RouteMatcher::ROUTE_ASSETS) {
                return $this->assetAction->handle($route['param']);
            }
        } catch (DocsViewerException $e) {
            return $this->errorResponse(404, $e->getMessage());
        }
        return $handler->handle($request);
    }

    /**
     * @param ServerRequestInterface $request
     * @return bool
     */
    private function isAccessAllowed(ServerRequestInterface $request): bool
    {
        if ($this->config->devOnly) {
            $isDev = $this->config->isDev;
            if (!$isDev()) {
                return false;
            }
        }
        if ($this->config->accessGuard !== null) {
            return (bool)call_user_func($this->config->accessGuard, $request);
        }
        return true;
    }

    /**
     * @param int $status
     * @param string $message
     * @return ResponseInterface
     */
    private function errorResponse(int $status, string $message): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write($message);
        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
