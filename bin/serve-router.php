<?php

/**
 * 内置 HTTP 服务器路由（由 md-docs-serve 通过 php -S 加载）
 */

if (PHP_SAPI !== 'cli-server') {
    return false;
}

$autoloadCandidates = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
    __DIR__ . '/../../../../vendor/autoload.php',
];
foreach ($autoloadCandidates as $file) {
    if (is_file($file)) {
        require $file;
        break;
    }
}

use MdDocsViewer\Config\ViewerConfig;
use MdDocsViewer\DocsViewerFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

$docRoot = getenv('MD_DOCS_ROOT') ?: '';
if ($docRoot === '' || !is_dir($docRoot)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'MD_DOCS_ROOT is not configured';
    return true;
}

$config = ViewerConfig::fromArray([
    'docRoot' => $docRoot,
    'routePrefix' => getenv('MD_DOCS_PREFIX') ?: '/docs',
    'title' => getenv('MD_DOCS_TITLE') ?: '文档预览',
    'devOnly' => false,
    'isDev' => static function (): bool {
        return true;
    },
]);

$psr17 = new Psr17Factory();
$middleware = DocsViewerFactory::createMiddleware($config, $psr17);
$creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);
$fallback = new class($psr17) implements \Psr\Http\Server\RequestHandlerInterface {
    /** @var Psr17Factory */
    private $factory;
    public function __construct(Psr17Factory $factory)
    {
        $this->factory = $factory;
    }
    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        return $this->factory->createResponse(404)->withBody(
            $this->factory->createStream('Not Found')
        );
    }
};

$request = $creator->fromGlobals();
$response = $middleware->process($request, $fallback);
http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header("{$name}: {$value}", false);
    }
}
echo (string)$response->getBody();
return true;
