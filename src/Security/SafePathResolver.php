<?php

namespace MdDocsViewer\Security;

use MdDocsViewer\Config\ViewerConfig;
use MdDocsViewer\Exception\DocsViewerException;
use MdDocsViewer\Support\PathNormalizer;

/**
 * 文档路径 realpath 白名单校验
 */
final class SafePathResolver
{
    /** @var ViewerConfig */
    private $config;
    /** @var string|null */
    private $resolvedRoot;

    /**
     * @param ViewerConfig $config
     */
    public function __construct(ViewerConfig $config)
    {
        $this->config = $config;
    }

    /**
     * 获取文档根绝对路径
     *
     * @return string
     */
    public function getDocRoot(): string
    {
        if ($this->resolvedRoot !== null) {
            return $this->resolvedRoot;
        }
        $baseDir = realpath($this->config->docRoot);
        if ($baseDir === false || !is_dir($baseDir)) {
            throw new DocsViewerException('文档目录不存在: ' . $this->config->docRoot);
        }
        $this->resolvedRoot = rtrim(str_replace('\\', '/', $baseDir), '/');
        return $this->resolvedRoot;
    }

    /**
     * 解析相对路径为绝对路径（必须在文档根内）
     *
     * @param string $path 相对 docRoot 的路径
     * @param bool $mustBeFile
     * @return string
     */
    public function resolve(string $path, bool $mustBeFile = true): string
    {
        // 1. 规范化
        try {
            $path = PathNormalizer::normalizeRelative($path);
        } catch (\InvalidArgumentException $e) {
            throw new DocsViewerException($e->getMessage(), 0, $e);
        }
        // 2. realpath 白名单
        $baseDir = $this->getDocRoot();
        $fullPath = realpath($baseDir . '/' . $path);
        if ($fullPath === false) {
            throw new DocsViewerException('文档不存在: ' . $path);
        }
        $fullPath = str_replace('\\', '/', $fullPath);
        if (strpos($fullPath, $baseDir) !== 0) {
            throw new DocsViewerException('非法文档路径: ' . $path);
        }
        if ($mustBeFile && !is_file($fullPath)) {
            throw new DocsViewerException('文档不存在: ' . $path);
        }
        return $fullPath;
    }

    /**
     * 校验扩展名是否在白名单
     *
     * @param string $fullPath
     * @return string 小写扩展名
     */
    public function assertAllowedExtension(string $fullPath): string
    {
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        if (!in_array($ext, $this->config->allowedExtensions, true)) {
            throw new DocsViewerException('不允许的文件类型: ' . $ext);
        }
        return $ext;
    }
}
