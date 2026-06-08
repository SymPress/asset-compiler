<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Composer;

use Composer\Command\BaseCommand;
use Composer\IO\IOInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use SymPress\AssetCompiler\Application\CompilerFactory;

final class CompileAssetsCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('compile-assets')
            ->setAliases(['assets:compile', 'asset-compiler:compile'])
            ->setDescription('Installs frontend dependencies and compiles package assets.')
            ->addOption('mode', null, InputOption::VALUE_REQUIRED, 'Configuration mode to resolve.')
            ->addOption('no-dev', null, InputOption::VALUE_NONE, 'Resolve configuration as Composer --no-dev.')
            ->addOption(
                'ignore-lock',
                null,
                InputOption::VALUE_OPTIONAL,
                'Ignore build locks for all packages or comma-separated package patterns.',
                '',
            )
            ->addOption(
                'packages',
                null,
                InputOption::VALUE_REQUIRED,
                'Only process comma-separated package names or fnmatch patterns.',
            )
            ->addOption('no-install', null, InputOption::VALUE_NONE, 'Skip dependency installation.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show planned work without running commands.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composer = $this->requireComposer();
        $io = $this->getIO();
        $devMode = !$input->getOption('no-dev');

        $compiler = CompilerFactory::create(
            $composer,
            $io,
            $this->stringOption($input, 'mode'),
            $devMode,
        );

        $result = $compiler->compile(
            ignoreLock: $this->optionalStringOption($input, 'ignore-lock'),
            packagePatterns: $this->csvOption($input, 'packages'),
            installDependencies: !$input->getOption('no-install'),
            dryRun: (bool) $input->getOption('dry-run'),
        );

        $this->writeSummary($io, $result);

        return $result->successful ? self::SUCCESS : self::FAILURE;
    }

    private function writeSummary(IOInterface $io, \SymPress\AssetCompiler\Application\CompilationResult $result): void
    {
        if ($result->total === 0) {
            $io->write('<comment>No asset packages found.</comment>');

            return;
        }

        $io->write(
            sprintf(
                '<info>Asset compilation finished:</info> %d ok, %d skipped, %d failed.',
                $result->successfulTasks,
                $result->skipped,
                $result->failed,
            ),
        );
    }

    private function stringOption(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function optionalStringOption(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @return list<string>
     */
    private function csvOption(InputInterface $input, string $name): array
    {
        $value = $input->getOption($name);

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(
            array_filter(
                array_map(static fn (string $part): string => trim($part), explode(',', $value)),
                static fn (string $part): bool => $part !== '',
            ),
        );
    }
}
