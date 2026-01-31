<?php
namespace LadyByron\Game\Engine;

final class ResolvedGame
{
    public function __construct(
        public bool $exists,
        public string $file = '',
        public string $engine = '', // 'twine' | 'ink'
        public string $shape = ''   // 'dir' | 'legacy'
    ) {}
}

final class EngineChain
{
    /** @var GameEngineInterface[] */
    private array $engines;

    public function __construct(array $engines)
    {
        $this->engines = $engines;
    }

    public function locate(string $slug): ResolvedGame
    {
        foreach ($this->engines as $engine) {
            $r = $engine->locate($slug);
            if ($r->exists) return $r;
        }
        return new ResolvedGame(false);
    }
}
