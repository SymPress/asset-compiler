# Migration

This package can replace legacy Composer asset compiler integrations in projects that need a PHP 8.5-compatible build workflow.

## Composer Package

Remove the old package and require the new one:

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

Packages that still require a legacy asset compiler should be migrated to require `sympress/asset-compiler` directly.

## Configuration Keys

Preferred root config:

```json
{
  "extra": {
    "sympress": {
      "asset-compiler": {
        "auto-discover": true,
        "defaults": {
          "script": "build",
          "dependencies": "install"
        }
      }
    }
  }
}
```

Preferred package config:

```json
{
  "extra": {
    "sympress": {
      "asset-compiler": {
        "script": "build"
      }
    }
  }
}
```

The flat key `extra.sympress.asset-compiler` is supported. The legacy key `extra.composer-asset-compiler` is also supported during migration.

## Command Changes

Use:

```bash
composer compile-assets
composer assets-hash
```

The compiler focuses on Composer package workspaces and package-local build locks. Use `--dry-run` to inspect the commands before running them:

```bash
composer compile-assets --dry-run
```

## Lock Files

Successful package builds write `.sympress_asset_compiler.lock` in each package directory. Existing locks can be ignored during migration:

```bash
composer compile-assets --ignore-lock='*'
```

## Recommended Migration Steps

1. Replace the Composer dependency and allowed plugin.
2. Move root configuration to `extra.sympress.asset-compiler`.
3. Move package-level configuration to nested `extra.sympress.asset-compiler` or flat `extra["sympress.asset-compiler"]`.
4. Run `composer assets-hash`.
5. Run `composer compile-assets --dry-run`.
6. Run `composer compile-assets --ignore-lock='*'`.
7. Keep the generated package-local lock files if the project tracks build state.
