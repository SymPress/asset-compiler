# Security Policy

## Supported Versions

Security support follows the actively maintained release line of this package.

| Version | Supported |
| --- | --- |
| Unreleased / development | Yes |

## Reporting a Vulnerability

Please report suspected vulnerabilities privately to the package maintainer instead of opening a public issue first.

Include:

- A short description of the issue.
- Affected versions or commits when known.
- Reproduction steps or a proof of concept.
- Expected impact.

The maintainer will review the report, confirm the impact, and coordinate a fix and disclosure timeline.

## Security Expectations

This package runs package-manager commands configured by the Composer project. Treat root and package `composer.json` files as trusted project configuration. Package-local `asset-compiler.json` files are ignored unless the root project explicitly enables them.

The implementation avoids shell interpolation and passes command arguments to Symfony Process as arrays. Environment variables are explicitly provided by configuration and can be unset by using `false`. Remote precompiled assets use HTTPS, security failures fail hard, and production/no-dev remote archives require SHA-256 checksums by default.
