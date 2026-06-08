<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Application;

use Symfony\Component\Process\Process;

final class RunningTask
{
    public ?Process $process = null;

    private int $step = 0;

    private float $startedAt = 0.0;

    public function __construct(public readonly BuildTask $task)
    {
    }

    public function currentStep(): BuildStep
    {
        return $this->task->steps[$this->step];
    }

    public function advance(): bool
    {
        ++$this->step;
        $this->process = null;

        return isset($this->task->steps[$this->step]);
    }

    public function markStarted(): void
    {
        if ($this->startedAt === 0.0) {
            $this->startedAt = microtime(true);
        }
    }

    public function elapsed(): float
    {
        return $this->startedAt > 0.0 ? microtime(true) - $this->startedAt : 0.0;
    }
}
