<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Support;

use InvalidArgumentException;

final class BuildEnvironment
{
    private const array INHERITED = ['PATH', 'HOME', 'USER', 'LOGNAME', 'TMPDIR', 'TMP', 'TEMP', 'LANG', 'LANGUAGE', 'LC_ALL', 'LC_CTYPE', 'TERM', 'CI', 'GITHUB_ACTIONS', 'SYSTEMROOT', 'WINDIR'];

    /**
     * @param array<string, string|false> $environment
     * @return array<string, string|false>
     */
    public static function configured(array $environment): array
    {
        foreach ($environment as $name => $value) {
            if ($value !== false && self::protected($name)) {
                throw new InvalidArgumentException(sprintf('Protected build environment variable %s cannot be configured.', $name));
            }
        }

        return $environment;
    }

    /**
     * @param array<string, string|false> $environment
     * @return array<string, string|false>
     */
    public static function process(array $environment): array
    {
        $result = [];
        $inherited = getenv();
        // phpcs:ignore SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable -- Process inherits both PHP environment sources; explicitly remove them at this boundary.
        foreach (array_keys(array_merge($inherited, $_SERVER, $_ENV)) as $name) {
            if (!is_string($name) || (in_array($name, self::INHERITED, true) && !self::protected($name))) {
                continue;
            }

            $result[$name] = false;
        }

        return array_replace($result, self::configured($environment));
    }

    private static function protected(string $name): bool
    {
        return preg_match('/AUTH|TOKEN|SECRET|PASSWORD|PASSWD|CREDENTIAL|PRIVATE|(^|_)KEY($|_)|SSH_|^AWS_|^AZURE_|^GOOGLE_|^GIT_|^COMPOSER_|^NPM_CONFIG_|^YARN_|^PNPM_|^DATABASE_|^DB_|^BASH_ENV$|^ENV$|^LD_|^DYLD_|^NODE_OPTIONS$|^PYTHONPATH$|^PHP_INI_SCAN_DIR$/i', $name) === 1;
    }
}
