<?php

namespace MdDocsViewer\Tests;

use MdDocsViewer\Config\ViewerConfig;
use MdDocsViewer\DocsViewerFactory;
use MdDocsViewer\Http\RouteMatcher;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

final class DocsViewerMiddlewareTest extends TestCase
{
    /** @var string */
    private $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/md-docs-mw-' . uniqid('', true);
        mkdir($this->tmpDir);
        file_put_contents($this->tmpDir . '/hello.md', "# Hello\n\n```mermaid\nflowchart LR\n  A-->B\n```");
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpDir . '/hello.md');
        @rmdir($this->tmpDir);
    }

    public function testIndexRouteReturnsHtml(): void
    {
        $psr17 = new Psr17Factory();
        $config = ViewerConfig::fromArray([
            'docRoot' => $this->tmpDir,
            'routePrefix' => '/docs',
            'title' => 'Test',
        ]);
        $middleware = DocsViewerFactory::createMiddleware($config, $psr17);
        $request = (new ServerRequest('GET', 'http://localhost/docs'))
            ->withAttribute('docsBaseUrl', '');
        $fallback = new class($psr17) implements RequestHandlerInterface {
            /** @var Psr17Factory */
            private $factory;
            public function __construct(Psr17Factory $factory) { $this->factory = $factory; }
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return $this->factory->createResponse(418);
            }
        };
        $response = $middleware->process($request, $fallback);
        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('window.__DOCS__', $body);
        $this->assertStringContainsString('hello.md', $body);
    }

    public function testFileRouteReturnsMarkdown(): void
    {
        $psr17 = new Psr17Factory();
        $config = ViewerConfig::fromArray(['docRoot' => $this->tmpDir]);
        $middleware = DocsViewerFactory::createMiddleware($config, $psr17);
        $request = new ServerRequest('GET', 'http://localhost/docs/files/hello.md');
        $fallback = new class($psr17) implements RequestHandlerInterface {
            /** @var Psr17Factory */
            private $factory;
            public function __construct(Psr17Factory $factory) { $this->factory = $factory; }
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return $this->factory->createResponse(418);
            }
        };
        $response = $middleware->process($request, $fallback);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('# Hello', (string)$response->getBody());
    }

    public function testRouteMatcher(): void
    {
        $config = ViewerConfig::fromArray(['docRoot' => $this->tmpDir, 'routePrefix' => '/docs']);
        $matcher = new RouteMatcher($config);
        $this->assertSame(RouteMatcher::ROUTE_INDEX, $matcher->match('/docs')['type']);
        $this->assertSame('a/b.md', $matcher->match('/docs/files/a/b.md')['param']);
        $this->assertSame('viewer.js', $matcher->match('/docs/assets/viewer.js')['param']);
        $this->assertNull($matcher->match('/other'));
    }
}
