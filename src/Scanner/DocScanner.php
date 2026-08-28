<?php

namespace MdDocsViewer\Scanner;

use MdDocsViewer\Config\ViewerConfig;
use MdDocsViewer\Exception\DocsViewerException;

/**
 * 递归扫描文档目录，收集可预览 Markdown 列表
 */
final class DocScanner
{
    /** @var ViewerConfig */
    private $config;

    /**
     * @param ViewerConfig $config
     */
    public function __construct(ViewerConfig $config)
    {
        $this->config = $config;
    }

    /**
     * 扫描并返回文档树
     *
     * @return array<int, array{title: string, path: string, group: string}>
     */
    public function scan(): array
    {
        // 1. 定位文档根
        $baseDir = $this->resolveDocRoot();
        $result = [];
        // 2. 深度优先扫描 .md
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'md') {
                continue;
            }
            $fullPath = $fileInfo->getPathname();
            $relPath = ltrim(str_replace('\\', '/', substr($fullPath, strlen($baseDir))), '/');
            if ($relPath === '' || $this->shouldSkip($relPath)) {
                continue;
            }
            $group = dirname($relPath);
            if ($group === '.') {
                $group = '';
            }
            $title = preg_replace('/\.md$/i', '', basename($relPath));
            $result[] = [
                'title' => $title,
                'path' => $relPath,
                'group' => $group,
            ];
        }
        // 3. 排序
        usort($result, static function (array $a, array $b): int {
            $groupCmp = strcmp($a['group'], $b['group']);
            if ($groupCmp !== 0) {
                return $groupCmp;
            }
            return strcmp($a['title'], $b['title']);
        });
        return $result;
    }

    /**
     * 判断路径是否在文档列表中
     *
     * @param array<int, array{title: string, path: string, group: string}> $docTree
     * @param string $path
     * @return bool
     */
    public function isListed(array $docTree, string $path): bool
    {
        foreach ($docTree as $item) {
            if (($item['path'] ?? '') === $path) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return string
     */
    private function resolveDocRoot(): string
    {
        $baseDir = realpath($this->config->docRoot);
        if ($baseDir === false || !is_dir($baseDir)) {
            throw new DocsViewerException('文档目录不存在: ' . $this->config->docRoot);
        }
        return rtrim(str_replace('\\', '/', $baseDir), '/');
    }

    /**
     * @param string $relPath
     * @return bool
     */
    private function shouldSkip(string $relPath): bool
    {
        foreach ($this->config->skipPatterns as $pattern) {
            if (fnmatch($pattern, $relPath, FNM_PATHNAME)) {
                return true;
            }
            if (fnmatch($pattern, basename($relPath))) {
                return true;
            }
        }
        return false;
    }
}
