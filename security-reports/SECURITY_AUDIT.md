# NGHIỆN KPAH Security Audit — 2026-10-15

## Scope and methodology
Source reviewed: `thachdeptrai/web-kpah` on main, SQL DDL from the separately uploaded `kpah.sql`, and 109 Java source files extracted **locally** from the separately uploaded `src.rar`. The Java archive is NOT part of the website repository and is not committed here. PHP 8.0 / Apache / XAMPP / MariaDB compatibility retained. Vibe Check upstream was reviewed at commit `3afad9906096a4b236b0427ce407f599d93f690d`. No production traffic, account changes or DB migration occurred.

Severity describes technical impact if the identified condition is reachable, not evidence of an actual exploit.

## Confirmed findings and changes

| ID | Severity | Code location | Evidence | Action |
|---|---|---|---|---|
| AUTH-01 | High | `app/pages/login.php` (login account lookup); `app/api/change-password.php`, `app/api/user-profile.php`, `app/api/forgot-password.php` | Login query did not check `team_user.ban`. The profile and password APIs required a session/CSRF but failed to recheck ban status on all relevant reads/writes. Protected account/dashboard pages already checked bans, so impact is **limited to affected routes and the login action**, not a verified bypass of all account protections. | Fixed on review branch: ban checks at DB read/write boundaries. |
| AUTH-02 | Medium | `app/api/change-password.php`, `app/api/forgot-password.php` | Password reset/change used read-verify-then-unconditional-UPDATE against MyISAM. A stale concurrent request could overwrite a newer password. | Fixed: compare-and-swap UPDATE on previous password hash, check affected rows. |
| DB-01 | High if executable by untrusted DB role | Uploaded `kpah.sql` stored routines `DeleteFromMatchingTables`, `DropMatchingTables` | The dump declares root-definer routines that issue dynamic cross-database DELETE/DROP statements. This is dangerous if imported and EXECUTE permission is granted outside trusted administration. Deployment/permissions are unknown. | No procedure executed or changed; restrict routine privileges, and remove from a separately reviewed sanitized staging import. |
| ARCH-01 | High (architectural risk) | `team_user`, `tob_char`, `board_naptien`; `app/includes/wallet_vnd.php`, `app/includes/bank_payos.php` | SQL DDL confirms MyISAM on game account / character / old recharge queue. Database transactions cannot roll back those writes. Existing app uses named locks, guarded updates, journaling and manual reconciliation to reduce risk, but game-server side concurrency is not proven safe. | Not changed: review migration and game integration separately. |
| AUTH-03 | Medium/High (legacy constraint) | `app/includes/account.php:24-26` | Game account passwords use the legacy MySQL-compatible SHA-1-derived format. Replacing it unilaterally would break game authentication. | Deferred until Java login server compatibility can be verified; require migration plan. |
| GAME-02 | Medium (admin tool, separate source) | Java archive `src/server/AdminPanel.java` around 1389 | The password reset form concatenated an operator-entered username into an SQL UPDATE instead of binding it. It is a SQL injection sink, but the tool's deployment and access control were not verified. | PreparedStatement patch created as separate local review artifact; **not applied to website repo or production**. |
| GAME-01 | Critical **if activated**; inactive in supplied constructor | Java archive `src/data/NapTien.java` | An optional TCP admin/payment listener has a fixed command gate; the constructor currently comments out its startup. A separate method also builds SQL by concatenating a character name. This cannot be classified as an active production exposure without confirming deployment and reachability. | No Java deployment or cross-project modifications. Isolate listener behind authenticated IPC and parameterized SQL before enabling it. |
| HEAD-01 | Low/operational | `app/includes/security.php`, Apache configuration | PHP sets headers including CSP/CSRF cookie settings; Apache must actually enforce `.htaccess` and secure cookies require correct HTTPS/proxy detection. | Local CI smoke tests only; confirm on real staging URL before merge. |

## Controls observed (not full penetration-test guarantees)
- Parameterized PDO operations and disabled emulated prepares in central database helper.
- Central admin role guard, optional TOTP, 30-minute session idle timeout, CSRF checks.
- payOS webhook HMAC validation and server-side reconciliation; card callback signature plus independent provider verification.
- Wallet named locks, idempotency journals, guarded MyISAM UPDATEs; Point ledger uses InnoDB transactions.
- Apache rules block web exposure of private folders, source dumps and backups when `mod_rewrite` and `AllowOverride` are correctly enabled.

## Unverified categories
No full dynamic database test or production scan has occurred for SQLi, XSS, CSRF, auth bypass across *all* endpoints, IDOR, brute force, SSRF, arbitrary file upload, RCE, webhook races, dependency CVEs, payment replay, and real-world business-logic attacks. This review covers selected critical data paths and static evidence; further validation on staging is mandatory. See REMAINING_RISKS.md.


## 2026-10-18 follow-up

The authoritative follow-up to this 2026-10-15 snapshot is
[**AUDIT_2026-10-18.md**](AUDIT_2026-10-18.md).
It records newer authentication controls, CI fixture regression repairs,
Semgrep execution evidence (focused rules: 0 findings in 96 files; PHP advisory:
130 *unvalidated candidates*), source tracing of five SQL candidates and five
filename candidates, and unchanged staging requirements.

This historical report does not supersede the newer code. In particular,
legacy MyISAM financial writes and Java game-side item/giftcode validation still
require authorized disposable staging with concurrency/crash simulation before
any claim of exact-once delivery.
