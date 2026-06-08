<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Application;

use Composer\IO\IOInterface;
use Symfony\Component\Process\Process;
use SymPress\AssetCompiler\Config\RootConfig;

final readonly class TaskRunner
{
    public function __construct(private IOInterface $io)
    {
    }

    /**
     * @param list<BuildTask> $tasks
     */
    public function run(array $tasks, RootConfig $config): RunnerResult
    {
        if ($tasks === []) {
            return new RunnerResult([], [], 0);
        }

        if ($config->maxProcesses <= 1 || count($tasks) === 1) {
            return $this->runSequentially($tasks, $config);
        }

        return $this->runInPool($tasks, $config);
    }

    /**
     * @param list<BuildTask> $tasks
     */
    private function runSequentially(array $tasks, RootConfig $config): RunnerResult
    {
        $successful = [];
        $hashes = [];
        $failed = 0;

        foreach ($tasks as $task) {
            $ok = $this->runTask($task);

            if ($ok) {
                $successful[] = $task->workspace;
                $hashes[$task->workspace->name] = $task->hash;
                continue;
            }

            ++$failed;

            if ($config->stopOnFailure) {
                break;
            }
        }

        return new RunnerResult($successful, $hashes, $failed);
    }

    private function runTask(BuildTask $task): bool
    {
        $this->io->write(sprintf('<info>Compiling assets for %s</info>', $task->workspace->name));

        foreach ($task->steps as $step) {
            $this->io->write(
                sprintf('  <comment>%s:</comment> %s', $step->label, $step->displayCommand()),
                true,
                IOInterface::VERBOSE,
            );

            $process = new Process($step->command, $step->workingDirectory, $step->environment, null, $step->timeout);
            $process->run($this->output(...));

            if (!$process->isSuccessful()) {
                $this->io->writeError(
                    sprintf(
                        '<error>Asset command failed for %s:</error> %s',
                        $task->workspace->name,
                        $step->displayCommand(),
                    ),
                );

                return false;
            }
        }

        return true;
    }

    /**
     * @param list<BuildTask> $tasks
     */
    private function runInPool(array $tasks, RootConfig $config): RunnerResult
    {
        $queue = new \SplQueue();
        foreach ($tasks as $task) {
            $queue->enqueue(new RunningTask($task));
        }

        $running = [];
        $successful = [];
        $hashes = [];
        $failed = 0;

        while (!$queue->isEmpty() || $running !== []) {
            while (count($running) < $config->maxProcesses && !$queue->isEmpty()) {
                /** @var RunningTask $task */
                $task = $queue->dequeue();
                $this->startTask($task);
                $running[spl_object_id($task)] = $task;
            }

            foreach ($running as $id => $task) {
                if (!$task->process instanceof Process || $task->process->isRunning()) {
                    continue;
                }

                if (!$task->process->isSuccessful()) {
                    ++$failed;
                    unset($running[$id]);
                    $this->io->writeError(sprintf('<error>Asset command failed for %s.</error>', $task->task->workspace->name));

                    if ($config->stopOnFailure) {
                        $this->stopRunning($running);

                        return new RunnerResult($successful, $hashes, $failed);
                    }

                    continue;
                }

                if ($task->advance()) {
                    $this->startTask($task);
                    continue;
                }

                unset($running[$id]);
                $successful[] = $task->task->workspace;
                $hashes[$task->task->workspace->name] = $task->task->hash;
            }

            usleep($config->processPoll);
        }

        return new RunnerResult($successful, $hashes, $failed);
    }

    private function startTask(RunningTask $task): void
    {
        $step = $task->currentStep();
        $this->io->write(sprintf('<info>%s</info> %s', $task->task->workspace->name, $step->label));
        $this->io->write(
            sprintf('  %s', $step->displayCommand()),
            true,
            IOInterface::VERBOSE,
        );

        $task->process = new Process(
            $step->command,
            $step->workingDirectory,
            $step->environment,
            null,
            $step->timeout,
        );
        $task->process->start($this->output(...));
    }

    /**
     * @param array<int, RunningTask> $running
     */
    private function stopRunning(array $running): void
    {
        foreach ($running as $task) {
            if ($task->process instanceof Process && $task->process->isRunning()) {
                $task->process->stop(1);
            }
        }
    }

    private function output(string $type, string $buffer): void
    {
        if ($type === Process::ERR) {
            $this->io->writeErrorRaw($buffer, false);

            return;
        }

        $this->io->writeRaw($buffer, false);
    }
}
