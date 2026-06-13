<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Composer;

use Composer\Command\BaseCommand;
use SymPress\AssetCompiler\Application\CompilerFactory;
use SymPress\AssetCompiler\Config\RootConfig;
use SymPress\AssetCompiler\Support\StringList;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class CompileAssetsCommand extends BaseCommand
{
    #[\Override]
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
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show planned work without running commands.')
            ->addOption('explain', null, InputOption::VALUE_NONE, 'Show package selection and manager resolution details.')
            ->addOption('max-processes', null, InputOption::VALUE_REQUIRED, 'Override package build parallelism for this run.')
            ->addOption(
                'execution-strategy',
                null,
                InputOption::VALUE_REQUIRED,
                'Override execution strategy for this run: staged or grouped.',
            )
            ->addOption(
                'wipe-node-modules',
                null,
                InputOption::VALUE_NONE,
                'Remove generated node_modules after each successful package build.',
            )
            ->addOption(
                'keep-node-modules',
                null,
                InputOption::VALUE_NONE,
                'Keep node_modules even when wipe-node-modules is enabled in configuration.',
            )
            ->addOption(
                'clear-package-manager-cache',
                null,
                InputOption::VALUE_NONE,
                'Remove isolated package-manager caches after each successful package build.',
            );
    }

    #[\Override]
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

        $dryRun = (bool) $input->getOption('dry-run');
        $wipeNodeModules = $this->wipeNodeModulesOverride($input);
        $executionStrategy = $this->stringOption($input, 'execution-strategy');

        if ($executionStrategy !== null) {
            $executionStrategy = RootConfig::normalizeExecutionStrategy($executionStrategy);
        }

        $result = $compiler->compile(
            ignoreLock: $this->optionalStringOption($input, 'ignore-lock'),
            packagePatterns: $this->csvOption($input, 'packages'),
            installDependencies: !$input->getOption('no-install'),
            dryRun: $dryRun,
            explain: (bool) $input->getOption('explain'),
            maxProcesses: $this->positiveIntOption($input, 'max-processes'),
            wipeNodeModules: $wipeNodeModules,
            clearPackageManagerCache: $input->getOption('clear-package-manager-cache') ? true : null,
            executionStrategy: $executionStrategy,
        );

        CompilationReporter::write($io, $result, $dryRun);

        return $result->successful ? self::SUCCESS : self::FAILURE;
    }

    private function wipeNodeModulesOverride(InputInterface $input): ?bool
    {
        $wipe = (bool) $input->getOption('wipe-node-modules');
        $keep = (bool) $input->getOption('keep-node-modules');

        if ($wipe && $keep) {
            throw new \InvalidArgumentException('Use either --wipe-node-modules or --keep-node-modules, not both.');
        }

        return match (true) {
            $wipe => true,
            $keep => false,
            default => null,
        };
    }

    private function positiveIntOption(InputInterface $input, string $name): ?int
    {
        $value = $input->getOption($name);

        if ($value === null || $value === false || $value === '') {
            return null;
        }

        if (!is_numeric($value) || (int) $value < 1) {
            throw new \InvalidArgumentException(sprintf('Option --%s must be a positive integer.', $name));
        }

        return (int) $value;
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

    /** @return list<string> */
    private function csvOption(InputInterface $input, string $name): array
    {
        $value = $input->getOption($name);

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        return StringList::fromCsv($value);
    }
}
