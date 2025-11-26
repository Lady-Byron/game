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

        // 游戏库需要登录
        if ($actor->isGuest()) {
            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        $gamesDir = $this->paths->storage . DIRECTORY_SEPARATOR . 'games';
        if (!is_dir($gamesDir)) {
            return new JsonResponse(['items' => []], 200);
        }

        // 引擎链：用来判断 twine / ink & 目录形态
        $engineChain = new EngineChain([
            new InkEngine($gamesDir),
            new TwineEngine($gamesDir),
        ]);

        $items    = [];
        $autoId   = 1;                 // 自动分配的 id 计数器
        $skipDirs = ['assets', 'ping']; // 永久跳过的目录名

        // 1) 目录形式的游戏：storage/games/{slug}/index.html
        foreach (scandir($gamesDir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $gamesDir . DIRECTORY_SEPARATOR . $entry;

            if (!is_dir($full)) {
                continue;
            }

            // slug 格式约束
            if (!preg_match('~^[a-z0-9_-]+$~i', $entry)) {
                continue;
            }

            // 显式跳过非游戏目录
            if (in_array(strtolower($entry), $skipDirs, true)) {
                continue;
            }

            $slug     = $entry;
            $resolved = $engineChain->locate($slug);
            if (!$resolved->exists) {
                continue;
            }

            $metaFile = $full . DIRECTORY_SEPARATOR . 'meta.json';
            $meta     = $this->loadMeta($metaFile);

            // 只接受有 meta.json 的游戏，避免 UNKNOWN 占位
            if (empty($meta)) {
                continue;
            }

            $items[] = $this->buildItem($slug, $meta, $resolved, $autoId);
        }

        // 2) legacy 单文件形式：storage/games/{slug}.html
        foreach (scandir($gamesDir) as $entry) {
            if (!preg_match('~^[a-z0-9_-]+\.html$~i', $entry)) {
                continue;
            }

            $slug = substr($entry, 0, -5); // 去掉 ".html"

            // 跳过根展示页 index.html，以及 ping.html 之类的非游戏
            if (in_array(strtolower($slug), array_merge($skipDirs, ['index']), true)) {
                continue;
            }

            // 目录形态已经收录的 slug 不再重复
            if (Arr::first($items, fn ($i) => ($i['slug'] ?? null) === $slug)) {
                continue;
            }

            $resolved = $engineChain->locate($slug);
            if (!$resolved->exists) {
                continue;
            }

            $metaFile = $gamesDir . DIRECTORY_SEPARATOR . $slug . '.json';
            $meta     = $this->loadMeta($metaFile);

            // 同样要求有 meta.json 才算游戏
            if (empty($meta)) {
                continue;
            }

            $items[] = $this->buildItem($slug, $meta, $resolved, $autoId);
        }

        // ⭐ 按 id 升序排序；id 相同时再按 title 保证稳定顺序
        usort($items, function (array $a, array $b) {
            $idA = (int)($a['id'] ?? 0);
            $idB = (int)($b['id'] ?? 0);

            if ($idA !== $idB) {
                return $idA <=> $idB;
            }

            return strcmp($a['title'], $b['title']);
        });

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
     * $autoId：按引用传入，用于在没有 meta.id 时自动递增分配。
     */
    private function buildItem(string $slug, array $meta, $resolved, int &$autoId): array
    {
        // 优先使用 meta.json 中的 id，没有或非法则用自动递增
        $id = isset($meta['id']) ? (int) $meta['id'] : 0;
        if ($id <= 0) {
            $id = $autoId++;
        }

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
            'id'         => $id,
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
