# Architecture

The plugin is intentionally split into a small Composer adapter and reusable application services.

## Layers

| Layer | Responsibility |
| --- | --- |
| `Composer` | Composer plugin registration, event subscriptions, and console commands. |
| `Config` | Reads and normalizes root/package configuration and mode overlays. |
| `Discovery` | Finds Composer-installed package workspaces that should be built. |
| `PackageManager` | Resolves npm, yarn, or pnpm and builds command arguments. |
| `Application` | Hashing, locking, task planning, process execution, and result aggregation. |

## Flow

1. Composer loads `SymPress\AssetCompiler\Composer\Plugin`.
2. Commands create an `AssetCompiler` through `CompilerFactory`.
3. `ConfigReader` resolves root and package configuration for the requested mode.
4. `PackageDiscovery` scans Composer's local repository and returns buildable workspaces.
5. `AssetHasher` calculates the current package build hash.
6. `LockRepository` skips packages with fresh locks unless the lock is ignored.
7. `BuildStepFactory` creates dependency and script steps.
8. `TaskRunner` executes steps sequentially or in a bounded process pool.
9. Successful package hashes are written to `.sympress_asset_compiler.lock`.

## Design Notes

- Public state objects are immutable readonly value objects.
- Composer-specific code is kept at the boundary.
- Shell commands are passed to Symfony Process as argument arrays.
- Package workspaces are sorted by name for deterministic output.
- Parallelism is bounded and configured by the root package.
- Dependency installation and script execution are separate build steps.
- Lock files are package-local so each package can be reasoned about independently.

## Symfony Components

The implementation uses Symfony components where they provide stable behavior:

- `symfony/filesystem` for package-local lock writes and filesystem preparation.
- `symfony/finder` for deterministic source hashing.
- `symfony/process` API for command execution and executable discovery.

The Composer package allows a wider `symfony/process` constraint because some WordPress toolchains provide the Process API through a Composer replacement package.
