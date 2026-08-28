<?php

namespace MdDocsViewer\Http;

use MdDocsViewer\Security\SafePathResolver;
use MdDocsViewer\Support\MimeTypeResolver;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 文档源文件输出 Action
 */
final class FileAction
{
    /** @var SafePathResolver */
    private $resolver;
    /** @var ResponseFactoryInterface */
    private $responseFactory;

    /**
     * @param SafePathResolver $resolver
     * @param ResponseFactoryInterface $responseFactory
     */
    public function __construct(SafePathResolver $resolver, ResponseFactoryInterface $responseFactory)
    {
        $this->resolver = $resolver;
        $this->responseFactory = $responseFactory;
    }

    /**
     * @param string $relativePath URL 解码后的相对路径
     * @return ResponseInterface
     */
    public function handle(string $relativePath): ResponseInterface
    {
        // 1. 安全解析路径
        $relativePath = rawurldecode($relativePath);
        $fullPath = $this->resolver->resolve($relativePath, true);
        $ext = $this->resolver->assertAllowedExtension($fullPath);
        // 2. 读取并输出
        $content = file_get_contents($fullPath);
        if ($content === false) {
            return $this->responseFactory->createResponse(500);
        }
        $response = $this->responseFactory->createResponse(200);
        $response->getBody()->write($content);
        return $response->withHeader('Content-Type', MimeTypeResolver::resolve($ext));
    }
}
