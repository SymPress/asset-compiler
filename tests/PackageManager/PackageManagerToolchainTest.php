<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Tests\PackageManager;

use Composer\IO\BufferIO;
use PHPUnit\Framework\TestCase;
use SymPress\AssetCompiler\Application\BuildStep;
use SymPress\AssetCompiler\Application\BuildTask;
use SymPress\AssetCompiler\Application\TaskRunner;
use SymPress\AssetCompiler\Config\BuildConfig;
use SymPress\AssetCompiler\Config\DependencyMode;
use SymPress\AssetCompiler\Config\RootConfig;
use SymPress\AssetCompiler\Discovery\PackageWorkspace;
use SymPress\AssetCompiler\PackageManager\PackageManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;

final class PackageManagerToolchainTest extends TestCase
{
    private ?string $workspacePath = null;

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->workspacePath === null || !is_dir($this->workspacePath)) {
            return;
        }

        (new Filesystem())->remove($this->workspacePath);
    }

    public function testAvailablePackageManagersExecuteTheirGeneratedBuildCommand(): void
    {
        $workspace = $this->workspace();
        $finder = new ExecutableFinder();
        $tested = [];

        foreach ([PackageManager::NPM, PackageManager::YARN, PackageManager::PNPM] as $name) {
            if (!is_string($finder->find($name))) {
                continue;
            }

            $marker = sprintf('built-%s', $name);
            $manager = new PackageManager($name);
            $result = (new TaskRunner(new BufferIO()))->run([
                new BuildTask($workspace, $name, [
                    new BuildStep(
                        'run build',
                        $manager->scriptCommand('build'),
                        $workspace->path,
                        30,
                        ['BUILD_MARKER' => $marker],
                    ),
                ]),
            ], $this->rootConfig());

            self::assertSame(0, $result->failed, sprintf('%s failed its real build-script probe.', $name));
            self::assertFileExists($workspace->path . '/' . $marker);
            $tested[] = $name;
        }

        if ($tested !== []) {
            return;
        }

        self::markTestSkipped('Install npm, yarn, or pnpm to run the real toolchain probe.');
    }

    private function workspace(): PackageWorkspace
    {
        $this->workspacePath = sys_get_temp_dir() . '/sympress_asset_compiler_toolchain_' . bin2hex(random_bytes(8));
        mkdir($this->workspacePath);
        file_put_contents($this->workspacePath . '/package.json', json_encode([
            'private' => true,
            'scripts' => [
                'build' => 'node -e "require(\'fs\').writeFileSync(process.env.BUILD_MARKER, \'ok\')"',
            ],
        ], JSON_THROW_ON_ERROR));

        return new PackageWorkspace(
            name: 'vendor/toolchain-fixture',
            type: 'wordpress-plugin',
            path: $this->workspacePath,
            build: new BuildConfig(['build'], DependencyMode::None, null, null, [], [], 30),
            packageJson: [],
        );
    }

    private function rootConfig(): RootConfig
    {
        return new RootConfig(
            rootPath: $this->workspacePath ?? '',
            autoRun: true,
            autoDiscover: true,
            stopOnFailure: true,
            maxProcesses: 1,
            processPoll: 10000,
            isolatedCache: false,
            wipeNodeModules: false,
            timeoutIncrement: 0,
            packageManager: null,
            defaults: [],
            packages: [],
            packageTypes: ['wordpress-plugin'],
            env: [],
        );
    }
}
