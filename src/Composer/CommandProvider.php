<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Composer;

use Composer\Command\BaseCommand;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;

final readonly class CommandProvider implements CommandProviderCapability
{
    /** @return list<BaseCommand> */
    #[\Override]
    public function getCommands(): array
    {
        return [
            new CompileAssetsCommand(),
            new AssetHashCommand(),
        ];
    }
}
