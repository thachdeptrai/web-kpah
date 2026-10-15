# Remaining risks / merge gate

1. **Staging validation missing:** No live disposable MariaDB + Java game server, valid test payOS/card provider, or configured Apache staging deployment was available here. End-to-end balance, webhook, replay, concurrent writes and authorization testing remains undone.
2. **MyISAM atomicity:** `team_user`, `tob_char`, `board_naptien` remain MyISAM according to the uploaded SQL. Current lock/journal/CAS strategy depends on game-server behavior, storage durability and MySQL connectivity. Never automatically repeat ambiguous credits.
3. **Old password hash:** `kp_game_password_hash` uses legacy SHA-1-derived MySQL format due game client/server compatibility. A separate coordinated game/web migration to Argon2id/bcrypt is recommended.
4. **Java admin payment listener:** The separately uploaded Java source includes an unsafe optional listener (constructor shows it disabled). Confirm it stays disabled, and never expose its port externally. Replace fixed-message authentication with strong authenticated IPC and bound interfaces before enabling it. Java archive wasn't present in website repository, and no server binaries were modified.
5. **Java admin SQL sink:** The separately uploaded `src/server/AdminPanel.java` has an SQL string concatenation path in the operator's password reset feature. An isolated PreparedStatement patch was produced locally and dry-run validated, but cannot be merged into the website repository or deployed to the Java game without separate review and build tests.
6. **Proxy headers:** `security.trusted_ip_header` / `trust_forwarded_proto` must be enabled only if the trusted proxy sets and sanitizes headers and origin access is restricted; defaults are off.
6. **Auth session lifecycle:** Password changes rotate the calling browser's session ID, but do not globally revoke all other logged-in PHP sessions or game-server sessions. Design server-side session versioning only after cross-client compatibility review.
7. **Static assets and server config:** Apache/CDN may serve assets without PHP headers; verify static-file headers and rewrite protections at staging.
8. **Dependencies and credentials:** Historical Git secrets scanning, PHP/Java dependency CVE inventory and deployed configuration/secret rotation were not completed. Do not publish real SQL dumps, game server configuration, or credentials.
9. **Scope:** Other endpoints and 109 Java source files received targeted review, not exhaustive data-flow proof or comprehensive dynamic testing. No conclusion of full security is implied.

**Merge recommendation:** Keep PR in draft until disposable staging tests pass and the deployed Apache/proxy settings are verified. Do not auto-deploy these changes.
