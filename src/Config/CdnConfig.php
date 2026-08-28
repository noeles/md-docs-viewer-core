<?php

namespace MdDocsViewer\Config;

/**
 * 第三方前端库 CDN 地址配置
 */
final class CdnConfig
{
    /** @var string */
    public $marked;
    /** @var string */
    public $mermaid;
    /** @var string */
    public $drawio;

    /**
     * @param string $marked marked.js URL
     * @param string $mermaid mermaid.js URL
     * @param string $drawio draw.io viewer URL
     */
    public function __construct(
        string $marked = 'https://cdn.jsdelivr.net/npm/marked@12.0.2/marked.min.js',
        string $mermaid = 'https://cdn.jsdelivr.net/npm/mermaid@10.9.0/dist/mermaid.min.js',
        string $drawio = 'https://viewer.diagrams.net/js/viewer-static.min.js'
    ) {
        $this->marked = $marked;
        $this->mermaid = $mermaid;
        $this->drawio = $drawio;
    }

    /**
     * 从数组构建 CDN 配置
     *
     * @param array<string, string> $config
     * @return self
     */
    public static function fromArray(array $config): self
    {
        return new self(
            $config['marked'] ?? 'https://cdn.jsdelivr.net/npm/marked@12.0.2/marked.min.js',
            $config['mermaid'] ?? 'https://cdn.jsdelivr.net/npm/mermaid@10.9.0/dist/mermaid.min.js',
            $config['drawio'] ?? 'https://viewer.diagrams.net/js/viewer-static.min.js'
        );
    }
}
