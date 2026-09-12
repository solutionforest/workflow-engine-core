# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 2.x     | ✅ |
| 1.0.x   | ⚠️ Superseded by 2.0.0; upgrade recommended |
| < 1.0   | ❌ (alpha releases, no longer maintained) |

## Reporting a Vulnerability

Please **do not** open a public issue for security problems.

Report vulnerabilities privately through
[GitHub Security Advisories](https://github.com/solutionforest/workflow-engine-core/security/advisories/new),
or by email to **info@solutionforest.com**.

Include as much of the following as you can:

- A description of the issue and its impact
- Steps to reproduce, or a proof-of-concept workflow definition
- Affected version(s)

We aim to acknowledge reports within 5 working days and to ship a fix or
mitigation for confirmed issues in the next patch release.

## Scope Notes

A few behaviours are intentional and are **not** vulnerabilities:

- **Actions execute arbitrary PHP.** A workflow definition names a class that the
  engine instantiates and runs. Treat workflow definitions as trusted code, not as
  user input — never build a definition's `action` value from an untrusted source.
- **Conditions are parsed, not evaluated.** `ConditionEvaluator` uses a small
  hand-written parser and never calls `eval()`. A malformed condition throws
  rather than executing anything.
- **`HttpAction` follows redirects** (max 3 by default, HTTP/HTTPS only, TLS
  verification on). It does not filter private or link-local addresses, so do not
  point it at a URL supplied by an untrusted party without your own SSRF controls.
- **`DelayAction` blocks the current process.** A long delay in a web request will
  hold that worker; run long-delay workflows from a queue or CLI worker.
