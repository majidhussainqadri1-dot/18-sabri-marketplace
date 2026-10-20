# File 18 — Marketplace Module Status (20 October 2026)

- Runtime / target schema / contract: **2.1.0 / 2.1.0 / 1.2.0**.
- Corrective candidate: **2.1.0-RC4** (checksum requires final-head build).
- Source: draft PR #4 on draft PR #3's branch; main remains unchanged.
- Fresh inspection: 40 **scoped static** reviews; six defect-bearing scopes, one additional transaction-boundary hardening issue.
- Fixed in source: cursor pagination (two routes), verified application-password-only nonce exceptions (four mutation paths), external inbox transaction, explicit local event ACK, audit chain lock safety, transaction start/commit failure handling.
- Static regressions: `tests/test-october-40-scope-regressions.php`; included in CI and release script.
- Staging-Accepted, Live-Deployed, Operational: **NOT VERIFIED**.
- Actual deployed plugin, DB version and migration status: **UNKNOWN**.

Never use old RC3 checksum with RC4. The correction report and remaining blockers are documented in the repository root at `docs/OCT-20-2026-40-SCOPE-STATIC-REVIEW.md`.
