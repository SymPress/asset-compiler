<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Config;

use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use InvalidArgumentException;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use SymPress\AssetCompiler\PackageManager\PackageManager;

final readonly class ConfigReader
{
    /**
     * @var list<string>
     */
    private const SUPPORTED_PRECOMPILED_ADAPTERS = [
        'archive',
        'zip',
        'github-release',
        'gh-release-zip',
        'github-artifact',
        'gh-action-artifact',
    ];

    private ModeResolver $modes;

    private JsonEncoder $json;

    public function __construct(?string $mode, bool $devMode)
    {
        $this->modes = new ModeResolver($mode, $devMode);
        $this->json = new JsonEncoder(defaultContext: [
            JsonDecode::ASSOCIATIVE => true,
            JsonDecode::OPTIONS => JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR,
        ]);
    }

    public function rootConfig(RootPackageInterface $package, string $rootPath): RootConfig
    {
        $data = $this->modes->root($this->packageExtra($package, $rootPath));
        $precompiling = $this->envBool('COMPOSER_ASSET_COMPILER_PRECOMPILING', false);

        return new RootConfig(
            rootPath: rtrim($rootPath, '/'),
            autoRun: $this->bool($this->value($data, 'auto-run'), false),
            autoDiscover: $precompiling ? false : $this->envBool(
                'COMPOSER_ASSET_COMPILER_AUTO_DISCOVER',
                $this->bool($this->value($data, 'auto-discover'), true),
            ),
            stopOnFailure: $this->envBool(
                'COMPOSER_ASSET_COMPILER_STOP_ON_FAILURE',
                $this->bool($this->value($data, 'stop-on-failure'), true),
            ),
            maxProcesses: max(1, min(8, $this->envInt(
                'COMPOSER_ASSET_COMPILER_MAX_PROCESSES',
                $this->int($this->value($data, 'max-processes'), 4),
            ))),
            processPoll: max(10000, $this->envInt(
                'COMPOSER_ASSET_COMPILER_PROCESSES_POLL',
                $this->int($this->value($data, 'process-poll'), 100000),
            )),
            isolatedCache: $this->envBool(
                'COMPOSER_ASSET_COMPILER_ISOLATED_CACHE',
                $this->bool($this->value($data, 'isolated-cache'), false),
            ),
            wipeNodeModules: $this->envBool(
                'COMPOSER_ASSET_COMPILER_WIPE_NODE_MODULES',
                $this->bool($this->value($data, 'wipe-node-modules'), false),
            ),
            timeoutIncrement: max(0, $this->envInt(
                'COMPOSER_ASSET_COMPILER_TIMEOUT_INCR',
                $this->int($this->value($data, 'timeout-increment'), 0),
            )),
            packageManager: $this->packageManager(
                $this->envString('COMPOSER_ASSET_COMPILER_PACKAGE_MANAGER')
                ?? $this->value($data, 'package-manager'),
                'root package-manager',
            ),
            defaults: $this->array($this->value($data, 'defaults')),
            packages: $this->array($this->value($data, 'packages')),
            packageTypes: $this->stringList(
                $this->value($data, 'package-types'),
                ['wordpress-plugin', 'wordpress-theme', 'wordpress-muplugin'],
            ),
            env: $this->env($this->value($data, 'default-env')),
        );
    }

    /**
     * @param array<string, mixed>|null $rootOverride
     * @param array<string, mixed> $packageJson
     */
    public function buildConfig(
        PackageInterface $package,
        RootConfig $root,
        array $packageJson,
        ?array $rootOverride,
        bool $forceDefaults,
        ?string $packagePath = null,
    ): ?BuildConfig {
        $packageExtra = $this->packageExtra($package, $packagePath);
        $hasPackageExtra = $packageExtra !== [];
        /** @var array<string, mixed> $base */
        $base = [];

        if ($forceDefaults || !$hasPackageExtra) {
            $base = $root->defaults;
        }

        if (!$forceDefaults && $hasPackageExtra) {
            $base = array_replace_recursive($base, $packageExtra);
        }

        if ($rootOverride !== null) {
            $base = array_replace_recursive($base, $rootOverride);
        }

        $base = $this->modes->root(self::stringKeyedArray($base));

        if ($base === [] && !$this->hasBuildScript($packageJson)) {
            return null;
        }

        if ($base === []) {
            $base = ['script' => 'build'];
        }

        $env = array_replace($root->env, $this->env($this->value($base, 'default-env')));
        $scripts = $this->scripts($this->value($base, 'script'), $packageJson, $env);
        $dependencyMode = DependencyMode::fromMixed(
            $this->value($base, 'dependencies'),
            $scripts === [] ? DependencyMode::None : DependencyMode::Install,
        );

        $config = new BuildConfig(
            scripts: $scripts,
            dependencyMode: $dependencyMode,
            packageManager: $this->packageManager($this->value($base, 'package-manager'), sprintf('%s package-manager', $package->getName())),
            packageManagerPreference: $root->packageManager,
            isolatedCache: $this->bool($this->value($base, 'isolated-cache'), $root->isolatedCache),
            env: $env,
            sourcePaths: $this->stringList(
                $this->value($base, 'source-paths') ?? $this->value($base, 'src-paths'),
                [],
            ),
            timeout: max(60, $this->int($this->value($base, 'timeout'), 900)),
            precompiledAssets: $this->precompiledAssets(
                $this->value($base, 'precompiled') ?? $this->value($base, 'pre-compiled'),
            ),
        );

        return $config->runnable() ? $config : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function packageExtra(PackageInterface $package, ?string $packagePath = null): array
    {
        $fileConfig = $this->configFile($packagePath);

        if ($fileConfig !== null) {
            return $this->normalizeConfig($fileConfig);
        }

        $extra = $package->getExtra();
        $config = $extra[RootConfig::EXTRA_KEY] ?? null;

        if (!is_array($config)) {
            $nested = $extra['sympress'] ?? null;
            $config = is_array($nested) ? ($nested[RootConfig::NESTED_EXTRA_KEY] ?? null) : null;
        }

        if (!is_array($config)) {
            $config = $extra[RootConfig::LEGACY_EXTRA_KEY] ?? null;
        }

        return $this->normalizeConfig($config);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeConfig(mixed $config): array
    {
        if (is_string($config)) {
            return ['script' => $config];
        }

        if (is_array($config) && array_is_list($config)) {
            return ['script' => $config];
        }

        return is_array($config) ? self::stringKeyedArray($config) : [];
    }

    private function configFile(?string $packagePath): mixed
    {
        if (!is_string($packagePath) || $packagePath === '') {
            return null;
        }

        foreach (['asset-compiler.json', 'assets-compiler.json'] as $name) {
            $file = rtrim($packagePath, '/') . '/' . $name;

            if (!is_file($file) || !is_readable($file)) {
                continue;
            }

            $contents = file_get_contents($file);

            if (!is_string($contents)) {
                throw new InvalidArgumentException(sprintf('Could not read JSON for asset compiler config file "%s".', $file));
            }

            if (trim($contents) === '') {
                return [];
            }

            try {
                $decoded = $this->json->decode($contents, JsonEncoder::FORMAT);
            } catch (NotEncodableValueException $exception) {
                throw new InvalidArgumentException(
                    sprintf('Could not deserialize JSON for asset compiler config file "%s": %s.', $file, $exception->getMessage()),
                    previous: $exception,
                );
            }

            if (!is_array($decoded)) {
                return $decoded;
            }

            return $decoded[RootConfig::EXTRA_KEY]
                ?? (is_array($decoded['sympress'] ?? null)
                    ? ($decoded['sympress'][RootConfig::NESTED_EXTRA_KEY] ?? $decoded)
                    : $decoded);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $packageJson
     */
    private function hasBuildScript(array $packageJson): bool
    {
        $scripts = $packageJson['scripts'] ?? null;

        return is_array($scripts) && is_string($scripts['build'] ?? null);
    }

    /**
     * @param array<string, mixed> $packageJson
     * @param array<string, string|false> $env
     * @return list<string>
     */
    private function scripts(mixed $raw, array $packageJson, array $env): array
    {
        $raw = $this->modes->property($raw);

        if (is_string($raw)) {
            $raw = [$raw];
        }

        if (!is_array($raw)) {
            $raw = $this->hasBuildScript($packageJson) ? ['build'] : [];
        }

        $scripts = [];

        foreach ($raw as $script) {
            if (is_string($script) && trim($script) !== '') {
                $scripts[] = $this->interpolate(trim($script), $env);
            }
        }

        return array_values(array_unique($scripts));
    }

    /**
     * @param array<string, string|false> $env
     */
    private function interpolate(string $value, array $env): string
    {
        return (string) preg_replace_callback(
            '/\$\{([A-Z0-9_]+)\}/i',
            static function (array $matches) use ($env): string {
                $name = $matches[1];
                $value = $env[$name] ?? getenv($name);

                return is_string($value) ? $value : '';
            },
            $value,
        );
    }

    private function packageManager(mixed $value, string $scope): ?string
    {
        $value = $this->modes->property($value);

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $value = strtolower(trim($value));

        return match ($value) {
            PackageManager::NPM, PackageManager::YARN, PackageManager::PNPM => $value,
            default => throw new InvalidArgumentException(
                sprintf('Unsupported asset compiler %s "%s". Supported values are npm, yarn, and pnpm.', $scope, $value),
            ),
        };
    }

    /**
     * @return array<string, string|false>
     */
    private function env(mixed $value): array
    {
        $value = $this->modes->property($value);

        if (!is_array($value)) {
            return [];
        }

        $env = [];

        foreach ($value as $name => $envValue) {
            if (!is_string($name) || trim($name) === '') {
                continue;
            }

            if ($envValue === false || is_string($envValue)) {
                $env[$name] = $envValue;
            }
        }

        return $env;
    }

    /**
     * @return array<string, mixed>
     */
    private function array(mixed $value): array
    {
        $value = $this->modes->property($value);

        return is_array($value) ? self::stringKeyedArray($value) : [];
    }

    /**
     * @param list<string> $default
     * @return list<string>
     */
    private function stringList(mixed $value, array $default): array
    {
        $value = $this->modes->property($value);

        if (is_string($value)) {
            $value = [$value];
        }

        if (!is_array($value)) {
            return $default;
        }

        $list = [];

        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $list[] = trim($item);
            }
        }

        return $list === [] ? $default : array_values(array_unique($list));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function value(array $data, string $key): mixed
    {
        return array_key_exists($key, $data) ? $this->modes->property($data[$key]) : null;
    }

    private function bool(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private function envBool(string $name, bool $default): bool
    {
        $value = $this->envString($name);

        return $value === null ? $default : $this->bool($value, $default);
    }

    private function envInt(string $name, int $default): int
    {
        $value = $this->envString($name);

        return $value === null ? $default : $this->int($value, $default);
    }

    private function envString(string $name): ?string
    {
        $value = getenv($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function int(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @return list<PrecompiledAssetConfig>
     */
    private function precompiledAssets(mixed $value): array
    {
        $value = $this->modes->property($value);

        if (!is_array($value)) {
            return [];
        }

        $items = array_is_list($value) ? $value : [$value];
        $configs = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $adapter = $item['adapter'] ?? null;
            $source = $item['source'] ?? null;
            $target = $item['target'] ?? 'assets';

            if (!is_string($adapter) || trim($adapter) === '' || !is_string($source) || trim($source) === '') {
                continue;
            }

            $adapter = strtolower(trim($adapter));

            if (!in_array($adapter, self::SUPPORTED_PRECOMPILED_ADAPTERS, true)) {
                throw new InvalidArgumentException(sprintf('Unsupported precompiled asset adapter "%s".', $adapter));
            }

            $configs[] = new PrecompiledAssetConfig(
                adapter: $adapter,
                source: trim($source),
                target: is_string($target) && trim($target) !== '' ? trim($target) : 'assets',
                config: is_array($item['config'] ?? null) ? self::stringKeyedArray($item['config']) : [],
                stability: is_string($item['stability'] ?? null) ? strtolower(trim($item['stability'])) : null,
                checksum: $this->checksum($item),
            );
        }

        return $configs;
    }

    /**
     * @param array<array-key, mixed> $item
     */
    private function checksum(array $item): ?string
    {
        $config = is_array($item['config'] ?? null) ? $item['config'] : [];
        $checksum = $item['checksum'] ?? $item['sha256'] ?? $config['checksum'] ?? $config['sha256'] ?? null;

        if (!is_string($checksum) || trim($checksum) === '') {
            return null;
        }

        return trim($checksum);
    }

    /**
     * @param array<array-key, mixed> $value
     * @return array<string, mixed>
     */
    private static function stringKeyedArray(array $value): array
    {
        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

}
