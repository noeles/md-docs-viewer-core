<?php

namespace MdDocsViewer;

use MdDocsViewer\Config\ViewerConfig;
use MdDocsViewer\Http\AssetAction;
use MdDocsViewer\Http\DocsViewerMiddleware;
use MdDocsViewer\Http\FileAction;
use MdDocsViewer\Http\IndexAction;
use MdDocsViewer\Http\RouteMatcher;
use MdDocsViewer\Scanner\DocScanner;
use MdDocsViewer\Security\SafePathResolver;
use MdDocsViewer\View\ShellRenderer;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * 组装文档预览相关依赖
 */
final class DocsViewerFactory
{
    /**
     * @param ViewerConfig $config
     * @param ResponseFactoryInterface $responseFactory
     * @return DocsViewerMiddleware
     */
    public static function createMiddleware(
        ViewerConfig $config,
        ResponseFactoryInterface $responseFactory
    ): DocsViewerMiddleware {
        $scanner = new DocScanner($config);
        $resolver = new SafePathResolver($config);
        $matcher = new RouteMatcher($config);
        $shell = new ShellRenderer($config, $scanner);
        return new DocsViewerMiddleware(
            $config,
            $matcher,
            new IndexAction($shell, $responseFactory),
            new FileAction($resolver, $responseFactory),
            new AssetAction($config, $responseFactory),
            $responseFactory
        );
    }
}
