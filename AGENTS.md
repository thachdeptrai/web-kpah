# NGHIỆN KPAH — Security rules for coding agents

Adapted to this PHP 8.0 / Apache XAMPP / MariaDB / Java game ecosystem from
[Benav Labs Vibe Check](https://github.com/benavlabs/vibe-check),
reviewed against upstream commit `3afad9906096a4b236b0427ce407f599d93f690d`.
These are code-review guardrails, **not** proof that an audit has passed.

- Never commit real passwords, DB dumps, API keys, .env files, card codes, or customer information.
- Protect every authenticated endpoint server-side; scope reads AND writes to the session user.
  Admin-only routes must call the central admin authorization guard.
- Check `team_user.ban = 0` for protected account operations, not merely when rendering a page.
- Enforce session-based CSRF checks on state-changing forms. Regenerate session IDs on login.
- Use PDO prepared statements for values; whitelist SQL identifiers explicitly.
- Escape output with `kp_e()`. Never trust client-provided amounts, account IDs or paid statuses.
- For topups and card/bank webhooks, verify the server-to-provider payment status and amount.
  Enforce idempotency and do not retry ambiguous MyISAM writes automatically.
- `team_user`, `tob_char`, and `board_naptien` are MyISAM in the supplied SQL.
  MyISAM writes are NOT transactional; never assume a PDO rollback reverts these writes.
  Changing their engine requires a separately reviewed staging migration.
- Use server-side storage and rate limiting, fail closed on security state errors.
- Keep Apache .htaccess denying storage, secrets, dumps and source directories. PHP's
  development server does not apply Apache .htaccess directives.
- Review third-party scripts before running them. Vibe Check scanner may run only against
  an authorized test site; CI uses a local header/CORS fixture (not production).
- Do not rename routes, change pricing/currency ratios, alter the game schema, or push
  directly to main during a security-only change.
- No test with real player balances or live payment credentials. Mark untested risks explicitly.

Review [security-reports/SECURITY_AUDIT.md](security-reports/SECURITY_AUDIT.md)
and [security-reports/REMAINING_RISKS.md](security-reports/REMAINING_RISKS.md) before merge.
