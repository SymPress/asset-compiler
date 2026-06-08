<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Composer;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use SymPress\AssetCompiler\Application\CompilerFactory;
use SymPress\AssetCompiler\Support\StringList;

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
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show planned work without running commands.')
            ->addOption('explain', null, InputOption::VALUE_NONE, 'Show package selection and manager resolution details.');
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

        $dryRun = (bool) $input->getOption('dry-run');
        $result = $compiler->compile(
            ignoreLock: $this->optionalStringOption($input, 'ignore-lock'),
            packagePatterns: $this->csvOption($input, 'packages'),
            installDependencies: !$input->getOption('no-install'),
            dryRun: $dryRun,
            explain: (bool) $input->getOption('explain'),
        );

        CompilationReporter::write($io, $result, $dryRun);

        return $result->successful ? self::SUCCESS : self::FAILURE;
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

        return StringList::fromCsv($value);
    }
}
