<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Composer;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use SymPress\AssetCompiler\Application\CompilerFactory;
use SymPress\AssetCompiler\Support\StringList;

final class AssetHashCommand extends BaseCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('assets-hash')
            ->setAliases(['asset-compiler:hash'])
            ->setDescription('Prints the current asset build hash for discovered packages.')
            ->addOption('mode', null, InputOption::VALUE_REQUIRED, 'Configuration mode to resolve.')
            ->addOption('no-dev', null, InputOption::VALUE_NONE, 'Resolve configuration as Composer --no-dev.')
            ->addOption(
                'packages',
                null,
                InputOption::VALUE_REQUIRED,
                'Only print comma-separated package names or fnmatch patterns.',
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

        $hashes = $compiler->hashes($this->csvOption($input, 'packages'));

        if ($hashes === []) {
            $this->getIO()->write('<comment>No asset packages found.</comment>');

            return self::SUCCESS;
        }

        foreach ($hashes as $package => $hash) {
            $this->getIO()->write(sprintf('%s %s', $hash, $package));
        }

        return self::SUCCESS;
    }

    private function stringOption(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
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
