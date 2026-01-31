<?php

namespace LadyByron\Game\Games;

use Flarum\Foundation\Paths;
use Flarum\Http\UrlGenerator;
use Illuminate\Contracts\Cache\Repository as Cache;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use LadyByron\Game\Engine\EngineChain;
use LadyByron\Game\Engine\TwineEngine;
use LadyByron\Game\Engine\InkEngine;

final class GamesListController implements RequestHandlerInterface
{
    private const CACHE_KEY = 'ladybyron_game_list';
    private const CACHE_TTL = 300; // 5 分钟缓存

    public function __construct(
        protected UrlGenerator $url,
        protected Paths $paths,
        protected Cache $cache
    ) {}

    public function handle(Request $request): Response
    {
        $gamesDir = $this->paths->storage . DIRECTORY_SEPARATOR . 'games';
        if (!is_dir($gamesDir)) {
            return new JsonResponse(['items' => []], 200);
        }

        // 尝试从缓存获取
        $items = $this->cache->get(self::CACHE_KEY);

        if ($items === null) {
            $items = $this->scanGames($gamesDir);
            $this->cache->put(self::CACHE_KEY, $items, self::CACHE_TTL);
        }

        return new JsonResponse(['items' => $items], 200);
    }

    /**
     * 扫描游戏目录，返回排序后的游戏列表
     */
    private function scanGames(string $gamesDir): array
    {
        $engineChain = new EngineChain([
            new InkEngine($gamesDir),
            new TwineEngine($gamesDir),
        ]);

        $items     = [];
        $seenSlugs = [];  // 哈希表用于 O(1) 查重
        $autoId    = 1;
        $skipNames = ['assets' => true, 'ping' => true, 'index' => true];

        // 一次扫描，分类处理
        $entries = scandir($gamesDir);
        $dirs    = [];
        $htmls   = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $gamesDir . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($full)) {
                // 验证 slug 格式
                if (preg_match('~^[a-z0-9_-]+$~i', $entry)) {
                    $dirs[] = $entry;
                }
            } elseif (preg_match('~^([a-z0-9_-]+)\.html$~i', $entry, $m)) {
                $htmls[] = $m[1]; // 提取不含 .html 的 slug
            }
        }

        // 1) 处理目录形式游戏
        foreach ($dirs as $slug) {
            $slugLower = strtolower($slug);
            if (isset($skipNames[$slugLower])) {
                continue;
            }

            $resolved = $engineChain->locate($slug);
            if (!$resolved->exists) {
                continue;
            }

            $metaFile = $gamesDir . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'meta.json';
            $meta = $this->loadMeta($metaFile);
            if (empty($meta)) {
                continue;
            }

            $items[] = $this->buildItem($slug, $meta, $resolved, $autoId);
            $seenSlugs[$slugLower] = true;  // 记录已处理的 slug
        }

        // 2) 处理单文件形式游戏
        foreach ($htmls as $slug) {
            $slugLower = strtolower($slug);

            // 跳过系统文件和已处理的 slug
            if (isset($skipNames[$slugLower]) || isset($seenSlugs[$slugLower])) {
                continue;
            }

            $resolved = $engineChain->locate($slug);
            if (!$resolved->exists) {
                continue;
            }

            $metaFile = $gamesDir . DIRECTORY_SEPARATOR . $slug . '.json';
            $meta = $this->loadMeta($metaFile);
            if (empty($meta)) {
                continue;
            }

            $items[] = $this->buildItem($slug, $meta, $resolved, $autoId);
        }

        // 按 id 升序排序；id 相同时按 title 排序
        usort($items, function (array $a, array $b) {
            $cmp = ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
            return $cmp !== 0 ? $cmp : strcmp($a['title'], $b['title']);
        });

        return $items;
    }

    /**
     * 从 meta.json 读取并简单校验
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
     * 统一构建前端要吃的结构
     */
    private function buildItem(string $slug, array $meta, $resolved, int &$autoId): array
    {
        $id = isset($meta['id']) ? (int) $meta['id'] : 0;
        if ($id <= 0) {
            $id = $autoId++;
        }

        $length = max(1, min(5, (int) ($meta['length'] ?? 3)));

        $playUrl = $this->url
            ->to('forum')
            ->route('ladybyron-game.play', ['slug' => $slug]);

        return [
            'id'          => $id,
            'slug'        => $slug,
            'title'       => (string) ($meta['title'] ?? $slug),
            'subtitle'    => (string) ($meta['subtitle'] ?? ''),
            'author'      => (string) ($meta['author'] ?? 'Unknown'),
            'size'        => (string) ($meta['size'] ?? ''),
            'length'      => $length,
            'status'      => (string) ($meta['status'] ?? ''),
            'description' => (string) ($meta['description'] ?? ''),
            'tags'        => isset($meta['tags']) && is_array($meta['tags']) ? array_values($meta['tags']) : [],
            'color'       => (string) ($meta['color'] ?? 'text-amber-500'),
            'stripColor'  => (string) ($meta['stripColor'] ?? 'bg-amber-600'),
            'engine'      => $resolved->engine,
            'shape'       => $resolved->shape,
            'playUrl'     => $playUrl,
        ];
    }
}
