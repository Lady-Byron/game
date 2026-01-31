<?php

namespace LadyByron\Game\Games;

use Flarum\Foundation\Paths;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class GamesPageController implements RequestHandlerInterface
{
    public function __construct(
        private Paths $paths
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $file = $this->paths->storage . DIRECTORY_SEPARATOR . 'games' . DIRECTORY_SEPARATOR . 'index.html';

        if (!is_file($file) || !is_readable($file)) {
            return new HtmlResponse('Games page not found', 404);
        }

        $html = @file_get_contents($file);
        if ($html === false) {
            return new HtmlResponse('Failed to load', 500);
        }

        return new HtmlResponse($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
