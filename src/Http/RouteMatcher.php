<?php

namespace MdDocsViewer\Http;

use MdDocsViewer\Config\ViewerConfig;

/**
 * 匹配 docs 预览相关路由
 */
final class RouteMatcher
{
    public const ROUTE_INDEX = 'index';
    public const ROUTE_FILES = 'files';
    public const ROUTE_ASSETS = 'assets';

    /** @var ViewerConfig */
    private $config;
    /** @var string */
    private $prefix;

    /**
     * @param ViewerConfig $config
     */
    public function __construct(ViewerConfig $config)
    {
        $this->config = $config;
        $this->prefix = ViewerConfig::normalizePrefix($config->routePrefix);
    }

    /**
     * 匹配请求路径
     *
     * @param string $path 不含 query 的 URI path
     * @return array{type: string, param: string}|null
     */
    public function match(string $path): ?array
    {
        $path = '/' . trim($path, '/');
        if ($path === $this->prefix || $path === $this->prefix . '/') {
            return ['type' => self::ROUTE_INDEX, 'param' => ''];
        }
        $filesPrefix = $this->prefix . '/files/';
        if (strpos($path, $filesPrefix) === 0) {
            return ['type' => self::ROUTE_FILES, 'param' => substr($path, strlen($filesPrefix))];
        }
        $assetsPrefix = $this->prefix . '/assets/';
        if (strpos($path, $assetsPrefix) === 0) {
            return ['type' => self::ROUTE_ASSETS, 'param' => substr($path, strlen($assetsPrefix))];
        }
        return null;
    }

    /**
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }
}
