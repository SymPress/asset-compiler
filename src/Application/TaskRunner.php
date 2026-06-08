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

        $prepared = $this->runSequentialSteps($tasks, $config);

        if ($prepared->failed > 0 && $config->stopOnFailure) {
            return new RunnerResult($prepared->successfulWorkspaces, $prepared->hashes, $prepared->failed);
        }

        if ($prepared->parallelTasks === []) {
            return new RunnerResult($prepared->successfulWorkspaces, $prepared->hashes, $prepared->failed);
        }

        $parallel = $config->maxProcesses <= 1 || count($prepared->parallelTasks) === 1
            ? $this->runScriptTasksSequentially($prepared->parallelTasks, $config)
            : $this->runInPool($prepared->parallelTasks, $config);

        return new RunnerResult(
            array_merge($prepared->successfulWorkspaces, $parallel->successfulWorkspaces),
            array_replace($prepared->hashes, $parallel->hashes),
            $prepared->failed + $parallel->failed,
        );
    }

    /**
     * @param list<BuildTask> $tasks
     */
    private function runScriptTasksSequentially(array $tasks, RootConfig $config): RunnerResult
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
        $started = microtime(true);
        $this->io->write(sprintf('<info>%s</info> run build steps', $task->workspace->name));

        foreach ($task->steps as $step) {
            if (!$this->runStep($task->workspace->name, $step)) {
                return false;
            }
        }

        $this->io->write(sprintf('<info>%s</info> completed in %.2fs.', $task->workspace->name, microtime(true) - $started));

        return true;
    }

    /**
     * @param list<BuildTask> $tasks
     */
    private function runSequentialSteps(array $tasks, RootConfig $config): PreparedTasks
    {
        $successful = [];
        $hashes = [];
        $parallelTasks = [];
        $failed = 0;

        foreach ($tasks as $task) {
            $sequential = array_values(array_filter($task->steps, static fn (BuildStep $step): bool => !$step->parallel));
            $parallel = array_values(array_filter($task->steps, static fn (BuildStep $step): bool => $step->parallel));

            foreach ($sequential as $step) {
                $this->io->write(sprintf('<info>%s</info> %s', $task->workspace->name, $step->label));

                if (!$this->runStep($task->workspace->name, $step)) {
                    ++$failed;

                    if ($config->stopOnFailure) {
                        return new PreparedTasks($successful, $hashes, $parallelTasks, $failed);
                    }

                    continue 2;
                }
            }

            if ($parallel === []) {
                $successful[] = $task->workspace;
                $hashes[$task->workspace->name] = $task->hash;
                continue;
            }

            $parallelTasks[] = new BuildTask($task->workspace, $task->hash, $parallel);
        }

        return new PreparedTasks($successful, $hashes, $parallelTasks, $failed);
    }

    private function runStep(string $packageName, BuildStep $step): bool
    {
        $started = microtime(true);
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
                    $packageName,
                    $step->displayCommand(),
                ),
            );

            return false;
        }

        $this->io->write(
            sprintf('  %s finished in %.2fs.', $step->label, microtime(true) - $started),
            true,
            IOInterface::VERBOSE,
        );

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
                $this->io->write(
                    sprintf('<info>%s</info> completed in %.2fs.', $task->task->workspace->name, $task->elapsed()),
                );
            }

            usleep($config->processPoll);
        }

        return new RunnerResult($successful, $hashes, $failed);
    }

    private function startTask(RunningTask $task): void
    {
        $step = $task->currentStep();
        $task->markStarted();
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
