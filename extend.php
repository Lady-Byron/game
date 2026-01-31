<?php

namespace LadyByron\Game;

use Flarum\Extend;
use LadyByron\Game\Controllers\PlayController;
use LadyByron\Game\Saves\SavesListController;
use LadyByron\Game\Saves\SaveUpsertController;
use LadyByron\Game\Saves\SaveDeleteController;
use LadyByron\Game\Games\GamesListController;
use LadyByron\Game\Games\GamesPageController;

return [
    // 符号链接：public/assets/games → storage/games（OLS / nginx 直出静态资源）
    new Extend\ServiceProvider(GameServiceProvider::class),

    // 游戏 HTML 入口（仍走 forum 中间件，注入 ForumAuth / <base>）
    (new Extend\Routes('forum'))
        ->get('/play/{slug:[^/]+}', 'ladybyron-game.play', PlayController::class),

    // 云存档 API（forum 路由，走会话+CSRF）
    (new Extend\Routes('forum'))
        ->get('/playapi/saves/{slug:[^/]+}', 'ladybyron-game.saves.index', SavesListController::class)
        ->get('/playapi/saves/{slug:[^/]+}/{slot:[^/]+}', 'ladybyron-game.saves.show', SavesListController::class)
        ->post('/playapi/saves/{slug:[^/]+}', 'ladybyron-game.saves.upsert', SaveUpsertController::class)
        ->delete('/playapi/saves/{slug:[^/]+}/{slot:[^/]+}', 'ladybyron-game.saves.delete', SaveDeleteController::class),

    // 游戏列表页 + API
    (new Extend\Routes('forum'))
        ->get('/games', 'ladybyron-game.games.page', GamesPageController::class)
        ->get('/playapi/games', 'ladybyron-game.games.index', GamesListController::class),
];
