<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Composer;

use Composer\Command\BaseCommand;
use SymPress\AssetCompiler\Application\CompilerFactory;
use SymPress\AssetCompiler\Support\StringList;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class AssetInfoCommand extends BaseCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('assets-info')
            ->setAliases(['assets:info', 'asset-compiler:info'])
            ->setDescription('Outputs discovered asset compiler package metadata as JSON.')
            ->addOption('mode', null, InputOption::VALUE_REQUIRED, 'Configuration mode to resolve.')
            ->addOption('no-dev', null, InputOption::VALUE_NONE, 'Resolve configuration as Composer --no-dev.')
            ->addOption(
                'packages',
                null,
                InputOption::VALUE_REQUIRED,
                'Only include comma-separated package names or fnmatch patterns.',
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $compiler = CompilerFactory::create(
            $this->requireComposer(),
            $this->getIO(),
            $this->stringOption($input, 'mode'),
            !$input->getOption('no-dev'),
        );

        $output->writeln(json_encode(
            $compiler->info($this->csvOption($input, 'packages')),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));

        return self::SUCCESS;
    }

    private function stringOption(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
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
