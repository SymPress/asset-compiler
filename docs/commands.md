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
| `--explain` | Print lock status, precompiled configuration count, package-manager resolution details, and skip reasons. |
| `--max-processes <count>` | Override package build parallelism for this run. |
| `--execution-strategy <name>` | Override execution strategy for this run. Supported values are `staged` and `grouped`. |
| `--wipe-node-modules` | Remove generated `node_modules` after each successful package build. |
| `--keep-node-modules` | Keep `node_modules` even when cleanup is enabled in configuration. |
| `--clear-package-manager-cache` | Remove isolated package-manager caches after each successful package build. |

Examples:

```bash
composer compile-assets
composer compile-assets --dry-run
composer compile-assets --dry-run --explain
composer compile-assets --packages 'sympress/*'
composer compile-assets --ignore-lock='*'
composer compile-assets --ignore-lock='acme/theme-*'
composer compile-assets --mode production --no-dev
composer compile-assets --no-install
composer compile-assets --execution-strategy grouped --wipe-node-modules --clear-package-manager-cache
```

The command summary reports discovered packages, packages that were built, restored, or planned, packages already current through build locks, and failures. The same summary is printed when `auto-run` is enabled for Composer install or update events.

With `--explain`, each package reports whether its lock is current, stale, missing, or ignored. Packages with runnable build steps also report the resolved package manager and the signal that selected it.

The default `staged` execution strategy runs dependency installation and dependency updates in a controlled sequential phase. Build scripts then run through the bounded process pool configured by `max-processes`.

The `grouped` execution strategy runs each package as a sequential pipeline while different packages may run in parallel. This keeps install and build steps for one package close together, allowing generated `node_modules` and isolated package-manager caches to be removed immediately after that package succeeds. It is useful for large CI jobs with limited disk space.

## `assets-info`

Outputs discovered asset compiler package metadata as JSON. This is useful for custom watch commands, external Node-based orchestrators, or CI diagnostics that need package paths, build steps, package-manager resolution, hashes, and source path information without running a build.

Aliases:

- `assets:info`
- `asset-compiler:info`

Options:

| Option | Description |
| --- | --- |
| `--mode <name>` | Resolve root and package configuration against a named mode. |
| `--no-dev` | Resolve configuration as Composer no-dev mode. |
| `--packages <patterns>` | Only include comma-separated package names or fnmatch patterns. |

Examples:

```bash
composer assets-info
composer assets-info --packages 'sympress/*'
composer assets-info --mode production --no-dev
```

## `assets-hash`

Prints the current build hash for every discovered package. This is useful when debugging why a package is skipped or rebuilt.

Hashes include the resolved package manager and detected Node.js/package-manager versions when a package needs package-manager-backed dependency or script steps. This means changing from an npm fallback build to a Yarn build, or changing the local toolchain version, invalidates the old lock automatically.

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
4. Root `package-manager` preference.
5. npm fallback.

When multiple lock file families exist in the same package, the lock files are treated as ambiguous. In that case, package config or `package.json` `packageManager` must make the choice explicit, otherwise the root preference is used. If no root preference is configured, npm is used as the final fallback because it is installed with Node.js.

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

When `isolated-cache` is enabled, install/update commands receive package-specific cache flags:

| Manager | Cache flag |
| --- | --- |
| npm | `--cache <path>` |
| yarn | `--cache-folder <path>` |
| pnpm | `--store-dir <path>` |

Use `clear-package-manager-cache` or `composer compile-assets --clear-package-manager-cache` to remove those isolated caches after successful package builds.
