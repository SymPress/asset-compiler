# Configuration

Configuration can be declared in the root Composer package and in individual installed packages.

The preferred key is nested under `extra.sympress.asset-compiler`:

```json
{
  "extra": {
    "sympress": {
      "asset-compiler": {}
    }
  }
}
```

The flat key `extra.sympress.asset-compiler` is also supported. The legacy key `extra.composer-asset-compiler` is accepted for migration compatibility.

## Root Keys

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `auto-run` | boolean | `false` | Run the compiler after Composer install and update events. Auto-run uses a late event priority and prints the same compilation summary as the manual command. |
| `auto-discover` | boolean | `true` | Discover packages with build scripts automatically. |
| `stop-on-failure` | boolean | `true` | Stop the run after the first failing package. |
| `max-processes` | integer | `4` | Number of package builds to run in parallel. Values are clamped to `1..8`. |
| `execution-strategy` | string | `staged` | Process scheduling strategy. Supports `staged` and `grouped`. |
| `process-poll` | integer | `100000` | Poll interval in microseconds for parallel process execution. |
| `isolated-cache` | boolean | `false` | Use package-specific npm/yarn/pnpm cache directories below the system temp directory. |
| `wipe-node-modules` | boolean | `false` | Remove `node_modules` after a successful package build when the compiler created it during the run. |
| `clear-package-manager-cache` | boolean | `false` | Remove isolated npm/yarn/pnpm caches after a successful package build. Only applies when the package uses `isolated-cache`. |
| `timeout-increment` | integer | `0` | Additional timeout seconds added progressively across the package plan. |
| `package-manager` | string | auto-detected | Project-wide package-manager preference. Supports `npm`, `yarn`, and `pnpm`. Package-level config, `package.json` `packageManager`, and unambiguous lock files still win per package. npm remains the final fallback. Unsupported values fail early. |
| `allow-package-config-files` | boolean | `false` | Allow package-local `asset-compiler.json` and `assets-compiler.json` files to override Composer `extra` for that package. Keep disabled for stricter supply-chain control. |
| `require-precompiled-checksum` | boolean | `true` in `--no-dev`, otherwise `false` | Require SHA-256 checksums for remote precompiled archives. |
| `package-types` | string list | WordPress package types | Local path package types considered during auto-discovery. |
| `default-env` | object | `{}` | Environment variables passed to asset commands. Use `false` to unset a variable. |
| `defaults` | object | `{}` | Default package build configuration. |
| `packages` | object | `{}` | Explicit package selections, overrides, or disabled packages. |

## Package Build Keys

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `script` | string or string list | `build` when `package.json` has a build script | Script or scripts to run. |
| `dependencies` | string | `install` when scripts are present | `install`, `update`, or `none`. |
| `package-manager` | string | auto-detected | `npm`, `yarn`, or `pnpm`. |
| `default-env` | object | `{}` | Package-level command environment merged over root environment. |
| `src-paths` | string list | auto-discovered hash inputs | Optional files or directories that affect the build hash. |
| `source-paths` | string list | alias for `src-paths` | Compatibility alias. |
| `timeout` | integer | `900` | Process timeout in seconds. Minimum value is `60`. |
| `isolated-cache` | boolean | root value | Override isolated package-manager cache behavior for one package. |
| `require-precompiled-checksum` | boolean | root value | Override remote precompiled checksum enforcement for one package. |
| `precompiled` | object or object list | `[]` | Restore ZIP assets from archives or GitHub before falling back to local builds. |
| `pre-compiled` | object or object list | alias for `precompiled` | Migration alias. |

When package config files are enabled, they must contain valid JSON. Invalid local config files fail with a clear error instead of being ignored silently.

## Execution Strategies

The default `staged` strategy installs or updates dependencies for all selected packages first, then runs build scripts through the bounded process pool. This keeps package-manager installs predictable and preserves the historical behavior.

The `grouped` strategy runs each package as a sequential pipeline while different packages may run in parallel. For large CI jobs, pair it with `wipe-node-modules`, `isolated-cache`, and `clear-package-manager-cache` to lower peak disk usage:

```json
{
  "execution-strategy": "grouped",
  "max-processes": 2,
  "isolated-cache": true,
  "wipe-node-modules": true,
  "clear-package-manager-cache": true
}
```

## Package Config Files

When the root project sets `"allow-package-config-files": true`, packages can store the same package build object in a root-level JSON file:

- `asset-compiler.json`
- `assets-compiler.json`

The file wins over Composer `extra` for that package only when package config files are allowed. Short forms are supported:

```json
"build"
```

Object form:

```json
{
  "script": "build",
  "dependencies": "install",
  "package-manager": "yarn"
}
```

## Package Selection

The root `packages` map can require, disable, or override specific packages.

```json
{
  "extra": {
    "sympress": {
      "asset-compiler": {
        "packages": {
          "acme/theme": true,
          "acme/experimental-*": false,
          "acme/admin": {
            "script": ["build", "build:admin"],
            "dependencies": "install"
          },
          "acme/legacy": "$force-defaults"
        }
      }
    }
  }
}
```

Supported values:

| Value | Meaning |
| --- | --- |
| `false` or `"disabled"` | Never build matching packages. |
| `true` or `"package-or-defaults"` | Explicitly include matching packages and prefer package config when present. |
| `"$force-defaults"` or `"$defaults"` | Explicitly include matching packages and apply root defaults. |
| string | Use the string as the script name. |
| string list | Use the list as script names. |
| object | Merge the object as a package override. |

Concrete package names in `packages` are treated as required when `stop-on-failure` is enabled. If a required package is missing from Composer's local repository, compilation fails early.

## Modes

Any config object or config property can contain a `$mode` object. The selected mode is resolved in this order:

1. The explicit `--mode` option.
2. `$default-no-dev` when the command is run with `--no-dev`.
3. `$default`.

The legacy `env` key is treated like `$mode` for compatibility.

```json
{
  "extra": {
    "sympress": {
      "asset-compiler": {
        "defaults": {
          "script": {
            "$mode": {
              "$default": "build",
              "production": "build:production"
            }
          },
          "dependencies": {
            "$mode": {
              "$default": "install",
              "$default-no-dev": "none"
            }
          }
        }
      }
    }
  }
}
```

## Environment Overrides

The compiler reads these environment variables before root configuration is finalized:

| Variable | Meaning |
| --- | --- |
| `COMPOSER_ASSETS_COMPILER` or `COMPOSER_ASSET_COMPILER` | Default mode when no `--mode` option is passed. |
| `COMPOSER_ASSET_COMPILER_PRECOMPILING` | Disable auto-discovery for precompilation-oriented runs. |
| `COMPOSER_ASSET_COMPILER_AUTO_DISCOVER` | Override `auto-discover`. |
| `COMPOSER_ASSET_COMPILER_STOP_ON_FAILURE` | Override `stop-on-failure`. |
| `COMPOSER_ASSET_COMPILER_MAX_PROCESSES` | Override `max-processes`. |
| `COMPOSER_ASSET_COMPILER_EXECUTION_STRATEGY` | Override `execution-strategy`. |
| `COMPOSER_ASSET_COMPILER_PROCESSES_POLL` | Override `process-poll`. |
| `COMPOSER_ASSET_COMPILER_ISOLATED_CACHE` | Override `isolated-cache`. |
| `COMPOSER_ASSET_COMPILER_WIPE_NODE_MODULES` | Override `wipe-node-modules`. |
| `COMPOSER_ASSET_COMPILER_CLEAR_PACKAGE_MANAGER_CACHE` | Override `clear-package-manager-cache`. |
| `COMPOSER_ASSET_COMPILER_TIMEOUT_INCR` | Override `timeout-increment`. |
| `COMPOSER_ASSET_COMPILER_PACKAGE_MANAGER` | Override the root package-manager preference. |
| `COMPOSER_ASSET_COMPILER_ALLOW_PACKAGE_CONFIG_FILES` | Override `allow-package-config-files`. |
| `COMPOSER_ASSET_COMPILER_REQUIRE_PRECOMPILED_CHECKSUM` | Override `require-precompiled-checksum`. |

Script strings may reference `${NAME}` placeholders. Values are resolved from merged `default-env` first and then from the process environment.

## Precompiled Assets

Precompiled assets are attempted before local dependency installation and build scripts. A successful restore writes the package lock hash. If no matching precompiled config exists or the restore fails, the compiler falls back to the normal local build.

```json
{
  "precompiled": [
    {
      "adapter": "archive",
      "source": "https://example.test/${package}-${version}.zip",
      "target": "assets",
      "stability": "stable",
      "checksum": "sha256:0000000000000000000000000000000000000000000000000000000000000000"
    },
    {
      "adapter": "github-artifact",
      "source": "assets-${ref}",
      "target": "assets",
      "config": {
        "repository": "vendor/repository"
      }
    }
  ]
}
```

Supported adapters:

| Adapter | Source |
| --- | --- |
| `archive` or `zip` | Local file path or HTTPS ZIP URL. |
| `github-release` or `gh-release-zip` | GitHub release asset matched by `source`; requires `config.repository`, optional `config.tag`. |
| `github-artifact` or `gh-action-artifact` | GitHub Actions artifact matched by `source`; requires `config.repository`. |

Supported placeholders in `source`, `target`, `checksum`, and GitHub `tag` are `${name}`, `${vendor}`, `${package}`, `${version}`, `${ref}`, `${reference}`, and `${stability}`.

GitHub adapters use `config.token`, `GITHUB_TOKEN`, or `GH_TOKEN` when available. Tokens are scoped to trusted GitHub API/download hosts and are not forwarded to arbitrary redirect targets. ZIP targets are relative package paths, are validated against traversal and symlink escapes, and are cleaned by default; set `"config": {"clean-target": false}` to merge into an existing target.

Optional SHA-256 verification can be configured through `checksum`, `sha256`, `config.checksum`, or `config.sha256`. Values may be raw 64-character hashes or `sha256:<hash>`.
Remote precompiled archives require a SHA-256 checksum by default when the compiler runs in Composer no-dev mode.

Precompiled downloads and extraction are intentionally bounded:

- Downloads use HTTPS only and reject non-HTTPS redirects.
- Generic archive redirects must stay on the same host.
- GitHub archive redirects are restricted to known GitHub/GitHub-backed download hosts.
- Downloads are limited to 100 MiB by default.
- ZIP extraction is limited to 20,000 entries, 100 MiB per entry, and 512 MiB total uncompressed size.
- ZIP entries with absolute paths or `..` path segments are rejected.
- ZIP archives are extracted into a package-local staging directory before replacing or merging into the target.

## Auto-Discovery

Auto-discovery includes packages that have a readable `package.json` with a `scripts.build` entry and match the configured discovery policy.

A package is included when:

- It has explicit Composer `extra` asset-compiler package config.
- Package config files are enabled and it has a package-local asset compiler config file.
- It is explicitly listed in root `packages`.
- Auto-discovery is enabled, it has a build script, and it is a local Composer path package with a configured package type.
- Auto-discovery is enabled, it has a build script, and its Composer package requires `sympress/assets`.
- Auto-discovery is enabled, it has a build script, and its Composer `extra.kernel.bundle` metadata is present.

## Build Hashes

The build hash includes:

- Composer package name, explicit package-manager name, and root package-manager preference.
- The resolved package manager, its version, the Node.js version, and why the manager was selected.
- Dependency mode.
- Configured scripts and environment.
- Precompiled asset configuration.
- `composer.json`, `package.json`, common lock files, and frontend config files.
- Common frontend source directories such as `resources`, `src`, `source`, `frontend`, `client`, `assets-src`, `blocks`, `components`, `scripts`, `styles`, `views`, and `templates`.
- Configured `src-paths` when a package needs to override the automatic inputs.

Fresh packages are skipped unless `--ignore-lock` matches the package name.
