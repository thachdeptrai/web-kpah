# Loyalty Points / đổi quà

## Install (XAMPP + MariaDB / database kpah)

**Back up first.** Open Navicat or mysql, select the same kpah database configured in PHP and execute **only** `database/loyalty_points.sql` once. This SQL creates `kp_points_program`, `kp_points_wallet`, `kp_points_ledger`, `kp_points_rewards`, `kp_points_redemptions`. It does not change game tables or bank orders. Re-running `CREATE TABLE IF NOT EXISTS` will not reset wallets.

There is **no automatic retrospective award**: `kp_points_program.activated_at` records when you run the migration. Older payment orders are ignored even if their statuses change later.

## Rules

- Every **1,000 VND** of a provider-verified eligible top-up earns **1 Point**. **10,000 VND = 10 Points**. The website counts a provider-approved **card face value** and a completed **payOS bank amount**; cards whose game delivery is unclear and not verified are not blindly credited.
- Points belong to `team_user.id` (the website account), **not** `tob_char.tichluy`, `diemluong`, `topNap`, or any character's balance.
- `kp_points_ledger` has **UNIQUE (source_type, source_ref)**, so repeated webhooks and repeated page loads do not double-credit Points.
- Wallet changes and ledger insert run in a single **InnoDB transaction**.
- Only published admin-configured gifts can be redeemed; the request costs Points immediately and becomes `pending`. A repeated form with the same request token returns the existing redemption, not a second debit.
- Gifts are **manual delivery only** for now. Admin marks fulfilled **after actually delivering** a gift, or rejects and automatically refunds Points once. This code does not modify character bags or automatically issue game items.
- Never hand-edit wallet totals, paid order statuses, or duplicate ledger rows to fix a payment.

## URLs

- Player: `/diem-tich-luy` (balance, history, reward catalog and redemptions).
- Admin: `/quan-tri/diem-tich-luy` (publish/disable rewards, approve or reject redemptions).
- Existing payment history: `/lich-su-nap`.

## Suggested manual test cases on a non-production copy

1. New payOS payment 10,000 VND, successfully delivered: +10 Points, exactly one `payos` ledger row.
2. Poll the same completed payment repeatedly and replay the provider webhook: **no additional Points**.
3. New provider-approved 50,000 VND card: +50 Points, one `card` ledger row.
4. Pending/failed/unverified payments: +0.
5. Existing order dated before `activated_at`: +0.
6. Place a redemption with insufficient Points: no wallet or stock change.
7. Redeem an active reward with sufficient Points: one debit and one pending redemption, stock decremented if finite.
8. Re-send the identical redemption token: no duplicate debit.
9. Admin marks delivered: one final fulfillment, cannot repeat.
10. Admin rejects instead: one Point refund and one stock refund; cannot reject twice.
11. Concurrent redemptions/spam requests: wallet balance must never become negative.
12. Uninstalled migration: Point page shows a friendly notice; **payment confirmation remains operational**, Point write failures are logged.

The above are test cases to run; a live payOS/game-server test is **not** performed by the GitHub commit.
