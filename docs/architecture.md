# Architecture

The plugin is intentionally split into a small Composer adapter and reusable application services.

## Layers

| Layer | Responsibility |
| --- | --- |
| `Composer` | Composer plugin registration, event subscriptions, and console commands. |
| `Config` | Reads and normalizes root/package configuration and mode overlays. |
| `Discovery` | Finds Composer-installed package workspaces that should be built. |
| `PackageManager` | Resolves npm, yarn, or pnpm and builds command arguments. |
| `Precompiled` | Locates, downloads, and extracts precompiled ZIP archives. |
| `Application` | Hashing, locking, task planning, process execution, and result aggregation. |

## Flow

1. Composer loads `SymPress\AssetCompiler\Composer\Plugin`.
2. Commands create an `AssetCompiler` through `CompilerFactory`.
3. `ConfigReader` resolves root and package configuration for the requested mode.
4. `PackageDiscovery` scans Composer's local repository and returns buildable workspaces.
5. `PackageManagerResolver` resolves the package manager for packages that need dependency or script steps.
6. `AssetHasher` calculates the current package build hash, including the resolved package manager when one is needed.
7. `LockRepository` skips packages with fresh locks unless the lock is ignored.
8. Precompiled assets are restored first when a matching configuration exists.
9. `BuildStepFactory` creates dependency and script steps for packages that still need a local build.
10. `TaskRunner` runs dependency steps sequentially and script steps in a bounded process pool.
11. Successful package hashes are written to `.sympress_asset_compiler.lock`.

## Design Notes

- Public state objects are immutable readonly value objects.
- Composer-specific code is kept at the boundary.
- Shell commands are passed to Symfony Process as argument arrays.
- Package workspaces are sorted by name for deterministic output.
- Parallelism is bounded and configured by the root package.
- Dependency installation and script execution are separate build steps.
- Dependency installation is serialized to reduce package-manager cache contention.
- Precompiled assets are optional, checksum-capable, bounded by download/extraction limits, and fall back to local builds when unavailable.
- Lock files are package-local so each package can be reasoned about independently.
- Lock hashes include the resolved package manager to prevent stale locks after runtime tool availability changes.

## Symfony Components

The implementation uses Symfony components where they provide stable behavior:

- `symfony/filesystem` for package-local lock writes and filesystem preparation.
- `symfony/finder` for deterministic source hashing.
- `symfony/process` API for command execution and executable discovery.

The Composer package allows a wider `symfony/process` constraint because some WordPress toolchains provide the Process API through a Composer replacement package.
