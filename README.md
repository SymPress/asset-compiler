# SymPress Asset Compiler

SymPress Asset Compiler is a Composer 2 plugin for building frontend assets in PHP package workspaces. It is designed for SymPress and WordPress projects that install plugins, themes, or kernel packages through Composer and want one predictable command for dependency installation, asset compilation, and build-lock handling.

The plugin keeps Composer integration small and moves the actual work into focused services for configuration, package discovery, package-manager resolution, hashing, locking, and process execution.

## Requirements

- PHP 8.5 or newer
- Composer 2 with plugin API 2.2 or newer
- PHP cURL and Zip extensions for precompiled archive downloads
- npm, yarn, or pnpm on the runtime PATH
- Symfony Filesystem, Finder, and Process-compatible components

## Installation

Require the package in the root project and allow the Composer plugin:

```json
{
  "require": {
    "sympress/asset-compiler": "^1.0"
  },
  "config": {
    "allow-plugins": {
      "sympress/asset-compiler": true
    }
  }
}
```

For monorepos or path repositories, use the package version strategy of the root project.

## Commands

Compile assets for discovered packages:

```bash
composer compile-assets
```

Print the current build hash for discovered packages:

```bash
composer assets-hash
```

Print package metadata for external tooling:

```bash
composer assets-info
```

Run the local quality gate used by this package:

```bash
composer qa
```

Useful compile options:

```bash
composer compile-assets --dry-run
composer compile-assets --dry-run --explain
composer compile-assets --packages 'vendor/package,vendor/theme-*'
composer compile-assets --ignore-lock='*'
composer compile-assets --no-install
composer compile-assets --mode production --no-dev
composer compile-assets --execution-strategy grouped --wipe-node-modules --clear-package-manager-cache
```

See [Commands](docs/commands.md) for the full command reference.

## Root Configuration

Root configuration can be as small as enabling auto-run:

```json
{
  "extra": {
    "sympress.asset-compiler": {
      "auto-run": true,
      "package-manager": "yarn"
    }
  }
}
```

When no package build config is present, packages with a `package.json` `build` script are compiled with dependency installation enabled. Source and config files used for build hashes are discovered automatically from common frontend files and directories. The root `package-manager` is a project preference: package-level config, `package.json` `packageManager`, and unambiguous lock files still win per package. If nothing can be resolved, npm is the final fallback because it ships with Node.js. Invalid package-manager names fail early.

See [Configuration](docs/configuration.md) for all supported keys and mode handling.

## Package Configuration

Individual packages can opt in or override defaults through their own Composer `extra` section. The short form is enough for most packages:

```json
{
  "extra": {
    "sympress.asset-compiler": "build"
  }
}
```

Use the object form only when a package needs custom behavior:

```json
{
  "extra": {
    "sympress": {
      "asset-compiler": {
        "script": ["build", "build:admin"],
        "dependencies": "install",
        "package-manager": "npm",
        "timeout": 900,
        "src-paths": ["resources", "webpack.config.js"]
      }
    }
  }
}
```

Packages that need a different package manager can also declare it in `package.json`, for example `"packageManager": "npm@10.9.0"`. When a package has conflicting lock files, the root preference is used unless package config or `packageManager` makes the choice explicit. Without a root preference, npm is used.

Packages may also move their build config into `asset-compiler.json` or `assets-compiler.json` in the package root when the root project explicitly enables package config files. The file contains the same object that would otherwise live under Composer `extra.sympress.asset-compiler`.

## Precompiled Assets

Packages can restore ZIP archives instead of building locally. This is useful for production installs, CI artifacts, and release packages:

```json
{
  "precompiled": {
    "adapter": "github-release",
    "source": "assets-${version}.zip",
    "target": "assets",
    "checksum": "sha256:<64 hex characters>",
    "config": {
      "repository": "vendor/repository",
      "tag": "${version}"
    }
  }
}
```

Supported adapters are `archive`, `zip`, `github-release`, `gh-release-zip`, `github-artifact`, and `gh-action-artifact`. If precompiled assets are unavailable, the compiler falls back to the normal build. Security failures such as checksum mismatches, unsafe targets, unsafe ZIP entries, HTTP sources, or missing production checksums fail the run. Downloads use HTTPS-only requests and redirects, bounded timeouts, archive size limits, and ZIP extraction limits. GitHub tokens are only sent to trusted GitHub API/download hosts and are not forwarded to arbitrary redirect targets.

## Build Locks

Every successful package build writes a package-local `.sympress_asset_compiler.lock` file. The lock stores the current build hash, so unchanged packages can be skipped on future runs. The hash includes the resolved package manager and toolchain versions, source inputs, build config, environment, and precompiled asset configuration.

Use `--ignore-lock='*'` to rebuild everything or `--ignore-lock='vendor/package-*'` to rebuild selected package patterns.

## Documentation

- [Commands](docs/commands.md)
- [Configuration](docs/configuration.md)
- [Architecture](docs/architecture.md)
- [Migration](docs/migration.md)

## License

This package is licensed under GPL-2.0-or-later. See [LICENSE](LICENSE).
