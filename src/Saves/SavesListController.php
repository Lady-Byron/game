<?php
namespace LadyByron\Games\Saves;

use LadyByron\Games\Model\GameSave;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;

final class SavesListController implements RequestHandlerInterface
{
    public function handle(Request $request): Response
    {
        $actor = RequestUtil::getActor($request);
        $rp    = (array) $request->getAttribute('routeParameters', []);
        $slug = (string) Arr::get($rp, 'slug', '');
        $slot = (string) Arr::get($rp, 'slot', '');

        if ($slug === '' || !preg_match('~^[a-z0-9_-]+$~i', $slug)) {
            return new JsonResponse(['error' => 'invalid_slug'], 400);
        }

        if ($slot !== '') {
            $save = GameSave::query()
                ->where('user_id', $actor->id)
                ->where('game_slug', $slug)
                ->where('slot', $slot)
                ->first();

            if (!$save) {
                return new JsonResponse(['error' => 'not_found'], 404);
            }

            return new JsonResponse([
                'slot'       => $save->slot,
                'rev'        => $save->rev,
                'stateJson'  => $save->state_json,   // 加载需要完整状态
                'meta'       => $save->meta_json,
                'storyHash'  => $save->story_hash,
                'updatedAt'  => $save->updated_at?->toAtomString(),
            ], 200);
        }

        // 列表：不返回 state_json，避免大流量
        $rows = GameSave::query()
            ->where('user_id', $actor->id)
            ->where('game_slug', $slug)
            ->orderBy('updated_at', 'desc')
            ->get(['slot', 'rev', 'meta_json', 'story_hash', 'updated_at']);

        return new JsonResponse([
            'items' => $rows->map(fn ($r) => [
                'slot'      => $r->slot,
                'rev'       => (int) $r->rev,
                'meta'      => $r->meta_json,
                'storyHash' => $r->story_hash,
                'updatedAt' => $r->updated_at?->toAtomString(),
            ])->all(),
        ], 200);
    }
}
