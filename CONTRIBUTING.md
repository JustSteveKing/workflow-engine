# Contributing

Thanks for considering a contribution to `juststeveking/workflow-engine`.

## Getting started

```bash
git clone https://github.com/juststeveking/workflow-engine
cd workflow-engine
composer install
```

The package is exercised through [Orchestra Testbench](https://github.com/orchestral/testbench),
so no full Laravel application is needed. It targets **PHP 8.5** and **Laravel 13**.

## Before you open a pull request

Run the full check suite — all three must pass:

```bash
composer test    # Pest (Unit + Feature)
composer stan    # PHPStan / Larastan
composer lint    # Pint (code style)
```

CI runs the same three on every pull request.

## Guidelines

- Add or update tests for any behaviour change. Feature tests live in `tests/Feature`, pure value-object tests in `tests/Unit`, and step/definition test doubles in `tests/Fixtures`.
- Keep the architectural invariants intact — they are documented in [AGENTS.md](AGENTS.md). In particular: every mutating engine path runs under a row lock, side effects are dispatched after commit, and every status change goes through the state machine.
- Match the existing style; let Pint format (`composer lint`).
- Update the [CHANGELOG](CHANGELOG.md) under `Unreleased`.
- Keep pull requests focused. One concern per PR is easier to review and revert.

## Reporting bugs and requesting features

Use the GitHub issue templates. For security issues, do **not** open a public issue — see [SECURITY.md](SECURITY.md).
