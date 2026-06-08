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

Root configuration lives in `extra.sympress.asset-compiler`:

```json
{
  "extra": {
    "sympress": {
      "asset-compiler": {
        "auto-run": false,
        "auto-discover": true,
        "max-processes": 2,
        "package-prefixes": ["sympress/", "acme/"],
        "package-types": ["wordpress-plugin", "wordpress-theme", "wordpress-muplugin"],
        "default-env": {
          "npm_config_legacy_peer_deps": "true"
        },
        "defaults": {
          "script": "build",
          "dependencies": "install",
          "source-paths": [
            "package.json",
            "package-lock.json",
            "pnpm-lock.yaml",
            "yarn.lock",
            "webpack.config.js",
            "vite.config.js",
            "resources"
          ]
        }
      }
    }
  }
}
```

See [Configuration](docs/configuration.md) for all supported keys and mode handling.

## Package Configuration

Individual packages can opt in or override defaults through their own Composer `extra` section:

```json
{
  "extra": {
    "sympress": {
      "asset-compiler": {
        "script": ["build", "build:admin"],
        "dependencies": "install",
        "package-manager": "npm",
        "timeout": 900,
        "source-paths": ["package.json", "assets", "resources"]
      }
    }
  }
}
```

A short string form is also supported:

```json
{
  "extra": {
    "sympress.asset-compiler": "build"
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
