# RC47 — bounded stable cursor and actionable HTTP response
Date: 2026-11-15. Based on exact repository parent ccb08768a17f63a5d7dfe74dd45f640d0ade6c1a; V12.3 F18-FR-007 and F18-NFR-003/004/005.

Two real public search functions previously converted arbitrary pagination cursors using a lossy `(int)` cast: malformed text, negative numbers, array values, scientific notation, leading zeros or very large identifiers became 0/other values. This could silently repeat the first page rather than reject an invalid cursor. The primary public REST path also returned success status for `invalid_*` and `cursor_expired` errors.

Both canonical search owners now accept only an empty cursor, zero, or 1–18 decimal digits without leading zeros. Anything else returns `invalid_cursor` without running SQL. The enhanced REST path maps invalid parameters to HTTP 400, expired/missing anchor to HTTP 410, and existing backend outages to HTTP 503.

Actual class regression covers malformed/valid cursor sets and REST status mapping. Real WordPress staging/browser pagination, concurrency and File26 index reconciliation remain separate release gates.