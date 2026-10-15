# Security Fixes — review branch

## Applied
1. `app/pages/login.php`: server-side active-account filter (`ban = 0`) in login query.
2. `app/api/user-profile.php`: active-account checks for profile read and write, including email/phone changes.
3. `app/api/change-password.php`: active-account read, conditional password UPDATE matching old password hash, rejected stale writes and session ID rotation on success.
4. `app/api/forgot-password.php`: banned users cannot start recovery; final reset uses old-hash compare-and-swap and ban condition.
5. `tests/auth_security_smoke.php`: offline regression checks (query invariants, CSRF, legacy hash compatibility).
6. `AGENTS.md`: Vibe Check security guidance customized to PHP, MariaDB and the game server.
7. `.github/workflows/vibe-security-audit.yml`: checkout Vibe Check at pinned commit, run non-destructive local headers/CORS scanner on isolated PHP fixture, lint and smoke tests, upload report artifact.
8. `tests/vibe_fixture/index.php`: database-free local test target for the scanner.
9. `security-reports/*.md`: findings, tests and outstanding risks.

## Intentionally unchanged
- `index.php`, `.htaccess`, URL routing, layouts, CSS, JS.
- Recharge denominations, conversion ratios, loyalty Point rates, user balances, DB schema and all production settings.
- MyISAM table engines, giftcode reward logic and Java game server.
- payOS/card secrets and webhook endpoints.

## Review/rollback
Review branch should be merged through PR only after staging verification. The code-only auth changes can be reverted by reverting their commits; no DB rollback/migration is required. A functional regression that would affect accounts requires reviewer approval before production rollout.
