# Commands

## `compile-assets`

Installs frontend dependencies when required, runs configured build scripts, and updates package-local build locks after successful builds.

Aliases:

- `assets:compile`
- `asset-compiler:compile`

Options:

| Option | Description |
| --- | --- |
| `--mode <name>` | Resolve root and package configuration against a named mode. |
| `--no-dev` | Resolve configuration as Composer no-dev mode. |
| `--ignore-lock[=<patterns>]` | Ignore fresh build locks. Use `*` for all packages or comma-separated fnmatch patterns. |
| `--packages <patterns>` | Only process comma-separated package names or fnmatch patterns. |
| `--no-install` | Skip dependency installation and run only build scripts. |
| `--dry-run` | Print the planned package-manager commands without executing them. |

Examples:

```bash
composer compile-assets
composer compile-assets --dry-run
composer compile-assets --packages 'sympress/*'
composer compile-assets --ignore-lock='*'
composer compile-assets --ignore-lock='acme/theme-*'
composer compile-assets --mode production --no-dev
composer compile-assets --no-install
```

The command summary reports discovered packages, packages that were built or planned, packages already current through build locks, and failures. The same summary is printed when `auto-run` is enabled for Composer install or update events.

## `assets-hash`

Prints the current build hash for every discovered package. This is useful when debugging why a package is skipped or rebuilt.

Alias:

- `asset-compiler:hash`

Options:

| Option | Description |
| --- | --- |
| `--mode <name>` | Resolve root and package configuration against a named mode. |
| `--no-dev` | Resolve configuration as Composer no-dev mode. |
| `--packages <patterns>` | Only print hashes for comma-separated package names or fnmatch patterns. |

Examples:

```bash
composer assets-hash
composer assets-hash --packages 'acme/plugin'
composer assets-hash --mode production --no-dev
```

## Package Manager Commands

The package manager is resolved in this order:

1. Explicit `package-manager` config.
2. `packageManager` in `package.json`.
3. Unambiguous lock files in the package directory.
4. Root `package-manager` fallback.
5. `npm` fallback.

When multiple lock file families exist in the same package, the lock files are treated as ambiguous. In that case, package config or `package.json` `packageManager` must make the choice explicit, otherwise the root fallback is used.

Install commands:

| Manager | With lock file | Without lock file |
| --- | --- | --- |
| npm | `npm ci` | `npm install --no-package-lock` |
| yarn | `yarn install --frozen-lockfile` | `yarn install` |
| pnpm | `pnpm install --frozen-lockfile` | `pnpm install` |

Update commands:

| Manager | Command |
| --- | --- |
| npm | `npm update --no-save` |
| yarn | `yarn upgrade` |
| pnpm | `pnpm update` |

Script commands support arguments by separating the script name from arguments with ` -- `:

```json
{
  "script": "build -- --mode production"
}
```
