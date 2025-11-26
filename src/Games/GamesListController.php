<?php

namespace LadyByron\Games\Controllers;

use Flarum\Foundation\Paths;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;

final class GameListController implements RequestHandlerInterface
{
    public function __construct(
        protected Paths $paths,
        protected UrlGenerator $url
    ) {}

    public function handle(Request $request): Response
    {
        $actor = RequestUtil::getActor($request);

        // 和其他 API 一致：未登录重定向论坛首页
        if ($actor->isGuest()) {
            return new RedirectResponse($this->url->to('forum')->base(), 302);
        }

        // /var/www/html/storage/games
        $gamesDir = $this->paths->storage . DIRECTORY_SEPARATOR . 'games';

        if (!is_dir($gamesDir) || !is_readable($gamesDir)) {
            return new JsonResponse(['items' => []], 200);
        }

        $items = [];
        $id    = 1;

        // 只看「子目录里的 meta.json」
        // 形如 /storage/.../games/*/meta.json
        $pattern = $gamesDir . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'meta.json';

        foreach (glob($pattern) as $metaFile) {
            if (!is_file($metaFile) || !is_readable($metaFile)) {
                continue;
            }

            // 目录名就是 slug 的基础
            $dir  = dirname($metaFile);
            $slug = basename($dir);

            // 跳过特殊目录（即便有 meta 也不当成游戏）
            if (in_array($slug, ['assets', 'ping'], true)) {
                continue;
            }
            if ($slug === '' || $slug[0] === '.') {
                continue;
            }

            $raw = @file_get_contents($metaFile);
            if ($raw === false) {
                continue;
            }

            $meta = json_decode($raw, true);
            if (!is_array($meta)) {
                continue;
            }

            // ---- 安全读取字段 + 默认值 ----
            $slugMeta = (string)($meta['slug'] ?? $slug);
            if ($slugMeta === '' || !preg_match('~^[a-z0-9_-]+$~i', $slugMeta)) {
                $slugMeta = $slug;
            }

            $title       = (string)($meta['title'] ?? strtoupper($slugMeta));
            $subtitle    = (string)($meta['subtitle'] ?? 'DATA CARTRIDGE');
            $author      = (string)($meta['author'] ?? 'UNKNOWN');
            $size        = (string)($meta['size'] ?? 'N/A');
            $length      = (int)($meta['length'] ?? 1);
            $length      = max(1, min($length, 5)); // 1~5
            $status      = (string)($meta['status'] ?? 'READY');
            $description = (string)($meta['description'] ?? '');

            $tags = $meta['tags'] ?? [];
            if (!is_array($tags)) {
                $tags = [];
            }

            $color      = (string)($meta['color'] ?? 'text-amber-500');
            $stripColor = (string)($meta['stripColor'] ?? 'bg-amber-600');

            $playUrl = (string)($meta['playUrl'] ?? '');
            if ($playUrl === '') {
                $playUrl = '/play/' . $slugMeta;
            }

            $items[] = [
                'id'          => $id++,
                'slug'        => $slugMeta,
                'title'       => $title,
                'subtitle'    => $subtitle,
                'author'      => $author,
                'size'        => $size,
                'length'      => $length,
                'status'      => $status,
                'description' => $description,
                'tags'        => $tags,
                'color'       => $color,
                'stripColor'  => $stripColor,
                'playUrl'     => $playUrl,
            ];
        }

        // 可选：按 title 排序，保证顺序稳定
        usort($items, fn (array $a, array $b) => strcmp($a['title'], $b['title']));

        return new JsonResponse(['items' => $items], 200);
    }
}

