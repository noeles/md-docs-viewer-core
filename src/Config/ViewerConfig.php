<?php

namespace MdDocsViewer\Config;

/**
 * 文档预览器运行时配置
 */
final class ViewerConfig
{
    /** @var string 文档根目录绝对路径 */
    public $docRoot;
    /** @var string URL 前缀，如 /docs */
    public $routePrefix = '/docs';
    /** @var string 预览页标题 */
    public $title = '文档预览';
    /** @var string[] 允许通过 files 路由输出的扩展名 */
    public $allowedExtensions = ['md', 'drawio', 'xml', 'png', 'jpg', 'jpeg', 'gif', 'svg'];
    /** @var string[] 扫描时跳过的 glob 模式（相对 docRoot） */
    public $skipPatterns = ['_template/*', '_*'];
    /** @var bool 为 true 时仅 dev 环境可访问（由 isDev 回调判定） */
    public $devOnly = false;
    /** @var callable|null bool(ServerRequestInterface $request): bool */
    public $accessGuard;
    /** @var callable bool(): bool 判定是否开发环境 */
    public $isDev;
    /** @var array<string, bool> */
    public $features = [
        'mindmap' => true,
        'mermaid' => true,
        'drawio' => true,
        'lightbox' => true,
        'toc' => true,
    ];
    /** @var CdnConfig|null */
    public $cdn;

    /**
     * @param string $docRoot
     */
    public function __construct(string $docRoot)
    {
        $this->docRoot = $docRoot;
        $this->isDev = static function (): bool {
            return (getenv('APP_ENV') ?: 'production') === 'dev'
                || (getenv('APP_ENV') ?: '') === 'local';
        };
    }

    /**
     * 从数组构建配置
     *
     * @param array<string, mixed> $config
     * @return self
     */
    public static function fromArray(array $config): self
    {
        if (empty($config['docRoot'])) {
            throw new \InvalidArgumentException('ViewerConfig requires docRoot');
        }
        $instance = new self((string)$config['docRoot']);
        if (isset($config['routePrefix'])) {
            $instance->routePrefix = self::normalizePrefix((string)$config['routePrefix']);
        }
        if (isset($config['title'])) {
            $instance->title = (string)$config['title'];
        }
        if (isset($config['allowedExtensions'])) {
            $instance->allowedExtensions = array_values($config['allowedExtensions']);
        }
        if (isset($config['skipPatterns'])) {
            $instance->skipPatterns = array_values($config['skipPatterns']);
        }
        if (isset($config['devOnly'])) {
            $instance->devOnly = (bool)$config['devOnly'];
        }
        if (isset($config['features'])) {
            $instance->features = array_merge($instance->features, $config['features']);
        }
        if (isset($config['cdn'])) {
            $instance->cdn = CdnConfig::fromArray($config['cdn']);
        }
        if (isset($config['accessGuard']) && is_callable($config['accessGuard'])) {
            $instance->accessGuard = $config['accessGuard'];
        }
        if (isset($config['isDev']) && is_callable($config['isDev'])) {
            $instance->isDev = $config['isDev'];
        }
        return $instance;
    }

    /**
     * 规范化路由前缀
     *
     * @param string $prefix
     * @return string
     */
    public static function normalizePrefix(string $prefix): string
    {
        $prefix = '/' . trim(str_replace('\\', '/', $prefix), '/');
        return $prefix === '/' ? '/docs' : $prefix;
    }

    /**
     * 获取 CDN 配置（带默认值）
     *
     * @return CdnConfig
     */
    public function getCdn(): CdnConfig
    {
        return $this->cdn ?? new CdnConfig();
    }

    /**
     * 包内资源目录绝对路径
     *
     * @return string
     */
    public function getResourcesDir(): string
    {
        return dirname(__DIR__, 2) . '/resources';
    }
}
