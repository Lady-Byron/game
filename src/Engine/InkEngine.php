<?php
namespace LadyByron\Game\Engine;

final class InkEngine implements GameEngineInterface
{
    public function __construct(private string $baseDir) {}

    public function locate(string $slug): ResolvedGame
    {
        // 物理布局不变；仅在找到文件后用“特征”判定是否为 Ink（inkjs / Inkle）
        $dir   = $this->baseDir . DIRECTORY_SEPARATOR . $slug;
        $index = $dir . DIRECTORY_SEPARATOR . 'index.html';
        $legacy= $this->baseDir . DIRECTORY_SEPARATOR . $slug . '.html';

        if (is_file($index) && $this->looksLikeInk($index))  return new ResolvedGame(true, $index,  'ink', 'dir');
        if (is_file($legacy) && $this->looksLikeInk($legacy)) return new ResolvedGame(true, $legacy, 'ink', 'legacy');

        return new ResolvedGame(false);
    }

    private function looksLikeInk(string $htmlFile): bool
    {
        $head = @file_get_contents($htmlFile, false, null, 0, 4096);
        if ($head === false) return false;

        return stripos($head, 'inkjs') !== false
            || stripos($head, 'inkle') !== false
            || stripos($head, 'inky')  !== false;
    }
}
