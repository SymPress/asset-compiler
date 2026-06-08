# SymPress Asset Compiler

SymPress Asset Compiler is a Composer 2 plugin for building frontend assets in PHP package workspaces. It is designed for SymPress and WordPress projects that install plugins, themes, or kernel packages through Composer and want one predictable command for dependency installation, asset compilation, and build-lock handling.

The plugin keeps Composer integration small and moves the actual work into focused services for configuration, package discovery, package-manager resolution, hashing, locking, and process execution.

## Requirements

- PHP 8.5 or newer
- Composer 2 with plugin API 2.2 or newer
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

Useful compile options:

```bash
composer compile-assets --dry-run
composer compile-assets --packages 'vendor/package,vendor/theme-*'
composer compile-assets --ignore-lock='*'
composer compile-assets --no-install
composer compile-assets --mode production --no-dev
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

When no package build config is present, packages with a `package.json` `build` script are compiled with dependency installation enabled. Source and config files used for build hashes are discovered automatically from common frontend files and directories. Omit `package-manager` to let each package choose through `packageManager`, lock files, or npm fallback.

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

## Build Locks

Every successful package build writes a package-local `.sympress_asset_compiler.lock` file. The lock stores the current build hash, so unchanged packages can be skipped on future runs.

Use `--ignore-lock='*'` to rebuild everything or `--ignore-lock='vendor/package-*'` to rebuild selected package patterns.

## Documentation

- [Commands](docs/commands.md)
- [Configuration](docs/configuration.md)
- [Architecture](docs/architecture.md)
- [Migration](docs/migration.md)

## License

This package is licensed under GPL-2.0-or-later. See [LICENSE](LICENSE).
