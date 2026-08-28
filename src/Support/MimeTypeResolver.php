<?php

namespace MdDocsViewer\Support;

/**
 * 按扩展名解析 MIME 类型
 */
final class MimeTypeResolver
{
    /** @var array<string, string> */
    private static $map = [
        'md' => 'text/plain; charset=utf-8',
        'drawio' => 'application/xml; charset=utf-8',
        'xml' => 'application/xml; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
    ];

    /**
     * @param string $extension 不含点号
     * @return string
     */
    public static function resolve(string $extension): string
    {
        $ext = strtolower($extension);
        return self::$map[$ext] ?? 'application/octet-stream';
    }
}
