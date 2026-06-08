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
| `max-processes` | integer | `1` | Number of package builds to run in parallel. Values are clamped to `1..8`. |
| `process-poll` | integer | `100000` | Poll interval in microseconds for parallel process execution. |
| `package-types` | string list | WordPress package types | Composer package types considered during auto-discovery. |
| `package-prefixes` | string list | `[]` | Optional package-name prefixes for auto-discovery. |
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
| `source-paths` | string list | default hash paths | Files or directories that affect the build hash. |
| `src-paths` | string list | alias for `source-paths` | Compatibility alias. |
| `timeout` | integer | `900` | Process timeout in seconds. Minimum value is `60`. |

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

## Auto-Discovery

Auto-discovery includes packages that have a readable `package.json` with a `scripts.build` entry and match the configured discovery policy.

A package is included when:

- It has explicit asset-compiler package config.
- It is explicitly listed in root `packages`.
- Auto-discovery is enabled, it has a build script, and it matches the configured package prefixes and types.
- Its Composer package requires `sympress/assets`.
- Its Composer `extra.kernel.bundle` metadata is present.

## Build Hashes

The build hash includes:

- Composer package name and package-manager name.
- Dependency mode.
- Configured scripts and environment.
- `package.json` and common lock/config files.
- Configured source files and directories.

Fresh packages are skipped unless `--ignore-lock` matches the package name.
