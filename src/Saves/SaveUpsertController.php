<?php
namespace LadyByron\Game\Saves;

use LadyByron\Game\Model\GameSave;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;

final class SaveUpsertController implements RequestHandlerInterface
{
    // 最大状态体积（防滥用/DoS）
    private const STATE_MAX_BYTES = 512 * 1024;

    public function handle(Request $request): Response
    {
        $actor = RequestUtil::getActor($request);
        $rp    = (array) $request->getAttribute('routeParameters', []);
        $slug = (string) Arr::get($rp, 'slug', '');
        if ($slug === '' || !preg_match('~^[a-z0-9_-]+$~i', $slug)) {
            return new JsonResponse(['error' => 'invalid_slug'], 400);
        }

        // —— 关键修复：优先用 parsedBody，必要时回退读取原始流并 rewind ——
        $json = $request->getParsedBody();
        if (!is_array($json)) {
            $body = $request->getBody();
            if ($body->tell() !== 0 && $body->isSeekable()) {
                $body->rewind();
            }
            $raw  = (string) $body->getContents();
            $json = json_decode($raw, true);
        }
        if (!is_array($json)) {
            return new JsonResponse(['error' => 'invalid_json'], 400);
        }

        $slot      = (string) Arr::get($json, 'slot', '');
        $stateJson = (string) Arr::get($json, 'stateJson', '');
        $storyHash = (string) Arr::get($json, 'storyHash', '');
        $meta      = Arr::get($json, 'meta');
        $rev       = (int) Arr::get($json, 'rev', 0);

        if ($slot === '' || !preg_match('~^[a-z0-9_-]{1,50}$~i', $slot)) {
            return new JsonResponse(['error' => 'invalid_slot'], 400);
        }
        if ($stateJson === '' || strlen($stateJson) > self::STATE_MAX_BYTES) {
            return new JsonResponse(['error' => 'state_too_large'], 413);
        }
        if ($meta !== null && !is_array($meta)) {
            return new JsonResponse(['error' => 'invalid_meta'], 400);
        }

        $existing = GameSave::query()
            ->where('user_id', $actor->id)
            ->where('game_slug', $slug)
            ->where('slot', $slot)
            ->first();

        if ($existing) {
            // 乐观并发：客户端带 rev，不一致则 409
            if ($rev !== 0 && $rev !== (int) $existing->rev) {
                return new JsonResponse([
                    'error'   => 'conflict',
                    'current' => ['rev' => (int) $existing->rev],
                ], 409);
            }

            $existing->state_json = $stateJson;
            if ($meta !== null)      $existing->meta_json  = $meta;
            if ($storyHash !== '')   $existing->story_hash = $storyHash;
            $existing->rev = (int) $existing->rev + 1;
            $existing->save();

            return new JsonResponse([
                'ok'        => true,
                'rev'       => (int) $existing->rev,
                'slot'      => $existing->slot,
                'updatedAt' => $existing->updated_at?->toAtomString(),
            ], 200);
        }

        $row = GameSave::query()->create([
            'user_id'    => (int) $actor->id,
            'game_slug'  => $slug,
            'slot'       => $slot,
            'rev'        => 1,
            'state_json' => $stateJson,
            'meta_json'  => $meta,
            'story_hash' => $storyHash ?: null,
        ]);

        return new JsonResponse([
            'ok'        => true,
            'rev'       => 1,
            'slot'      => $row->slot,
            'updatedAt' => $row->updated_at?->toAtomString(),
        ], 201);
    }
}
