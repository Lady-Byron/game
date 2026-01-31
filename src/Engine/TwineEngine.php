<?php
namespace LadyByron\Game\Engine;

final class TwineEngine implements GameEngineInterface
{
    public function __construct(private string $baseDir) {}

    public function locate(string $slug): ResolvedGame
    {
        // 与原逻辑完全一致（目录 index.html 优先，其次 legacy 单文件）
        $dir   = $this->baseDir . DIRECTORY_SEPARATOR . $slug;
        $index = $dir . DIRECTORY_SEPARATOR . 'index.html';
        $legacy= $this->baseDir . DIRECTORY_SEPARATOR . $slug . '.html';

        if (is_file($index))  return new ResolvedGame(true, $index,  'twine', 'dir');
        if (is_file($legacy)) return new ResolvedGame(true, $legacy, 'twine', 'legacy');

        return new ResolvedGame(false);
    }
}
