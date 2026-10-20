# File 18 — 20 October 2026, Fresh Forty-Scope Static Review

**Project:** Sabri Social Homeopathy Platform, File 18 Marketplace  
**Source review base:** `c402d872fd00a0400b4718e47c02cb9d404bcd9d` (PR #3 head; NOT main)  
**Corrective branch:** `fix/file18-40-scope-audit-2026-10-20`  
**Authority:** Three Central Plans Consolidated Governing Master Plan (later governing sections), File 18 Master Plan including 7 August appendices (58 mapped CV requirements), and canonical contracts of Files 00/17/19/20/24/25/26.

## What the forty checks establish

These are **40 distinct source-code control inspections**, not forty full end-to-end passes, not forty executions of the test suite, and not tests against WordPress/MySQL, staging or live. A 'SOURCE' verdict means the named mechanism was found, **not that the entire requirement was verified to function**. The count is:

- 32 scoped controls with source evidence present (including #27, later hardened).
- Six defect-bearing controls: #10, #20, #21, #22, #24, #25.
- Two controls requiring runtime/staging evidence: #29 and #40.
- During corrective deeper inspection of #27, a **seventh concrete safeguard defect** was identified: transaction start and COMMIT return values were not checked. The helper is now hardened.

A defect-free release is **not** asserted. The 58 mapped CV requirements and all cross-file acceptance journeys require a complete trace/functional/staging review before release.

## Forty inspection register

| # | Inspectable control | Result |
|---:|---|---|
| 01 | File 00 identity provider | SOURCE |
| 02 | Identity unavailable fail-closed | SOURCE |
| 03 | Minors and guardian checks | SOURCE |
| 04 | Risk and suspension | SOURCE |
| 05 | Seller ownership | SOURCE |
| 06 | Create/update draft | SOURCE |
| 07 | Submit/moderate listing | SOURCE |
| 08 | Versioned listing lifecycle | SOURCE |
| 09 | Public browse ordering | SOURCE |
| 10 | Browse composite cursor | FIXED-SOURCE |
| 11 | Media attach interface | SOURCE |
| 12 | Save/unsave | SOURCE |
| 13 | File 17 conversation owner | SOURCE |
| 14 | Conversation seller recheck | SOURCE |
| 15 | Offer currency match | SOURCE |
| 16 | Offer seller recheck | SOURCE |
| 17 | Deal seller recheck | SOURCE |
| 18 | 0% commission code constant | SOURCE |
| 19 | REST predispatch CSRF check | SOURCE |
| 20 | Nonce exemption in four mutation paths | FIXED-SOURCE |
| 21 | Expanded search composite cursor | FIXED-SOURCE |
| 22 | Inbound event atomic acknowledgement | FIXED-SOURCE |
| 23 | Outbox recovery lease | SOURCE |
| 24 | Local event consumer acknowledgement | FIXED-SOURCE |
| 25 | Audit lock failure policy | FIXED-SOURCE |
| 26 | Audit insertion failure handling | SOURCE |
| 27 | Database transaction helper | SOURCE + HARDENED |
| 28 | Upgrade lock | SOURCE |
| 29 | Legacy migration reconciliation in installed DB | STAGING-REQUIRED |
| 30 | File 00 suspension event | SOURCE |
| 31 | Payment status reconciliation | SOURCE |
| 32 | File 17 report event | SOURCE |
| 33 | Regulated evidence review | SOURCE |
| 34 | Recall and legal holds | SOURCE |
| 35 | Report/dispute transitions | SOURCE |
| 36 | Privacy export/erasure | SOURCE |
| 37 | File 20 shell adapter | SOURCE |
| 38 | File 26 search adapter | SOURCE |
| 39 | Recall private indexing | SOURCE |
| 40 | Real staging and Founder acceptance | STAGING-REQUIRED |

## Seven corrective findings

1. **CR-01 — Listing paging**: publication-time descending order paired with id-only cursor; fixed in `MKT_Listings::search` with composite published_at/id comparison using current anchor.
2. **CR-02 — Expanded paging**: identical ordering/cursor defect in `MKT_Finalization::search_array`; fixed with the same comparison.
3. **CR-03 — REST nonce exception**: raw Authorization / PHP_AUTH_USER presence accepted as sufficient authentication in four internal code paths; replaced with successfully authenticated WordPress Application Password action evidence and request-scoped nonce checks.
4. **CR-04 — Inbound event atomicity**: external owner-state mutations and inbox 'processed' acknowledgement did not share a DB transaction; now share `MKT_DB::transaction` with explicit acknowledgement count and retryable failure.
5. **CR-05 — Outbox acknowledgement**: the presence of a local action subscriber was counted as success; now local delivery requires explicit `mkt_event_acknowledged` filter acknowledgement. The subscriber-side contract **must be tested** on real integrations.
6. **CR-06 — Audit chain integrity**: lock timeout deliberately wrote `LOCK_UNAVAILABLE` into new audit rows; now fails closed and aborts the governed mutation instead.
7. **CR-07 — DB transaction boundaries**: failed START TRANSACTION or COMMIT calls could be ignored; both now abort on failure.

## Regression and candidate packaging

- `tests/test-october-40-scope-regressions.php` adds static safety assertions and a composite-pagination counterexample.
- Included in `tests/qa-static.sh` and the GitHub Actions PHP 8.1/8.3 matrix.
- Corrective artifact becomes `18-marketplace-2.1.0-RC4.zip` (runtime version still 2.1.0). Its checksum must be taken **only from the final HEAD CI build**. Historical RC3 checksums must not be reused.
- Expected local event acknowledgement must be implemented/tested by every registered `mkt_event` consumer. A registered hook alone is not proof of processing.

## Remaining blockers — no unsupported completion claim

1. GitHub `main` remains documentation-only; PR #3 is a draft candidate and this corrective PR targets PR #3's branch; no direct production merge.
2. Exact deployed plugin/version/checksum, `wp_options` versions, MySQL schema/migration and live configuration remain unknown. **Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
3. Real companion service API contracts, migrated records, affected notifications, buyer/seller/dispute roles, minor-account rules, media scanning, WordPress/Hostinger and browser/RTL accessibility tests still require staging.
4. Fresh database backup **and restore verification**, rollback rehearsal, founder approval, canary deployment and live re-test are mandatory release gates.
5. CI passing means source-level gates only. Do not mark `Staging-Accepted`, `Live-Deployed` or `Operational` without separate date-stamped evidence.

## Required final incident truth ledger

| Field | Truth boundary |
|---|---|
| Repository `main` HEAD | `04a5872e3e2e170ee400e7c607e39abd5484df65` observed before corrective PR |
| PR #3 reviewed source HEAD | `c402d872fd00a0400b4718e47c02cb9d404bcd9d` |
| Corrective repository HEAD | resolve dynamically after final audit/documentation/test commits |
| Source runtime/schema target | 2.1.0 / 2.1.0 |
| Deployed Version | NOT VERIFIED |
| DB Version | NOT VERIFIED |
| Migration State | NOT VERIFIED |
| Live Verification Status | NOT PERFORMED |

**Release disposition: DRAFT, staging blocked pending exact environment evidence and approval.**
