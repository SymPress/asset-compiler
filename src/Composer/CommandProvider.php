<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Composer;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;

final readonly class CommandProvider implements CommandProviderCapability
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(private array $arguments = [])
    {
    }

    /**
     * @return list<\Composer\Command\BaseCommand>
     */
    public function getCommands(): array
    {
        return [
            new CompileAssetsCommand(),
            new AssetHashCommand(),
        ];
    }
}
