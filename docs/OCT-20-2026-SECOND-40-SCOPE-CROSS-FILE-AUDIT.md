# File 18 — Second Fresh 40-Scope Governing and Cross-File Audit
**Date:** 20 October 2026  
**Corrective PR:** #4, branch `fix/file18-40-scope-audit-2026-10-20`  
**Baseline source:** `69e5e604d4bef6135b57ef57ce0e41e6a27cf13e` (prior RC4 QA green)  
**Plan authorities:** Three Central Plans Consolidated Governing Master Plan; dedicated File 18 plan (20 FR + 10 NFR + 58 mapped central CV requirements); File 17 Communication Plan; independently inspected File 19 actual main-branch PHP producer and event-validator contracts.

## Truthful method
Forty **separately scoped** source inspections below compared implementation anchors to the named owner/requirement. An anchor's presence **does not prove functional completeness**, security, acceptance or staging correctness. Separately, the correction branch's GitHub Actions PHP 8.1/8.3 quality workflow is configured to run the **entire aggregate static/regression suite 40 sequential times per PHP matrix job**. Those are 40 real repeated **automated static QA passes per PHP version**, **not 40 independent full WordPress/DB/live reviews**. Exact final-head log and count must be checked before asserting that the repetition passed.

## Forty scope register

| # | Review scope | Verdict |
|---:|---|---|
| 01 | F18-FR-001 — Policy taxonomy | Source anchor found; runtime unverified |
| 02 | F18-FR-002 — Seller eligibility | Source anchor found; runtime unverified |
| 03 | F18-FR-003 — Listing draft | Source anchor found; runtime unverified |
| 04 | F18-FR-004 — Media provider | Source anchor found; runtime unverified |
| 05 | F18-FR-005 — Regulated evidence review | Source anchor found; runtime unverified |
| 06 | F18-FR-006 — Listing lifecycle | Source anchor found; runtime unverified |
| 07 | F18-FR-007 — Stable search/facets | Source anchor found; runtime unverified |
| 08 | F18-FR-008 — Public listing DTO | Source anchor found; runtime unverified |
| 09 | F18-FR-009 — Save/share/report bridge | Source anchor found; runtime unverified |
| 10 | F18-FR-010 — File 17 conversation handoff | Source anchor found; runtime unverified |
| 11 | F18-FR-011 — Offers | Source anchor found; runtime unverified |
| 12 | F18-FR-012 — Negotiation | Source anchor found; runtime unverified |
| 13 | F18-FR-013 — Deal and readback | Source anchor found; runtime unverified — corrective code added |
| 14 | F18-FR-014 — Zero commission | Source anchor found; runtime unverified |
| 15 | F18-FR-015 — Payment status | Source anchor found; runtime unverified |
| 16 | F18-FR-016 — Moderation | Source anchor found; runtime unverified |
| 17 | F18-FR-017 — Disputes | Source anchor found; runtime unverified |
| 18 | F18-FR-018 — Promotion disablement | Source anchor found; runtime unverified |
| 19 | F18-FR-019 — Seller studio | Source anchor found; runtime unverified |
| 20 | F18-FR-020 — Expiry/reconciliation | Source anchor found; runtime unverified |
| 21 | F18-NFR-001 — Authorization | Source anchor found; runtime unverified |
| 22 | F18-NFR-002 — Privacy | Source anchor found; runtime unverified |
| 23 | F18-NFR-003 — Reliability/outbox | Source anchor found; runtime unverified |
| 24 | F18-NFR-004 — Performance bounds | Source anchor found; runtime unverified |
| 25 | F18-NFR-005 — Accessibility | Source anchor found; runtime unverified |
| 26 | F18-NFR-006 — Audit/observability | Source anchor found; runtime unverified |
| 27 | F18-NFR-007 — Schema and migration | Source anchor found; runtime unverified — corrective code added |
| 28 | F18-NFR-008 — Maintenance | Source anchor found; runtime unverified |
| 29 | F18-NFR-009 — Compatibility/version | Source anchor found; runtime unverified |
| 30 | F18-NFR-010 — Localization | Source anchor found; runtime unverified |
| 31 | File 00 — Current identity assertions | Source anchor found; runtime unverified |
| 32 | File 17 — Conversation owner | Source anchor found; runtime unverified |
| 33 | File 19 — Notifications (native API corrected) | Source anchor found; runtime unverified — corrective code added |
| 34 | File 20 — Application shell | Source anchor found; runtime unverified |
| 35 | File 24 — Assurance | Source anchor found; runtime unverified |
| 36 | File 26 — Search provider | Source anchor found; runtime unverified |
| 37 | Central — Single free tier | Source anchor found; runtime unverified |
| 38 | Central — Nonce/idempotency | Source anchor found; runtime unverified |
| 39 | Central — Private route caching | Source anchor found; runtime unverified |
| 40 | Central — Release acceptance | Source anchor found; runtime unverified |

### Six newly proven implementation defects corrected
- **F18-NEW-01 — Deal identity corruption:** `MKT_Commerce::create_deal_from_offer` used `$wpdb->insert_id` *after* mandatory audit/outbox INSERTs. The new Deal PK is captured immediately after insert and readback is verified against the public ID. Related: F18-FR-013; CV-204; AJ-23.
- **F18-NEW-02 — Silently incomplete legacy migration:** seller/listing reads and seller/listing/handoff writes could fail while migration version was marked completed. Added fail-closed read/write checks; only successful handoff INSERTs increment imported counts. Related: F18-NFR-007; CV-283; File 17 ownership.
- **F18-NEW-03 — False schema promotion:** `dbDelta` loops promoted `mkt_schema_version` without verifying table/critical-column existence. Added table/column confirmation before version promotion. Related: F18-NFR-007.
- **F18-NEW-04 — File 17 origin mismatch:** conversation URL handoff compared host only; scheme/port mismatches passed. Full scheme/host/port comparison, no userinfo.
- **F18-NEW-05 — Unsafe Back origin:** referer navigation compared host only; scheme/port downgrade now rejected.
- **F18-NEW-06 — Native File 19 producer incompatibility:** File 18 emitted an older `sabri_notify_*` shape rather than File 19's `sun.event.v1` envelope. Verified File 19's default-branch `SUN_Event_Validator` + `SUN_Producer_Registry` + public `sun_ingest_domain_event`. Added registered `file-18-marketplace` producer with `owner=File 18`, dotted past-tense event type, stable per-user event ID, schema version, timestamp, canonical recipients, safe minimized summary and explicit processed/duplicate/failure semantics.

**Test-only defect, distinct from six product defects:** newly authored deal-ID regression assertion accidentally interpolated PHP variables, yielding a false failure. Corrected and reran automated QA.

### Evidence coverage and unresolved integration questions
- Real File 00 verified identity, minor/guardian and suspension contract: source adapter exists; actual deployed contract/state not checked.
- File 17: communication plan and public source contract read; listing conversation creation still needs real two-account test and same-origin HTTPS test.
- File 19: observed canonical source API differs from File 18's previous adapter; new direct integration has an isolated PHP stub test. Must test **both real modules together**, including producer registration, source-event replay, preferences and delivery under outage.
- File 20/24: adapters visible; real navigation, assurance and theme integration not checked.
- File 26: local provider adapter visible; exact current File 26 deployed/accepted source and indexing contract not independently established.
- 58 central CV requirements have been mapped by the governing File 18 plan but are **not each individually proven coded and accepted by these 40 source inspections**. Central cross-cutting items remain owned by their declared canonical modules.
- Live WordPress connection not authorized/available through tools. Earlier WPVibe connection attempt returned Cloudflare HTTP 530; cause is not established. No firewall bypass/security disablement or live changes performed.
- Source, QA, Staging, Live, Operational are independent statuses. PR #4 is Draft based on PR #3, not main.

### Gates remaining
1. Final exact-HEAD PHP 8.1/8.3 matrix and both counted 40-pass logs + deterministic RC4 SHA-256.
2. Actual WordPress+MySQL fresh install and supported upgrade from real installed package; schema options and migration report.
3. Real File 00, 17, 19, 20, 24, 25 and 26 adapters + negative/blocked/suspended/guardian use cases.
4. Browser/device/RTL/keyboard/400% zoom, payment safe-claims, privacy export/erasure and regulated product safety tests.
5. Restored backup + data-protecting rollback rehearsal, Founder approval, controlled deployment and live re-test.

### Live-first reality freeze
- Repository corrective source: resolve after final documentation/test commits, never infer from historical HEAD.
- Deployed version: **UNVERIFIED**.
- DB version: **UNVERIFIED**.
- Migration state: **UNVERIFIED**.
- Live verification status: **NOT PERFORMED**.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
