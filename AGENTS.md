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
- Do not rename routes, change pricing/currency ratios, or alter the game schema.
  For security-only work, prefer review branches unless the repository owner explicitly
  authorizes commits directly to `main`; after each direct commit verify GitHub CI.
- No test with real player balances or live payment credentials. Mark untested risks explicitly.

Review [security-reports/SECURITY_AUDIT.md](security-reports/SECURITY_AUDIT.md)
and [security-reports/REMAINING_RISKS.md](security-reports/REMAINING_RISKS.md) before merge.

## Security reviewer pre-merge / pre-deploy checklist

- [ ] Confirm every changed private endpoint checks session authorization **server-side**, even when called directly, and verifies player/character ownership.
- [ ] Ensure all state-changing browser actions enforce CSRF and role permissions; callback endpoints instead require authenticated provider evidence.
- [ ] For dynamic SQL, bind all values and allowlist column names, sort keys, and fragments. Never interpret a clean scanner report as a data-flow proof.
- [ ] Confirm HTML/attribute/URL/JS contexts are escaped appropriately; reject URL authority backslashes and unsafe schemes.
- [ ] Check login/recovery limits independently by source IP and target identity, with fail-closed storage.
- [ ] For topup/VNĐ/Point/giftcode/item operations, verify amount & target server-side, journal before MyISAM mutations, reject duplicate tokens and ambiguous retries.
- [ ] Never automatically repay or re-grant on an uncertain state; require authoritative reconciliation.
- [ ] Run PHP lint, scoped regression tests, Semgrep focused gate, review advisory findings and compare diffs for secrets.
- [ ] Before calling a business flow safe, exercise a **disposable** staging database/game/provider simulation with concurrency and failure injection.
- [ ] Do not run ZAP, Strix, Nuclei or exploit probes against production. Check proxy TLS, Apache AllowOverride and private storage denial with authorized deployment access.
