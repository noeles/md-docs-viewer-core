<?php

namespace MdDocsViewer\Support;

/**
 * 相对路径规范化工具
 */
final class PathNormalizer
{
    /**
     * 规范化相对路径并拒绝穿越
     *
     * @param string $path
     * @return string
     */
    public static function normalizeRelative(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');
        if ($path === '' || strpos($path, '..') !== false) {
            throw new \InvalidArgumentException('非法路径');
        }
        return $path;
    }

    /**
     * 规范化 MD 文档路径（用于导航）
     *
     * @param string $path
     * @return string 非法时返回空串
     */
    public static function normalizeMarkdownPath(string $path): string
    {
        try {
            $path = self::normalizeRelative($path);
        } catch (\InvalidArgumentException $e) {
            return '';
        }
        if (!preg_match('/\.md$/i', $path)) {
            return '';
        }
        return $path;
    }
}
