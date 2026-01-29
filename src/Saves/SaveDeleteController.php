<?php
namespace LadyByron\Games\Saves;

use LadyByron\Games\Model\GameSave;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;

final class SaveDeleteController implements RequestHandlerInterface
{
    public function handle(Request $request): Response
    {
        $actor = RequestUtil::getActor($request);
        $rp    = (array) $request->getAttribute('routeParameters', []);
        $slug = (string) Arr::get($rp, 'slug', '');
        $slot = (string) Arr::get($rp, 'slot', '');

        if ($slug === '' || $slot === '') {
            return new JsonResponse(['error' => 'invalid_parameters'], 400);
        }

        $count = GameSave::query()
            ->where('user_id', $actor->id)
            ->where('game_slug', $slug)
            ->where('slot', $slot)
            ->delete();

        if ($count === 0) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return new JsonResponse(['ok' => true], 200);
    }
}
