<?php

namespace LadyByron\Game;

use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Foundation\Paths;
use Psr\Log\LoggerInterface;

class GameServiceProvider extends AbstractServiceProvider
{
    public function boot(): void
    {
        /** @var Paths $paths */
        $paths  = $this->app->make(Paths::class);
        $link   = $paths->public . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'games';
        $target = $paths->storage . DIRECTORY_SEPARATOR . 'games';

        // Already linked or manually created as directory — nothing to do
        if (is_link($link) || is_dir($link)) {
            return;
        }

        // Target directory must exist
        if (!is_dir($target)) {
            return;
        }

        // Ensure parent directory exists (public/assets/)
        $parent = dirname($link);
        if (!is_dir($parent)) {
            @mkdir($parent, 0755, true);
        }

        if (!@symlink($target, $link)) {
            /** @var LoggerInterface $log */
            $log = $this->app->make(LoggerInterface::class);
            $log->warning(
                "[lady-byron/game] Failed to create symlink: {$link} -> {$target}. "
                . 'Create it manually: ln -s ' . escapeshellarg($target) . ' ' . escapeshellarg($link)
            );
        }
    }
}
