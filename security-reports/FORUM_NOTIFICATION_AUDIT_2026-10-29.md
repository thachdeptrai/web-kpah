# KPAH — Forum, notifications, auth security and CI audit (2026-10-29)

## Scope
Static review of the public/admin Forum endpoints, admin and user notification handlers, username masking, point-schema routing, login session initialization, CSRF/rate limits, and failing GitHub Actions. This is **not** a live penetration test against production VPS or a guarantee against complete backend/database compromise.

## Confirmed failures and fixes

| Severity | Finding | Cause / evidence | Remediation |
| --- | --- | --- | --- |
| High | PHP session cookies/CSRF could fail to initialize | `config/register.php` began with UTF-8 BOM. Routing CI reported `headers already sent` and session warnings; test `router_smoke.php` could not match cookie name. | Removed BOM; added regression asserting configuration has no BOM. |
| Medium | Raw SQLSTATE could be shown on Forum errors | Both public/admin Forum pages used `$e instanceof RuntimeException ? $e->getMessage() : ...`; `PDOException` extends `RuntimeException`. SQL table names and driver errors could reach users. | `kp_forum_safe_error()` returns generic errors for PDO and all non-exact `RuntimeException` classes; new offline regression test. |
| Medium | Hidden/PII reports could be published through a direct service call | Sensitive-content check originally only in `app/admin/forum.php`, not in `kp_forum_admin_update()`. | Second fail-closed check on the locked database row before changing visibility, plus integration regression. |
| Medium | Notification SQL test failed independently | `app/includes/user_center.php` called `kp_points_schema_name()` without loading `points_storage.php`. | Added explicit dependency and separate-schema smoke tests. |
| Low | Old unread-toast marker persisted on login | New portal deduplicates by count + newest ID but login still reset only the retired count variable. | Reset both notification marker variables after login. |
| CI blocking | Giftcode Java patch absent | `tests/giftcode_builder_smoke.php` expected `game-server-patch/giftcode-item-options.patch`, which was deleted in Git history. | Restored exact patch from earlier repo commit `eceb75faf1`, preserving real contract assertions. |
| CI blocking | Homepage navigation test stale | `tests/home_layout_visual.py` expected five links; Forum made six. | Updated expected route list; layout checks remain active. |

## Protections reviewed
- Public Forum list only selects masked `public_alias`, not `user_id` or `team_user.username`. Admin list gets real account names only behind `kp_admin_require(...,['admin'])`.
- Forum operations use prepared statements, server-side session account identity, CSRF tokens, per-user/IP rate limiting, strict status enum checks, and one-time submission/reply tokens.
- Admin responses and ticket status changes write notifications in the same InnoDB transaction as the originating Forum action. Notifications reside in `kpah_points`; game identities stay in `kpah`.
- User notification queries are scoped by `user_id`. Notification link paths are strict allowlists, excluding external URLs and querystring redirections.
- Forum content and server-generated notification messages are rendered using `kp_e()` (HTML escaping); browser toast uses text-safe APIs.
- Reports flagged for possible emails/phone/credentials/security exploits are held privately. The service-level guard also refuses publication of such reports.
- Admin session guards, TOTP support, CSRF/origin checks, and rate limiting are covered by dedicated tests.

## Residual risk and release checks
1. **A compromised PHP/backend with database privileges can still query `kpah.team_user`**. Public name masking prevents disclosure through ordinary Forum output, not through SQL-level compromise. Harden PHP/runtime, keep secrets out of repository, restrict DB grants, enable administrator MFA and maintain backups.
2. PII keyword filters are heuristic and cannot identify every username, payment artifact, or vulnerability detail voluntarily typed into a report. Administrators must review submissions before republishing hidden reports.
3. Java Giftcode parser patch is restored for repository test integrity; presence in Git does **not** prove it is applied to the deployed game server. Verify server Java build separately before using DBO giftcode options.
4. Confirm browser route tests, MariaDB integration, notification ownership/privacy, and mobile visual geometry pass. Running tests is not equivalent to live VPS verification. Do not change `kpah_points` data or silently backfill lost notifications.
