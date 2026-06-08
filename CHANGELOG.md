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
- Package-manager resolution explanations through `compile-assets --explain`.
- Root environment overrides for modes, discovery, package manager, process limits, cache isolation, cleanup, and timeout increments.
- Package-local `asset-compiler.json` and `assets-compiler.json` configuration files.
- Precompiled ZIP assets from local archives, HTTP(S) archives, GitHub release assets, and GitHub Actions artifacts.
- Optional isolated package-manager caches and generated `node_modules` cleanup.

### Changed

- Simplified the default setup so root projects only need `auto-run` for the common case.
- Removed package-prefix based auto-discovery from the core configuration.
- Treat source hash paths as automatically discovered inputs unless a package explicitly overrides them.
- Treat the root `package-manager` as a project preference and keep npm as the final package-manager fallback.
- Run dependency installation in a controlled sequential phase before parallel build scripts.
