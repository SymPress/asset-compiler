# Changelog

All notable changes to this package will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions should follow semantic versioning.

## [Unreleased]

### Added

- Composer 2 plugin for compiling Composer package workspace assets.
- `compile-assets` command with package filtering, dry-run support, lock ignoring, dependency control, mode resolution, and no-dev resolution.
- `assets-hash` command for debugging package build hashes.
- `assets-info` command for exporting discovered package metadata to external tooling.
- Root and package configuration through `extra.sympress.asset-compiler`.
- Migration-compatible support for `extra.composer-asset-compiler`.
- Auto-discovery for WordPress package types, SymPress asset packages, and kernel bundle metadata.
- npm, yarn, and pnpm command resolution.
- Package-local `.sympress_asset_compiler.lock` files.
- Bounded parallel process execution.
- Package-manager resolution explanations through `compile-assets --explain`.
- Root environment overrides for modes, discovery, package manager, process limits, cache isolation, cleanup, and timeout increments.
- Package-local `asset-compiler.json` and `assets-compiler.json` configuration files.
- Precompiled ZIP assets from local archives, HTTPS archives, GitHub release assets, and GitHub Actions artifacts.
- Optional isolated package-manager caches and generated `node_modules` cleanup.
- Grouped execution strategy and CLI overrides for process count, `node_modules` cleanup, and isolated cache cleanup.

### Security

- Reject unsafe precompiled asset targets before extraction.
- Reject HTTP precompiled asset sources and non-HTTPS redirects.
- Fail hard on checksum mismatches, unsafe ZIP entries, and missing production remote checksums.
- Extract precompiled ZIP archives into a staging directory before replacing existing assets.
- Ignore package-local asset compiler config files unless the root project explicitly allows them.

### Changed

- Simplified the default setup so root projects only need `auto-run` for the common case.
- Removed package-prefix based auto-discovery from the core configuration.
- Treat source hash paths as automatically discovered inputs unless a package explicitly overrides them.
- Treat the root `package-manager` as a project preference and keep npm as the final package-manager fallback.
- Keep staged dependency installation before parallel build scripts as the default execution strategy.
- Include detected Node.js and package-manager versions in build hashes.
