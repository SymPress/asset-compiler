# Contributing

Thank you for improving SymPress Asset Compiler.

## Development Setup

Install dependencies:

```bash
composer install
```

Run tests:

```bash
composer test
```

Run a syntax check before opening a change:

```bash
find src -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Coding Guidelines

- Keep Composer integration at the boundary.
- Prefer immutable value objects for configuration and result data.
- Pass process commands as argument arrays.
- Keep configuration parsing explicit and predictable.
- Keep changes small enough to review comfortably.
- Document user-facing behavior in English.

## Commit Messages

Use Conventional Commits:

```text
feat: add package discovery
fix: handle missing package json
docs: document compile command
test: cover lock repository
chore: update development tooling
```

## Pull Requests

Before requesting review:

- Run the relevant Composer command or test suite.
- Update documentation for user-facing changes.
- Keep unrelated changes out of the branch.
- Explain any migration impact.
