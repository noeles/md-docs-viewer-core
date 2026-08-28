<?php

namespace MdDocsViewer\View;

use MdDocsViewer\Config\ViewerConfig;
use MdDocsViewer\Exception\DocsViewerException;
use MdDocsViewer\Scanner\DocScanner;
use MdDocsViewer\Support\PathNormalizer;

/**
 * 渲染预览壳页面
 */
final class ShellRenderer
{
    /** @var ViewerConfig */
    private $config;
    /** @var DocScanner */
    private $scanner;

    /**
     * @param ViewerConfig $config
     * @param DocScanner $scanner
     */
    public function __construct(ViewerConfig $config, DocScanner $scanner)
    {
        $this->config = $config;
        $this->scanner = $scanner;
    }

    /**
     * 渲染 HTML 壳页
     *
     * @param string $baseUrl 应用 base path，如 /smart 或空串
     * @param string $initialDocQuery 可选 ?doc= 参数
     * @return string
     */
    public function render(string $baseUrl = '', string $initialDocQuery = ''): string
    {
        // 1. 扫描文档树
        $docTree = $this->scanner->scan();
        if ($docTree === []) {
            throw new DocsViewerException('文档目录为空或无可预览的 Markdown');
        }
        // 2. 校验初始文档
        $initialDoc = PathNormalizer::normalizeMarkdownPath($initialDocQuery);
        if ($initialDoc !== '' && !$this->scanner->isListed($docTree, $initialDoc)) {
            $initialDoc = '';
        }
        // 3. 计算 URL 基址
        $prefix = RoutePrefixBuilder::build($this->config->routePrefix, $baseUrl);
        $fileBase = $prefix . '/files/';
        $assetBase = $prefix . '/assets/';
        $cdn = $this->config->getCdn();
        $title = $this->config->title;
        $features = $this->config->features;
        // 4. 渲染模板
        $template = $this->config->getResourcesDir() . '/shell.html.php';
        if (!is_file($template)) {
            throw new DocsViewerException('Shell template not found');
        }
        ob_start();
        include $template;
        return (string)ob_get_clean();
    }
}

/**
 * 构建带 baseUrl 的路由前缀（内部辅助）
 */
final class RoutePrefixBuilder
{
    /**
     * @param string $routePrefix
     * @param string $baseUrl
     * @return string
     */
    public static function build(string $routePrefix, string $baseUrl): string
    {
        $base = rtrim(str_replace('\\', '/', $baseUrl), '/');
        $prefix = ViewerConfig::normalizePrefix($routePrefix);
        return $base . $prefix;
    }
}
