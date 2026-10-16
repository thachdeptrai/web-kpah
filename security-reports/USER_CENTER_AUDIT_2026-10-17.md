# Audit web_KPAH — 2026-10-17

Scope: current **web-kpah** PHP code on `main`, with focused examination of player-facing giftcode history, notification center, payment order tracker, security history, Toast, access controls, routing and GitHub Actions. This is **not** a penetration test of production or an audit of the Java game runtime.

## Confirmed and fixed
- User notifications failure: `toast.js` consumes elements carrying `data-toast-*` and removes them. The old implementation placed those attributes on the **visible** error alert, so the alert disappeared. Now a separate hidden notification emits the Toast while the inline explanation stays visible.
- Browser headers in audit history: untrusted `User-Agent` was copied into the database with only raw-byte truncation. Control characters, malformed UTF-8 and partial multibyte chars could cause inserts to fail. Normalize before the bounded store.
- Payment cards: `success` should not be taken as a definitive order-delivery completion without a matching finalized status. Distinguish verified card from actually delivered `completed/wallet_credited`.
- Added static smoke guards for ownership, failure feedback, wallet/card state presentation and Toast escaping.

## Security controls observed
- Login-required pages use `kp_player_account()` and the verified `team_user.id` session reference.
- Giftcode history checks ownership through `tob_web_info_char.userid`; notification/security history filter by the logged-in user ID.
- Mark-all-read action uses POST with CSRF token and an owner-filtered UPDATE query.
- SQL access uses prepared statements for user data; escaping uses `kp_e`, and Toast text is assigned via `textContent`.
- `res` directory is forbidden except numeric PNG icons, and application source paths are denied by root Apache rules.
- Audit events do not log account credentials, PINs, session IDs or full IP addresses.

## Outstanding limitations and follow-up work
1. CI smoke tests are mostly isolated/static and do **not** establish authorization and payout safety against a live production database or payment provider. Perform staging integration tests with a sanitized SQL snapshot and separate payment test account.
2. Security event history is best-effort; records from before migration do not exist. Notification producers are not fully integrated with every successful payment event.
3. The card order tracker reads its existing JSON order store and bank tracking reads `kp_bank_orders`, both scoped to the user, but it does not force provider reconciliation. Users should use full payment history/support for stale or disputed orders.
4. Java game server giftcode logic and payment grant reconciliation need separate end-to-end concurrency tests; do not assume website CI certifies game server mutations.
5. Verify the deployment has applied `database/user_center.sql`, and that runtime `storage/` and secrets remain web-inaccessible, writable only to required identities.

Test commands:
```bat
C:\xampp\php\php.exe tests\user_center_smoke.php
C:\xampp\php\php.exe tests\auth_security_smoke.php
C:\xampp\php\php.exe tests\character_inventory_smoke.php
```
