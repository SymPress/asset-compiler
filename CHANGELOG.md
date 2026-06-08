# Changelog

All notable changes to this package will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions should follow semantic versioning.

## [Unreleased]

### Added

- Composer 2 plugin for compiling Composer package workspace assets.
- `compile-assets` command with package filtering, dry-run support, lock ignoring, dependency control, mode resolution, and no-dev resolution.
- `assets-hash` command for debugging package build hashes.
- Root and package configuration through `extra.sympress.asset-compiler`.
- Migration-compatible support for `extra.composer-asset-compiler`.
- Auto-discovery for WordPress package types, SymPress asset packages, and kernel bundle metadata.
- npm, yarn, and pnpm command resolution.
- Package-local `.sympress_asset_compiler.lock` files.
- Bounded parallel process execution.

### Changed

- Simplified the default setup so root projects only need `auto-run` for the common case.
- Removed package-prefix based auto-discovery from the core configuration.
- Treat source hash paths as automatically discovered inputs unless a package explicitly overrides them.
