# 2026-03-14 Quality Toolchain Baseline

## Context
- Built the minimum project quality toolchain for `lint`, `phpcs`, `phpstan` level `10`, and `psalm`.
- Goal: turn the mandatory validation rule into an executable workflow.

## Key Findings
- Global `PATH` does not contain `php`, so vendor binaries are not reliable when called directly.
- `Composer` needs an explicit writable `sys_temp_dir` in this environment.
- An empty project tree must be handled explicitly, otherwise some tools treat the absence of PHP files as a failure.
- `PHP_CodeSniffer 4.0.1` was unstable in this Windows environment; `3.13.5` is the chosen stable baseline.

## Decisions
- Added `composer.json`, `composer.lock`, `phpcs.xml.dist`, `phpstan.neon.dist`, `psalm.xml`, and `.gitignore`.
- Added `qa.php` and `QaRunner.php` as a reproducible CLI validation wrapper.
- All tools are executed through the current `PHP_BINARY` with `sys_temp_dir=var/tmp`.
- `qa.php` and `QaRunner.php` are included in static analysis so the QA wrapper validates itself.

## What This Changes
- The project now has a working automatic validation baseline.
- `composer qa` passes in the current environment.
- Framework core development can start on top of a working strict QA loop instead of before it.

## Changed Files
- `.gitignore`
- `composer.json`
- `composer.lock`
- `phpcs.xml.dist`
- `phpstan.neon.dist`
- `psalm.xml`
- `qa.php`
- `QaRunner.php`
- `artifacts/execution/2026-03-14-quality-toolchain-baseline.md`

## Validation
- `php -l qa.php`: passed
- `php -l QaRunner.php`: passed
- `composer validate --strict`: passed
- `composer qa`: passed
- `parallel-lint`: passed
- `phpcs`: passed
- `phpstan` level `10`: passed
- `psalm`: passed

## Open Risks
- `src/`, `tests/`, and `tools/` still do not contain framework PHP code; the green QA status currently validates the tooling wrapper itself.
- Local `AGENTS.md` and `.osp` remain environment-specific files outside the upstream baseline.

## Next Actions
- Fix `v0` framework boundaries.
- Add the first minimal core module that passes the assembled QA loop from the first commit.
