<?php

namespace MdDocsViewer\Tests;

use MdDocsViewer\Config\ViewerConfig;
use MdDocsViewer\Exception\DocsViewerException;
use MdDocsViewer\Scanner\DocScanner;
use MdDocsViewer\Security\SafePathResolver;
use PHPUnit\Framework\TestCase;

final class DocScannerTest extends TestCase
{
    /** @var string */
    private $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/md-docs-viewer-' . uniqid('', true);
        mkdir($this->tmpDir);
        mkdir($this->tmpDir . '/group');
        file_put_contents($this->tmpDir . '/group/alpha.md', '# Alpha');
        file_put_contents($this->tmpDir . '/_hidden.md', '# Hidden');
        mkdir($this->tmpDir . '/_template');
        file_put_contents($this->tmpDir . '/_template/skip.md', '# Skip');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testScanSkipsUnderscoreFiles(): void
    {
        $config = ViewerConfig::fromArray(['docRoot' => $this->tmpDir]);
        $scanner = new DocScanner($config);
        $tree = $scanner->scan();
        $paths = array_column($tree, 'path');
        $this->assertContains('group/alpha.md', $paths);
        $this->assertNotContains('_hidden.md', $paths);
        $this->assertNotContains('_template/skip.md', $paths);
    }

    public function testSafePathResolverRejectsTraversal(): void
    {
        $config = ViewerConfig::fromArray(['docRoot' => $this->tmpDir]);
        $resolver = new SafePathResolver($config);
        $this->expectException(DocsViewerException::class);
        $resolver->resolve('../' . basename($this->tmpDir) . '/group/alpha.md');
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
