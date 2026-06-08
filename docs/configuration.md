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
| `process-poll` | integer | `100000` | Poll interval in microseconds for parallel process execution. |
| `isolated-cache` | boolean | `false` | Use package-specific npm/yarn/pnpm cache directories below the system temp directory. |
| `wipe-node-modules` | boolean | `false` | Remove `node_modules` after a successful package build when the compiler created it during the run. |
| `timeout-increment` | integer | `0` | Additional timeout seconds added progressively across the package plan. |
| `package-manager` | string | auto-detected | Project-wide fallback package manager. Supports `npm`, `yarn`, and `pnpm`. Package-level config, `package.json` `packageManager`, and unambiguous lock files still win per package. |
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
| `precompiled` | object or object list | `[]` | Restore ZIP assets from archives or GitHub before falling back to local builds. |
| `pre-compiled` | object or object list | alias for `precompiled` | Migration alias. |

## Package Config Files

Packages can store the same package build object in a root-level JSON file:

- `asset-compiler.json`
- `assets-compiler.json`

The file wins over Composer `extra` for that package. Short forms are supported:

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
| `COMPOSER_ASSET_COMPILER_PROCESSES_POLL` | Override `process-poll`. |
| `COMPOSER_ASSET_COMPILER_ISOLATED_CACHE` | Override `isolated-cache`. |
| `COMPOSER_ASSET_COMPILER_WIPE_NODE_MODULES` | Override `wipe-node-modules`. |
| `COMPOSER_ASSET_COMPILER_TIMEOUT_INCR` | Override `timeout-increment`. |
| `COMPOSER_ASSET_COMPILER_PACKAGE_MANAGER` | Override the root package-manager fallback. |

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
      "stability": "stable"
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
| `archive` or `zip` | Local file path or HTTP(S) ZIP URL. |
| `github-release` or `gh-release-zip` | GitHub release asset matched by `source`; requires `config.repository`, optional `config.tag`. |
| `github-artifact` or `gh-action-artifact` | GitHub Actions artifact matched by `source`; requires `config.repository`. |

Supported placeholders in `source`, `target`, and GitHub `tag` are `${name}`, `${vendor}`, `${package}`, `${version}`, `${ref}`, `${reference}`, and `${stability}`.

GitHub adapters use `config.token`, `GITHUB_TOKEN`, or `GH_TOKEN` when available. ZIP targets are cleaned by default; set `"config": {"clean-target": false}` to merge into an existing target.

## Auto-Discovery

Auto-discovery includes packages that have a readable `package.json` with a `scripts.build` entry and match the configured discovery policy.

A package is included when:

- It has explicit asset-compiler package config.
- It is explicitly listed in root `packages`.
- Auto-discovery is enabled, it has a build script, and it is a local Composer path package with a configured package type.
- Auto-discovery is enabled, it has a build script, and its Composer package requires `sympress/assets`.
- Auto-discovery is enabled, it has a build script, and its Composer `extra.kernel.bundle` metadata is present.

## Build Hashes

The build hash includes:

- Composer package name, explicit package-manager name, and root package-manager fallback.
- Dependency mode.
- Configured scripts and environment.
- Precompiled asset configuration.
- `composer.json`, `package.json`, common lock files, and frontend config files.
- Common frontend source directories such as `resources`, `frontend`, `client`, and `assets-src`.
- Configured `src-paths` when a package needs to override the automatic inputs.

Fresh packages are skipped unless `--ignore-lock` matches the package name.
