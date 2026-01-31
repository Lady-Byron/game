<?php

namespace LadyByron\Game;

use Flarum\Extend;
use LadyByron\Game\Controllers\PlayController;
use LadyByron\Game\Controllers\AssetController;
use LadyByron\Game\Saves\SavesListController;
use LadyByron\Game\Saves\SaveUpsertController;
use LadyByron\Game\Saves\SaveDeleteController;
use LadyByron\Game\Games\GamesListController;

return [
    // 原有：游戏入口与资源
    (new Extend\Routes('forum'))
        ->get('/play/{slug:[^/]+}', 'ladybyron-game.play', PlayController::class)
        ->get('/play/{slug:[^/]+}/asset/{path:.+}', 'ladybyron-game.asset', AssetController::class),

    // 新增：云存档 API（forum 路由，走会话+CSRF，中间件自动套用）
    (new Extend\Routes('forum'))
        // 列表：GET /playapi/saves/{slug}
        ->get('/playapi/saves/{slug:[^/]+}', 'ladybyron-game.saves.index', SavesListController::class)
        // 读取：GET /playapi/saves/{slug}/{slot}
        ->get('/playapi/saves/{slug:[^/]+}/{slot:[^/]+}', 'ladybyron-game.saves.show', SavesListController::class)
        // 写入/更新：POST /playapi/saves/{slug}
        ->post('/playapi/saves/{slug:[^/]+}', 'ladybyron-game.saves.upsert', SaveUpsertController::class)
        // 删除：DELETE /playapi/saves/{slug}/{slot}
        ->delete('/playapi/saves/{slug:[^/]+}/{slot:[^/]+}', 'ladybyron-game.saves.delete', SaveDeleteController::class),

    (new Extend\Routes('forum'))
        ->get('/playapi/games', 'ladybyron-game.games.index', GamesListController::class),
];
