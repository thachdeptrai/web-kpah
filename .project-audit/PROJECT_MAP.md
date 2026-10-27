# Project map — 2026-10-27

Physical root: C:/xampp/htdocs. Git HEAD: 93cf1826fdb7c08f72c75437074edb94ee5a89a6. Dirty before audit: modified config/register.php; deleted scripts/import-approved-auth-webp.ps1. Audit did not alter these.

## Architecture confirmed from read source
- index.php requires app/router.php.
- app/router.php resolves canonical/alias routes from config/routes.php; rejects ambiguous path bytes, then requires the registered PHP handler.
- app/includes/bootstrap.php loads config, security, DB, captcha, validator, account, mailer, referrals, then session.
- app/includes/db.php initializes MariaDB/MySQL PDO with exceptions, no emulated prepares, and disabled multistatements if driver supports it.
- PHP modules: app/pages, app/api, app/admin, app/topup and app/includes. Assets under assets and res. SQL/schema under database.
- Existing test scripts under tests; production runtime uses Apache/XAMPP PHP with MariaDB. Game-server integration is external; game-server-patch contains supporting local materials.

## Verification limitations
Routing entry point and bootstrap inspected. Deep endpoint call chains, third-party assets, symlink roots, current runtime and provider callbacks remain pending. Existing security reports are historical and cannot prove current behavior.

Additional evidence: config/routes.php:1-106 contains fixed route handler map and legacy aliases; docs/ROUTING.md aligns on Apache mod_rewrite, scoped session and private-path denial; .htaccess:16-27 blocks private paths (deployment behavior NOT TESTED).

Refresh: application needs storage/game-downloads binaries and catalog.json, assets/js-dist is served by Apache rewrite, res/icon ids derive from DB; storage/ratelimit and topup remain runtime-bound, not safe deletion candidates.
