<?php

namespace LadyByron\Game\Games;

use Flarum\Foundation\Paths;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class GamesPageController implements RequestHandlerInterface
{
    public function __construct(
        private Paths $paths,
        private SettingsRepositoryInterface $settings,
        private FilesystemFactory $filesystemFactory
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $file = $this->paths->storage . DIRECTORY_SEPARATOR . 'games' . DIRECTORY_SEPARATOR . 'index.html';

        if (!is_file($file) || !is_readable($file)) {
            return new HtmlResponse('Games page not found', 404);
        }

        $html = @file_get_contents($file);
        if ($html === false) {
            return new HtmlResponse('Failed to load', 500);
        }

        // 注入 <base> 标签，使相对路径资源走 /assets/games/ 直出
        $assetsUrl = rtrim($this->filesystemFactory->disk('flarum-assets')->url('games'), '/') . '/';
        $baseTag   = '<base href="' . htmlspecialchars($assetsUrl, ENT_QUOTES, 'UTF-8') . '">';
        $baseCount = 0;
        $html = preg_replace('~(<head[^>]*>)~i', '$1' . $baseTag, $html, 1, $baseCount);
        if ($baseCount === 0) {
            $html = $baseTag . $html;
        }

        $faviconTag = $this->buildFaviconTag();
        if ($faviconTag) {
            $html = preg_replace('~</head>~i', $faviconTag . '</head>', $html, 1);
        }

        return new HtmlResponse($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function buildFaviconTag(): ?string
    {
        $path = $this->settings->get('favicon_path');
        if (!$path) {
            return null;
        }

        $url = $this->filesystemFactory->disk('flarum-assets')->url($path);
        return '<link rel="icon" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">';
    }
}
