<?php

namespace LadyByron\Games;

use Flarum\Extend;
use LadyByron\Games\Controllers\PlayController;
use LadyByron\Games\Controllers\AssetController;
use LadyByron\Games\Saves\SavesListController;
use LadyByron\Games\Saves\SaveUpsertController;
use LadyByron\Games\Saves\SaveDeleteController;
use LadyByron\Games\Games\GamesListController;

return [
    // 原有：游戏入口与资源
    (new Extend\Routes('forum'))
        ->get('/play/{slug:[^/]+}', 'ladybyron-games.play', PlayController::class)
        ->get('/play/{slug:[^/]+}/asset/{path:.+}', 'ladybyron-games.asset', AssetController::class),

    // 新增：云存档 API（forum 路由，走会话+CSRF，中间件自动套用）
    (new Extend\Routes('forum'))
        // 列表：GET /playapi/saves/{slug}
        ->get('/playapi/saves/{slug:[^/]+}', 'ladybyron-games.saves.index', SavesListController::class)
        // 读取：GET /playapi/saves/{slug}/{slot}
        ->get('/playapi/saves/{slug:[^/]+}/{slot:[^/]+}', 'ladybyron-games.saves.show', SavesListController::class)
        // 写入/更新：POST /playapi/saves/{slug}
        ->post('/playapi/saves/{slug:[^/]+}', 'ladybyron-games.saves.upsert', SaveUpsertController::class)
        // 删除：DELETE /playapi/saves/{slug}/{slot}
        ->delete('/playapi/saves/{slug:[^/]+}/{slot:[^/]+}', 'ladybyron-games.saves.delete', SaveDeleteController::class),

    (new Extend\Routes('forum'))
        ->get('/playapi/games', 'ladybyron-games.games.index', GamesListController::class),
];
