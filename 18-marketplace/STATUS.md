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

## Second corrective pass — 20 October 2026

Six newly found source defects repaired: deal INSERT ID lifetime, migration failure accounting, DB schema verification, File 17 same-origin scheme/port, browser Back full-origin validation, and File 19's actual canonical notification producer contract. An isolated File 19 PHP contract regression was added and a CI step configured for 40 repeated aggregate QA passes on each supported PHP matrix member. All production/staging evidence remains pending; see root `docs/OCT-20-2026-SECOND-40-SCOPE-CROSS-FILE-AUDIT.md`.

## Third fresh 40-scope review / RC5 (21 October 2026)

Draft PR #5 adds five targeted corrective source-domain fixes and direct PHP failure simulation for safety report/Outbox/Audit and audit transaction lock lifetime, source assertions for Saved Listings, REST atomic owner transitions, and updated RC5 reproducible build. Repeat 40 full static/regression runs for PHP 8.1 and 8.3 are configured; final exact HEAD CI must confirm count. This is **not Staging-Accepted or Live-Deployed**. Live exact installed files and DB/schema migrations unverified; WPVibe has no registered site and historic Cloudflare 530 has undiagnosed origin.

## Fourth corrective batch (21 October 2026) — Source only / 2.1.0-RC6

Required File19 recipient acknowledgement now gates Marketplace outbox completion, rather than being ignored when another event consumer confirms. Listing normalization no longer makes a negative or missing/non-numeric price silently free; listing/offer/counter values must be finite and strictly numeric before conversion. Fault-injection and domain-policy tests in the full static QA are configured for 40 repeated rounds on each PHP 8.1/8.3 CI matrix member; confirm exact HEAD logs before claiming success. All 40 scoped implementation anchors do not prove real WordPress/MySQL/companion acceptance. See the root `docs/OCT-21-2026-FOURTH-40-SCOPE-RC6-REVIEW.md`. Deployment, database, migration and live errors remain unverified; WPVibe Cloudflare HTTP 530 is unresolved and its origin unknown.

## Fifth forty-scope review / RC7 (21 October 2026)

Draft PR #7 now implements canonical File17 REST owner conversation + listing context authorization and refreshed current price projection, detects File19 SUN availability, and rejects false-positive durable idempotency receipt writes. Two additional PHP negative integration simulations are in the full aggregate QA and the 40-round per-PHP workflow. Source anchors are present for 40 scopes, not proof of complete central requirement acceptance. See root `docs/OCT-21-2026-FIFTH-40-SCOPE-RC7-REVIEW.md`. Do not reuse RC6 artifact or mark the live site resolved. Deployed plugin checksum, DB/schema, migration and live acceptance unverified.

## Seventh plan-to-code review (21 Oct 2026)

Four source-proven defects corrected in the RC9 Draft PR #9 (recall/audit atomicity; legal hold/audit atomicity; seller eligibility write/readback fail-closed; duplicate hold concurrent creation via canonical named lock). Two direct actual-class fault-injection tests + one forty-domain source-presence check run in aggregate QA, configured for 40 repeats on each PHP 8.1/8.3 CI job. Source presence and stub simulations do not establish functional completeness, real MySQL races, staging acceptance or deployment. Full report: `docs/OCT-21-2026-SEVENTH-40-SCOPE-RC9-AUDIT.md`. No main merge, live package parity, backup restore, migration proof, Founder approval or Live Re-Test.
