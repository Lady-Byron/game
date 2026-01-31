<?php
namespace LadyByron\Game\Engine;

interface GameEngineInterface
{
    /**
     * 尝试定位并识别 {slug} 对应的入口文件。
     * 找到则返回 ResolvedGame(exists=true)，否则 exists=false。
     */
    public function locate(string $slug): ResolvedGame;
}
