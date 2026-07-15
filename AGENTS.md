# SymPress Asset Compiler

## Scope and entry points

- Read `docs/architecture.md` first; configuration and command contracts live in `docs/configuration.md` and `docs/commands.md`.
- Composer integration stays in `src/Composer`; orchestration stays in `src/Application`.
- Package-manager selection and command construction live in `src/PackageManager`.
- Remote archive trust boundaries live in `src/Precompiled`.

## Verification

- Fast behavior check: `composer tests`.
- Full required check: `composer qa`.
- For package-manager changes, run the real-toolchain test with `vendor/bin/phpunit --filter PackageManagerToolchainTest` on a machine with the affected tool installed.

## Invariants

- Pass process commands as argument arrays; never interpolate package configuration into a shell command.
- Keep Composer-specific APIs at the boundary and state/configuration objects immutable.
- Root and explicitly enabled package configuration are trusted; remote archives still require HTTPS, checksum, size, and path validation.
- A failed download or extraction must not destroy existing compiled assets.
- Do not silently change lock-file precedence, package-manager selection, or dependency modes.

## Cross-repository impact and done

- Consumer packages opt in through Composer/package metadata documented in `docs/configuration.md`.
- A change is done when focused configuration/security/toolchain tests and `composer qa` pass and command documentation remains exact.
