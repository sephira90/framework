# 2026-03-14 Xdebug Web Runtime Fix

## Context
- PhpStorm debugger validation for `framework.ru` reported:
  - debug client host unreachable;
  - no debug extension loaded;
  - PhpStorm unable to receive Xdebug connections.
- CLI checks and web checks diverged, so the issue had to be localized across the full chain:
  - IDE listener;
  - OpenServer host routing;
  - PHP runtime configuration source;
  - FastCGI worker state.

## Key Findings
- `framework.ru` resolved correctly and served the project over OpenServer.
- PhpStorm was already listening on port `9003`.
- Web requests initially ran under `PHP 8.4.16 (cgi-fcgi)` without `Xdebug`, which matched the PhpStorm validation failure.
- The first template edits were made in `C:\OSPanel\modules\PHP-8.4\ospanel_data\default\templates\php.ini`, but OpenServer was actually regenerating the live PHP config from:
  - `C:\OSPanel\config\PHP-8.4\default\templates\php.ini`
- This hidden control point caused the runtime config to revert on worker restart, which is why earlier changes looked correct in isolation but did not hold in the real web process.

## Decisions
- Patched the authoritative OpenServer template:
  - `C:\OSPanel\config\PHP-8.4\default\templates\php.ini`
- Patched the active runtime file:
  - `C:\OSPanel\modules\PHP-8.4\php.ini`
- Patched the OpenServer environment settings:
  - `C:\OSPanel\config\PHP-8.4\default\settings.ini`
- Enabled Xdebug with explicit settings:
  - `zend_extension = "C:/OSPanel/modules/PHP-8.4/ext/php_xdebug.dll"`
  - `xdebug.mode = "develop,debug"`
  - `xdebug.start_with_request = "trigger"`
  - `xdebug.client_host = "127.0.0.1"`
  - `xdebug.client_port = 9003`
  - `xdebug.idekey = "PHPSTORM"`
  - `XDEBUG_MODE = develop,debug`
- Restarted the PHP FastCGI workers after the config fix.

## Validation
- `C:\OSPanel\modules\PHP-8.4\php.exe -v`: passed, `Xdebug v3.5.0` loaded
- `C:\OSPanel\modules\PHP-8.4\php-cgi.exe -v`: passed, `Xdebug v3.5.0` loaded
- CLI runtime values confirmed:
  - `xdebug.mode => develop,debug`
  - `xdebug.start_with_request => trigger`
  - `xdebug.client_host => 127.0.0.1`
  - `xdebug.client_port => 9003`
  - `xdebug.idekey => PHPSTORM`
- Web runtime probe over `http://framework.ru/...` confirmed:
  - `sapi = cgi-fcgi`
  - `loaded_zend_extensions = [Zend OPcache, Xdebug]`
  - `xdebug_loaded = true`
  - `xdebug_mode = develop,debug`
  - `xdebug_start_with_request = trigger`
  - `xdebug_client_host = 127.0.0.1`
  - `xdebug_client_port = 9003`
  - `xdebug_idekey = PHPSTORM`

## What This Means
- The PhpStorm validation errors were not caused by the IDE itself.
- The root cause was configuration drift inside OpenServer: the actual template source for PHP 8.4 web runtime differed from the first edited template path.
- Once the authoritative template and runtime config were aligned, the FastCGI workers started exposing Xdebug correctly to web requests.

## Open Risks
- OpenServer may regenerate runtime configs again in the future if another control plane modifies `C:\OSPanel\config\PHP-8.4\default\templates\php.ini`.
- With `trigger` mode, debugging still requires an explicit trigger from browser, extension, cookie, GET param, or IDE integration.

## Next Actions
- Re-run PhpStorm “Validate Debugger Configuration on Web Server”.
- If PhpStorm still reports a client connection issue after this fix, the next place to inspect is Windows firewall or IDE-side path mapping, not PHP runtime loading.
