# 2026-03-14 Xdebug Setup

## Context
- User requested Xdebug installation for the local OpenServer PHP 8.4 environment.
- The practical target was not package download, but a usable debugger configuration for local development.

## Key Findings
- Xdebug was already installed and loaded in `C:\OSPanel\modules\PHP-8.4\php.ini`.
- The active configuration exposed only `develop` mode, which improves diagnostics but does not enable step debugging in the IDE.
- Global `composer` was not available in `PATH`, so project QA had to be executed through the OpenServer-managed Composer phar.

## Decisions
- Kept the existing Xdebug installation.
- Enabled step debugging explicitly by switching `xdebug.mode` from `develop` to `develop,debug`.
- Set `xdebug.start_with_request=trigger` to avoid attaching the debugger to every request by default.
- Made debugger connection settings explicit for local IDE usage:
  - `xdebug.client_host=127.0.0.1`
  - `xdebug.client_port=9003`
  - `xdebug.idekey=PHPSTORM`

## Changed Files
- `C:\OSPanel\modules\PHP-8.4\php.ini`
- `artifacts/execution/2026-03-14-xdebug-setup.md`

## Validation
- `C:\OSPanel\modules\PHP-8.4\php.exe -v`: passed, Xdebug `3.5.0` loaded
- `C:\OSPanel\modules\PHP-8.4\php.exe -i | Select-String ...`: passed
  - `xdebug.mode => develop,debug`
  - `xdebug.start_with_request => trigger`
  - `xdebug.client_host => 127.0.0.1`
  - `xdebug.client_port => 9003`
  - `xdebug.idekey => PHPSTORM`
- `C:\OSPanel\modules\PHP-8.4\php.exe C:\OSPanel\data\PHP-8.4\default\composer\composer.phar qa`: passed

## Open Risks
- Web requests may still run with the previous PHP process state until OpenServer or the PHP web runtime is restarted.
- With `trigger` mode, IDE debugging starts only when a trigger is sent (`XDEBUG_SESSION`, browser extension, or equivalent client support).

## Next Actions
- Restart OpenServer or its PHP web runtime to apply the new `php.ini` to browser requests.
- In the IDE, listen on port `9003` and use `PHPSTORM` as the IDE key if required by the client setup.
