<?php

namespace MdDocsViewer\Http;

use MdDocsViewer\Config\ViewerConfig;
use MdDocsViewer\Support\MimeTypeResolver;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 包内静态资源输出 Action
 */
final class AssetAction
{
    /** @var ViewerConfig */
    private $config;
    /** @var ResponseFactoryInterface */
    private $responseFactory;
    /** @var string[] */
    private $allowed = ['viewer.css', 'viewer.js'];

    /**
     * @param ViewerConfig $config
     * @param ResponseFactoryInterface $responseFactory
     */
    public function __construct(ViewerConfig $config, ResponseFactoryInterface $responseFactory)
    {
        $this->config = $config;
        $this->responseFactory = $responseFactory;
    }

    /**
     * @param string $file
     * @return ResponseInterface
     */
    public function handle(string $file): ResponseInterface
    {
        // 1. 白名单文件名
        $file = basename(str_replace('\\', '/', rawurldecode($file)));
        if (!in_array($file, $this->allowed, true)) {
            return $this->responseFactory->createResponse(404);
        }
        // 2. 读取 dist 文件
        $fullPath = $this->config->getResourcesDir() . '/dist/' . $file;
        if (!is_file($fullPath)) {
            return $this->responseFactory->createResponse(404);
        }
        $content = file_get_contents($fullPath);
        if ($content === false) {
            return $this->responseFactory->createResponse(500);
        }
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $response = $this->responseFactory->createResponse(200);
        $response->getBody()->write($content);
        return $response
            ->withHeader('Content-Type', MimeTypeResolver::resolve($ext))
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }
}
