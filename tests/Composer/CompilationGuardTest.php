<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Tests\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\IO\BufferIO;
use Composer\Installer\InstallationManager;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledArrayRepository;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SymPress\AssetCompiler\Application\CompilerFactory;
use SymPress\AssetCompiler\Composer\Plugin;
use SymPress\AssetCompiler\Support\CompilationGuard;

final class CompilationGuardTest extends TestCase
{
    public function testManualCompilationRejectsNestedCallBeforeDiscovery(): void
    {
        $composer = new Composer();
        $composer->setPackage(new RootPackage('root/project', 'dev-main', 'dev-main'));
        $composer->setConfig(new Config());
        $composer->setInstallationManager($this->createStub(InstallationManager::class));
        $local = $this->createMock(InstalledArrayRepository::class);
        $local->expects(self::never())->method('getPackages');
        $repository = $this->createStub(RepositoryManager::class);
        $repository->method('getLocalRepository')->willReturn($local);
        $composer->setRepositoryManager($repository);
        $compiler = CompilerFactory::create($composer, new BufferIO(), null, true);
        $previous = getenv(CompilationGuard::ENVIRONMENT_VARIABLE);
        try {
            putenv(CompilationGuard::ENVIRONMENT_VARIABLE . '=1');
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/Nested asset compilation/');
            $compiler->compile();
        } finally {
            putenv($previous === false ? CompilationGuard::ENVIRONMENT_VARIABLE : CompilationGuard::ENVIRONMENT_VARIABLE . '=' . $previous);
        }
    }

    public function testNestedComposerEventsSkipAutomaticCompilation(): void
    {
        $composer = new Composer();
        $io = new BufferIO();
        $plugin = new Plugin();
        $plugin->activate($composer, $io);
        $previous = getenv(CompilationGuard::ENVIRONMENT_VARIABLE);
        try {
            putenv(CompilationGuard::ENVIRONMENT_VARIABLE . '=1');
            $plugin->onPostInstall(new Event(ScriptEvents::POST_INSTALL_CMD, $composer, $io));
            $plugin->onPostUpdate(new Event(ScriptEvents::POST_UPDATE_CMD, $composer, $io));
            self::assertSame('', $io->getOutput());
        } finally {
            putenv($previous === false ? CompilationGuard::ENVIRONMENT_VARIABLE : CompilationGuard::ENVIRONMENT_VARIABLE . '=' . $previous);
        }
    }
}
