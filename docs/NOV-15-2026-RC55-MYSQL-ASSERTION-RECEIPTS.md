# RC55 — exact real WordPress/MySQL integration assertion receipts
Date: 2026-11-15. Source parent 981e3a7f987cfa02bdad28a52a219834a7b579de.

GitHub Actions real WordPress smoke emitted many individual PASS WP/MYSQL rows but incorrectly summarized `0 assertions passed`. Root cause: the WP-CLI `eval-file` scope retained a local `$checks` initialized to zero while the global assertion helper incremented a separate global variable. A green test with a false zero-count is misleading QA evidence.

The test now explicitly initializes and increments one named `$GLOBALS['file18_mysql_integration_assertions']` counter and fail-closes if fewer than 35 independent test assertions execute; the final success line reports this exact counter. This is a QA reporting correction, **not** a live WordPress bug. No change to production Marketplace source or schema.

Run both exact-head GitHub workflows; the old RC54 ZIP filename remains accurate for this source's production-code bytes, although test evidence changed. Real Hostinger staging and deployed parity remain unverified.