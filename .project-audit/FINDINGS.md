# Findings — 2026-10-27

- ARCH-01 | HIGH architectural risk, documented/historical, current integration NOT TESTED. security-reports/SECURITY_AUDIT.md:15 documents MyISAM tables team_user, tob_char, board_naptien and possible non-atomic financial writes. Check actual schema, wallet_vnd.php/bank_payos.php and game-server race behavior on disposable staging before changing anything.
- AUTH-LEGACY | MEDIUM/HIGH legacy constraint, historical, current implementation NOT VERIFIED. security-reports/REMAINING_RISKS.md:5 describes game password SHA-1-derived compatibility. Coordinate Java login migration before replacing hashing.
- PAY-STAGING | HIGH validation gap, documented/historical. security-reports/REMAINING_RISKS.md:3 explicitly lists missing provider/database/game-server end-to-end checks. Current source and staging not exercised.
- JAVA-BOUNDARY | HIGH conditional, external scope. security-reports/REMAINING_RISKS.md:7-8 documents inactive unsafe Java listener and separate admin SQL sink. Java archive unavailable in this repository; not confirmed active.
- PROXY-HEADERS | MEDIUM operational hypothesis. security-reports/REMAINING_RISKS.md:9-10 documents trusted proxy assumptions and Apache static headers. Verify deployment configuration separately.

No new exploit or confirmed current security defect established by this checkpoint.
