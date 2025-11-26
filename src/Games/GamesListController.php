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

        // 和其他 API 一致：未登录直接回论坛首页
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

        // 不当成游戏的目录
        $skip = ['assets', 'ping', '.', '..'];

        $dh = opendir($gamesDir);
        if ($dh === false) {
            return new JsonResponse(['items' => []], 200);
        }

        while (($entry = readdir($dh)) !== false) {
            if (in_array($entry, $skip, true)) {
                continue;
            }
            if ($entry[0] === '.') {
                continue;
            }

            $dir = $gamesDir . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($dir)) {
                continue;
            }

            // ⭐ 关键：只处理存在 meta.json 的子目录
            $metaFile = $dir . DIRECTORY_SEPARATOR . 'meta.json';
            if (!is_file($metaFile) || !is_readable($metaFile)) {
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
            $slug = basename($dir);
            $slugMeta = (string)($meta['slug'] ?? $slug);
            if ($slugMeta === '' || !preg_match('~^[a-z0-9_-]+$~i', $slugMeta)) {
                $slugMeta = $slug;
            }

            $title       = (string)($meta['title'] ?? strtoupper($slugMeta));
            $subtitle    = (string)($meta['subtitle'] ?? 'DATA CARTRIDGE');
            $author      = (string)($meta['author'] ?? 'UNKNOWN');
            $size        = (string)($meta['size'] ?? 'N/A');
            $length      = (int)($meta['length'] ?? 1);
            $length      = max(1, min($length, 5)); // 1~5 之间
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

        closedir($dh);

        // 稳定排序（按标题）
        usort($items, fn (array $a, array $b) => strcmp($a['title'], $b['title']));

        return new JsonResponse(['items' => $items], 200);
    }
}
