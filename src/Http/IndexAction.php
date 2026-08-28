<?php

namespace MdDocsViewer\Http;

use MdDocsViewer\View\ShellRenderer;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * 预览首页 Action
 */
final class IndexAction
{
    /** @var ShellRenderer */
    private $renderer;
    /** @var ResponseFactoryInterface */
    private $responseFactory;

    /**
     * @param ShellRenderer $renderer
     * @param ResponseFactoryInterface $responseFactory
     */
    public function __construct(ShellRenderer $renderer, ResponseFactoryInterface $responseFactory)
    {
        $this->renderer = $renderer;
        $this->responseFactory = $responseFactory;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // 1. 解析 baseUrl 与初始文档
        $baseUrl = (string)($request->getAttribute('docsBaseUrl') ?? '');
        $query = $request->getQueryParams();
        $initialDoc = isset($query['doc']) ? (string)$query['doc'] : '';
        // 2. 渲染壳页
        $html = $this->renderer->render($baseUrl, $initialDoc);
        $response = $this->responseFactory->createResponse(200);
        $response->getBody()->write($html);
        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0');
    }
}
