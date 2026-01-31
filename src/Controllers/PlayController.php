<?php

namespace LadyByron\Game\Controllers;

use Flarum\Foundation\Paths;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use LadyByron\Game\Engine\EngineChain;
use LadyByron\Game\Engine\TwineEngine;
use LadyByron\Game\Engine\InkEngine;

final class PlayController implements RequestHandlerInterface
{
    public function __construct(
        private Paths $paths,
        private SettingsRepositoryInterface $settings,
        private FilesystemFactory $filesystemFactory
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // 按官方推荐，从 route attributes 读取路由参数
        $rp   = (array) $request->getAttribute('routeParameters', []);
        $raw  = (string) Arr::get($rp, 'slug', '');
        $slug = trim(rawurldecode($raw), " \t\n\r\0\x0B/");

        if ($slug === '' || !preg_match('~^[a-z0-9_-]+$~i', $slug)) {
            return new HtmlResponse('Invalid slug', 400);
        }

        $actor = RequestUtil::getActor($request);

        // 统一使用 Paths::storage，避免硬编码 base_path()
        $gamesDir = $this->paths->storage . DIRECTORY_SEPARATOR . 'games';
        $index    = $gamesDir . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'index.html';
        $legacy   = $gamesDir . DIRECTORY_SEPARATOR . $slug . '.html';
        $file     = is_file($index) ? $index : $legacy;

        // 仅管理员可见的 debug，且不回显物理路径，防止目录结构泄露
        $qp    = $request->getQueryParams();
        $debug = !empty(Arr::get($qp, 'debug')) && $actor->isAdmin();
        if ($debug) {
            $shape = is_file($index) ? 'dir' : (is_file($legacy) ? 'legacy' : 'none');
            return new HtmlResponse(
                "DEBUG (admin only)\nslug={$slug}\nengine=" . $this->guessEngine($gamesDir, $slug) . "\nshape={$shape}",
                200,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        if (!is_file($file) || !is_readable($file)) {
            return new HtmlResponse('Game not found', 404);
        }

        $html = @file_get_contents($file);
        if ($html === false) {
            return new HtmlResponse('Failed to load', 500);
        }

        // 注入 <base> 标签，使相对路径资源走 /assets/games/{slug}/ 直出（OLS / nginx 静态文件，不经 PHP）
        $assetsUrl = rtrim($this->filesystemFactory->disk('flarum-assets')->url('games/' . $slug), '/') . '/';
        $baseTag   = '<base href="' . htmlspecialchars($assetsUrl, ENT_QUOTES, 'UTF-8') . '">';
        $baseCount = 0;
        $html = preg_replace('~(<head[^>]*>)~i', '$1' . $baseTag, $html, 1, $baseCount);
        if ($baseCount === 0) {
            $html = $baseTag . $html;
        }

        // 注入 favicon
        $faviconTag = $this->buildFaviconTag();
        if ($faviconTag) {
            $html = preg_replace('~</head>~i', $faviconTag . '</head>', $html, 1);
        }

        // 注入 ForumUser 与 ForumAuth（含 CSRF），供前端 fetch /playapi/* 使用
        if (empty(Arr::get($qp, 'noinject'))) {
            $username = (string) $actor->username;
            $userId   = (int) $actor->id;

            // 从会话取 CSRF：forum 管道下会有 session attribute
            $session = $request->getAttribute('session');
            $csrf    = (is_object($session) && method_exists($session, 'token')) ? (string) $session->token() : '';

            $auth = [
                'csrf'    => $csrf,
                'userId'  => $userId,
                'apiBase' => '/playapi', // 你的扩展为云存档注册的 API 前缀
            ];

            $inject = '<script>'
                . 'window.ForumUser=' . json_encode($username, JSON_UNESCAPED_UNICODE) . ';'
                . 'window.ForumAuth=' . json_encode($auth, JSON_UNESCAPED_UNICODE) . ';'
                . '</script>';

            $count = 0;
            $html  = preg_replace('~</body>~i', $inject . '</body>', $html, 1, $count);
            if ($count === 0) {
                $html = preg_replace('~</head>~i', $inject . '</head>', $html, 1, $count);
                if ($count === 0) $html = $inject . $html;
            }
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

    private function guessEngine(string $gamesDir, string $slug): string
    {
        // 与 EngineChain 行为一致的"只读"推断
        $chain = new EngineChain([
            new InkEngine($gamesDir),
            new TwineEngine($gamesDir),
        ]);

        $resolved = $chain->locate($slug);
        return $resolved->engine ?: 'unknown';
    }
}
