# Security policy — NGHIỆN KPAH

## Supported application
The PHP 8 / Apache 2.4 production deployment at nghienkpah.io.vn, maintained from the `main` branch. This document is a disclosure and deployment policy, not a certificate of safety.

## Responsible reporting
If you believe you found a vulnerability, **do not** submit live credentials, personal data, payment information or working exploit details in a public issue. Contact the site administrator through the existing private support channel. Include the affected route, expected vs observed behavior, impact, and a minimal **non-destructive** reproduction against an authorized staging environment.

No unauthorized scanning, denial-of-service tests, credential stuffing, or payment experiments against production are permitted.

## Protected assets and invariants
- PHP credentials, local configuration, reset tokens, card PINs and transaction journals must not be committed.
- Every private read/write needs server-side authorization, explicit ownership checks and CSRF for state-changing browser requests.
- Webhook payments require provider signatures and independently verified payment status/amount; never trust browser-facing `paid` values.
- Game database tables including legacy MyISAM must not be assumed to support rollback.
- Never auto-replay an ambiguous credit/grant: queue for reconciliation.
- Private directories must be denied by Apache `.htaccess`; confirm AllowOverride and mod_rewrite are enabled on each deployed VPS.
- Logout/login sessions and rate-limit storage must be tested in the real staging proxy topology.

## Required deploy checks
1. Backup database and protected storage outside web root.
2. Review a proposed diff for credentials and money/item/role changes.
3. Run PHP syntax checks and `tests/auth_security_smoke.php`, `tests/security_auth_url_smoke.php`, `tests/security_smoke.php`, `tests/topup_smoke.php`.
4. Review GitHub Actions and staging behavior before pulling new `main`.
5. Verify public URLs under `/app/`, `/config/`, `/storage/`, `/database/`, `/tests/` cannot serve source/data.
6. Check HTTPS, cookie flags, CSP, CORS, and that all admin endpoints are inaccessible to ordinary users.
7. Use staging transactions with dedicated test credentials to assess callback replay, concurrent checkout, and partial failure; never create test payments on production.

## Proxy configuration warning
`security.trusted_ip_header` and `security.trust_forwarded_proto` must be configured **only** when the origin is restricted to trusted reverse proxies which overwrite the relevant headers. Otherwise an attacker reaching the origin may spoof source IP/HTTPS and bypass IP-based abuse controls. Configure proxy firewall/routing first; changing these settings without checking topology can lock out legitimate users.

For deeper reports see [security-reports/SECURITY_AUDIT.md](security-reports/SECURITY_AUDIT.md) and [security-reports/AUDIT_2026-10-18.md](security-reports/AUDIT_2026-10-18.md).
