# Security Tests — evidence and limitations

## Tests implemented
| Check | Command / mechanism | Scope | Status |
|---|---|---|---|
| Syntax across PHP files | `find . -name '*.php' ... php -l` | Parse errors, PHP 8.0 compatibility | PASS in GitHub Actions run 38755466198 |
| Account ban + password CAS guards | `php tests/auth_security_smoke.php` | Source-level invariants; live CSRF and legacy hash helper calls | PASS in GitHub Actions run 38755466198 |
| Existing admin auth/CSRF | `php tests/admin_security_smoke.php`, `php tests/admin_csrf_smoke.php` | Regression checks provided by project | PASS in GitHub Actions run 38755466198 |
| Existing topup checks | `php tests/topup_smoke.php` | Existing offline topup invariants | PASS in GitHub Actions run 38755466198 |
| Vibe Check pinned local subset | `check.py http://127.0.0.1:8765/ --local --only headers,cors --json` | Local fixture only; no DB, checkout, wallet or production endpoint | CI configured; JSON artifact generated at runtime |
| Sensitive-file tracking | Git tracked-name denylist | Guard newly committed secret/dump-like filenames | CI configured |

The local fixture scanner is **not** a scan of the live KPAH site and does not validate Apache `.htaccess`. Running the full Vibe Check secrets/headers/CORS scan requires an authorized Apache staging URL with realistic reverse proxy/TLS settings. Only GET/HEAD/OPTIONS read-only scanning is authorized.

## Staging tests still required (NOT PASS)
- Test standard, banned and freshly banned user sessions across registration, login, profile, password and payment routes. Expect blocked account operations.
- Send concurrent password changes/reset requests against disposable MySQL/MariaDB users. Expect a single successful compare-and-swap; subsequent stale updates conflict.
- Two test users: attempt profile, character, order and Points access to the other user's resource IDs.
- Run verified and duplicated fake payOS/card callbacks against provider sandbox and disposable DB. Compare order states, journals and balances exactly once.
- Run two simultaneous VND exchanges from an isolated test account, confirm no double-debit/grant; use fake game characters and isolated game DB.
- Test SQLi/XSS/CSRF, invalid data, throttling and privilege boundary with disposable credentials.
- Confirm Apache is enforcing private-file denials, HTTPS cookies and real security headers. Use staging, not the production site.
- Run Java game-server payment and admin-channel test only after integration build and authentication hardening.

## Reporting rules
Do not count scanner 'PASS' on a mock fixture as application end-to-end PASS. Do not claim zero vulnerabilities from this limited audit. Record GitHub Action run URL/status and staging logs before merge.


## Executed CI evidence (2026-10-15)
- **PASS** — [Vibe Check + account security audit, run 38755466198](https://github.com/thachdeptrai/web-kpah/actions/runs/38755466198): PHP 8.0 lint; account guard and CSRF/hash smoke; existing admin and topup security tests; private-artifact guard; pinned Vibe Check local fixture scan; JSON artifact uploaded.
- **PASS** — [Security regression checks, run 38755466586](https://github.com/thachdeptrai/web-kpah/actions/runs/38755466586).
- **PASS** — [PHP and JS syntax checks, run 38755466575](https://github.com/thachdeptrai/web-kpah/actions/runs/38755466575).
- **NOT EXECUTED** — genuine database-backed webhook replay, multi-request concurrency, cross-account authorization, Java game-server integration and public Apache staging scans.

These results validate the listed **offline**/local checks only; they do **not** certify the production environment.
