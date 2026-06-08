<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use SymPress\AssetCompiler\Application\AssetCompiler;
use SymPress\AssetCompiler\Application\CompilerFactory;
use SymPress\AssetCompiler\Config\RootConfig;

final class Plugin implements PluginInterface, EventSubscriberInterface, Capable
{
    private ?Composer $composer = null;

    private ?IOInterface $io = null;

    /**
     * @return array<string, list<array{string, int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => [
                ['onPostInstall', 0],
            ],
            ScriptEvents::POST_UPDATE_CMD => [
                ['onPostUpdate', 0],
            ],
        ];
    }

    /**
     * @return array<class-string, class-string>
     */
    public function getCapabilities(): array
    {
        return [
            CommandProviderCapability::class => CommandProvider::class,
        ];
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    public function onPostInstall(Event $event): void
    {
        $this->runFromComposerEvent($event);
    }

    public function onPostUpdate(Event $event): void
    {
        $this->runFromComposerEvent($event);
    }

    private function runFromComposerEvent(Event $event): void
    {
        $composer = $this->composer;
        $io = $this->io;

        if (!$composer instanceof Composer || !$io instanceof IOInterface) {
            return;
        }

        $compiler = CompilerFactory::create($composer, $io, null, $event->isDevMode());

        if (!$compiler->rootConfig()->autoRun) {
            return;
        }

        $io->write('<info>SymPress Asset Compiler</info> running after Composer operation.');

        $result = $compiler->compile();

        if (!$result->successful) {
            throw new \RuntimeException(
                sprintf(
                    'Asset compilation failed for %d package(s).',
                    $result->failed,
                ),
            );
        }
    }

    public function compiler(?string $mode, bool $devMode): AssetCompiler
    {
        if (!$this->composer instanceof Composer || !$this->io instanceof IOInterface) {
            throw new \RuntimeException('Composer plugin has not been activated.');
        }

        return CompilerFactory::create($this->composer, $this->io, $mode, $devMode);
    }

    public function rootConfig(?string $mode, bool $devMode): RootConfig
    {
        return $this->compiler($mode, $devMode)->rootConfig();
    }
}
