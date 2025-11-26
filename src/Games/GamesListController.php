<?php

namespace LadyByron\Games\Games;

use Flarum\Foundation\Paths;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use LadyByron\Games\Engine\EngineChain;
use LadyByron\Games\Engine\TwineEngine;
use LadyByron\Games\Engine\InkEngine;

final class GamesListController implements RequestHandlerInterface
{
    public function __construct(
        protected UrlGenerator $url,
        protected Paths $paths
    ) {}

    public function handle(Request $request): Response
    {
        $actor = RequestUtil::getActor($request);

        // 你可以在这里选择是否要求登录。
        // 如果希望游戏库也要登录，保留这段；否则去掉。
        if ($actor->isGuest()) {
            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        $gamesDir = $this->paths->storage . DIRECTORY_SEPARATOR . 'games';
        if (!is_dir($gamesDir)) {
            return new JsonResponse(['items' => []], 200);
        }

        // 用 EngineChain 判定 engine / shape，可选
        $engineChain = new EngineChain([
            new InkEngine($gamesDir),
            new TwineEngine($gamesDir),
        ]);

        $items = [];

        // 1) 扫目录形式的游戏：storage/games/{slug}/index.html
        foreach (scandir($gamesDir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $gamesDir . DIRECTORY_SEPARATOR . $entry;

            // 目录形式
            if (is_dir($full) && preg_match('~^[a-z0-9_-]+$~i', $entry)) {
                $slug = $entry;

                $resolved = $engineChain->locate($slug);
                if (!$resolved->exists) {
                    continue;
                }

                $metaFile = $full . DIRECTORY_SEPARATOR . 'meta.json';
                $meta = $this->loadMeta($metaFile);

                $items[] = $this->buildItem($slug, $meta, $resolved);
            }
        }

        // 2) legacy 单文件形式：storage/games/{slug}.html
        foreach (scandir($gamesDir) as $entry) {
            if (!preg_match('~^[a-z0-9_-]+\.html$~i', $entry)) {
                continue;
            }

            $slug = substr($entry, 0, -5); // 去掉 ".html"
            // 若已经被目录形式覆盖，就跳过，避免重复
            if (Arr::first($items, fn ($i) => $i['slug'] === $slug)) {
                continue;
            }

            $resolved = $engineChain->locate($slug);
            if (!$resolved->exists) {
                continue;
            }

            $metaFile = $gamesDir . DIRECTORY_SEPARATOR . $slug . '.json';
            $meta = $this->loadMeta($metaFile);

            $items[] = $this->buildItem($slug, $meta, $resolved);
        }

        // 按 title 排序（或者按 slug / 修改时间排序都可以）
        usort($items, fn ($a, $b) => strcmp($a['title'], $b['title']));

        return new JsonResponse(['items' => $items], 200);
    }

    /**
     * 从 meta.json 读取并简单校验。
     */
    private function loadMeta(string $metaFile): array
    {
        if (!is_file($metaFile) || !is_readable($metaFile)) {
            return [];
        }

        $raw = @file_get_contents($metaFile);
        if ($raw === false) {
            return [];
        }

        $json = json_decode($raw, true);
        return is_array($json) ? $json : [];
    }

    /**
     * 统一构建前端要吃的结构。
     */
    private function buildItem(string $slug, array $meta, $resolved): array
    {
        // 允许 meta 覆盖标题等，没写则用 slug 兜底
        $title       = (string) ($meta['title']       ?? $slug);
        $subtitle    = (string) ($meta['subtitle']    ?? '');
        $author      = (string) ($meta['author']      ?? 'Unknown');
        $size        = (string) ($meta['size']        ?? '');
        $status      = (string) ($meta['status']      ?? '');
        $description = (string) ($meta['description'] ?? '');
        $length      = (int)    ($meta['length']      ?? 3);
        $tags        = isset($meta['tags']) && is_array($meta['tags']) ? array_values($meta['tags']) : [];
        $color       = (string) ($meta['color']       ?? 'text-amber-500');
        $stripColor  = (string) ($meta['stripColor']  ?? 'bg-amber-600');

        $length = max(1, min(5, $length)); // 限制到 1–5

        // 统一给一个 playUrl，前端直接用
        $playUrl = $this->url
            ->to('forum')
            ->route('ladybyron-games.play', ['slug' => $slug]);

        return [
            'slug'       => $slug,
            'title'      => $title,
            'subtitle'   => $subtitle,
            'author'     => $author,
            'size'       => $size,
            'length'     => $length,
            'status'     => $status,
            'description'=> $description,
            'tags'       => $tags,
            'color'      => $color,
            'stripColor' => $stripColor,
            'engine'     => $resolved->engine,
            'shape'      => $resolved->shape,
            'playUrl'    => $playUrl,
        ];
    }
}
