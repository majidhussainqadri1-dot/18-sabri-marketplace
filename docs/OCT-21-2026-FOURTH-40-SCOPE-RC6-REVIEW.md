# File 18 — Fourth plan-to-code forty-scope audit and RC6 evidence register
**Date:** 21 October 2026 (Asia/Karachi)
**Baseline:** `8f86cfb8fafd881d1167d453b3094cfffc3b472f` (Draft PR #5, 2.1.0-RC5)
**New candidate:** Draft PR #6 `fix/file18-fourth-plan-audit-2026-10-21`, 2.1.0-RC6
**Precedence:** Latest explicit Founder decisions and Three Central Plans Consolidated Governing Plan; File 18 Complete Master Plan v1.0 (including final governing amendment); companion File 17 plan and independently inspected File 19 main-branch validator, registry and notification service. Conditional CF-01/CF-02 remain future conditional owners, not new live modules.

## What forty means
**40 separate plan-related source inspections** were performed against the exact candidate source tree: 20 F18 functional, 10 F18 nonfunctional, 10 central/companion boundaries. Each found the corresponding implementation **anchor**. This establishes only source presence; it is not proof that every requirement is fully coded, tested, usable, or accepted. Independently, GitHub Actions is configured to execute the **complete aggregate PHP/JS/CSS/static/fault-injection suite 40 times consecutively for PHP 8.1 and 8.3**. Only logs for the *final* commit and candidate checksum can prove 40/40 success. Tests using fake WordPress/DB are explicit simulations, not real MySQL tests.

| # | Requirement / boundary | Inspection outcome |
|---:|---|---|
| 01 | F18-FR-001 Category/prohibited policy | Source anchor verified on baseline; staging/live behavior not established |
| 02 | F18-FR-002 Seller eligibility | Source anchor verified on baseline; staging/live behavior not established |
| 03 | F18-FR-003 Draft and edit | Source anchor verified on baseline; staging/live behavior not established |
| 04 | F18-FR-004 Secure media references | Source anchor verified on baseline; staging/live behavior not established |
| 05 | F18-FR-005 Regulated evidence review | Source anchor verified on baseline; staging/live behavior not established |
| 06 | F18-FR-006 Listing state transitions | Source anchor verified on baseline; staging/live behavior not established |
| 07 | F18-FR-007 Publication cursor search | Source anchor verified on baseline; staging/live behavior not established |
| 08 | F18-FR-008 Public DTO | Source anchor verified on baseline; staging/live behavior not established |
| 09 | F18-FR-009 Saved listing/report | Source anchor verified on baseline; staging/live behavior not established |
| 10 | F18-FR-010 File 17 conversation | Source anchor verified on baseline; staging/live behavior not established |
| 11 | F18-FR-011 Offer and expiry | Source anchor verified on baseline; staging/live behavior not established |
| 12 | F18-FR-012 Negotiation/counter | Source anchor verified on baseline; staging/live behavior not established |
| 13 | F18-FR-013 Deal and immutable return ID | Source anchor verified on baseline; staging/live behavior not established |
| 14 | F18-FR-014 Zero commission | Source anchor verified on baseline; staging/live behavior not established |
| 15 | F18-FR-015 Payment status projection | Source anchor verified on baseline; staging/live behavior not established |
| 16 | F18-FR-016 Moderation/safety report | Source anchor verified on baseline; staging/live behavior not established |
| 17 | F18-FR-017 Deal disputes | Source anchor verified on baseline; staging/live behavior not established |
| 18 | F18-FR-018 Disabled unapproved promotions | Source anchor verified on baseline; staging/live behavior not established |
| 19 | F18-FR-019 Seller insights | Source anchor verified on baseline; staging/live behavior not established |
| 20 | F18-FR-020 Expiry/reconciliation | Source anchor verified on baseline; staging/live behavior not established |
| 21 | F18-NFR-001 Object/field authorization | Source anchor verified on baseline; staging/live behavior not established |
| 22 | F18-NFR-002 Privacy lifecycle | Source anchor verified on baseline; staging/live behavior not established |
| 23 | F18-NFR-003 Durable outbox and File19 handoff | Source anchor verified on baseline; staging/live behavior not established |
| 24 | F18-NFR-004 Bounded queries/cursor | Source anchor verified on baseline; staging/live behavior not established |
| 25 | F18-NFR-005 Accessibility/RTL source assets | Source anchor verified on baseline; staging/live behavior not established |
| 26 | F18-NFR-006 Audit/trace/lock | Source anchor verified on baseline; staging/live behavior not established |
| 27 | F18-NFR-007 Schema migration/rollback | Source anchor verified on baseline; staging/live behavior not established |
| 28 | F18-NFR-008 Maintenance and repair | Source anchor verified on baseline; staging/live behavior not established |
| 29 | F18-NFR-009 Versioned companion contracts | Source anchor verified on baseline; staging/live behavior not established |
| 30 | F18-NFR-010 Locale/currency | Source anchor verified on baseline; staging/live behavior not established |
| 31 | CEN-OWN File 00 identity assertions | Source anchor verified on baseline; staging/live behavior not established |
| 32 | CEN-OWN File 17 communication ownership | Source anchor verified on baseline; staging/live behavior not established |
| 33 | CEN-OWN File 19 notification ownership | Source anchor verified on baseline; staging/live behavior not established |
| 34 | CEN-OWN File 20 shell | Source anchor verified on baseline; staging/live behavior not established |
| 35 | CEN-SEC File 24 assurance | Source anchor verified on baseline; staging/live behavior not established |
| 36 | CEN-OWN File 26 search projection | Source anchor verified on baseline; staging/live behavior not established |
| 37 | CEN-BIZ Single free / zero commission | Source anchor verified on baseline; staging/live behavior not established |
| 38 | CEN-SEC CSRF/verified authentication | Source anchor verified on baseline; staging/live behavior not established |
| 39 | CEN-PRIV Noindex/no-store | Source anchor verified on baseline; staging/live behavior not established |
| 40 | CEN-REL Transactional owner mutations | Source anchor verified on baseline; staging/live behavior not established |

## Newly established and source-corrected defects

1. **F18-R4-01 — Cross-consumer outbox false completion.** `MKT_Events::process_outbox` previously regarded either platform/local consumer acknowledgement as sufficient even when `notify_user_ids` required a distinct File 19 notification handoff and that handoff failed. Corrected the required-recipient test and acknowledgement predicate; failed handoffs now follow bounded retries/dead-letter instead of being falsely marked processed. Originating actor is not their own notification recipient.
2. **F18-R4-02 — Negative listing price silently converted to free.** `MKT_Listings::sanitize_input` clamped negative price to 0.00 before `MKT_Policy::evaluate_listing` could reject invalid price. Preserve value for domain rejection, avoiding a misleading free listing. Negative stock is similarly preserved for explicit error instead of silently rewritten.
3. **F18-R4-03 — Non-finite money/stock values.** PHP numeric conversion can produce `INF` for extremely large exponential inputs. Listing price/quantity and offer/counteroffer amounts lacked finite-number validation. Added `is_finite()` guards in actual policy and commerce methods. These numeric changes are **not** a full fixed-point-money redesign or a real database-boundary benchmark.

## Corrective tests and CI
- `tests/test-outbox-required-notification-ack.php`: executes real File18 outbox worker with fake $wpdb and failing/working partner receipt, domain success, actor-only notifications, and retry/dead-letter limits.
- `tests/test-money-stock-validation.php`: exercises actual private listing normalization and actual domain policy with negative, infinite and ordinary finite numbers.
- `tests/test-october-40-scope-regressions.php`: protects the key predicates and offer/counter finite-value checks against regression.
- `tests/qa-static.sh`: includes both new dynamic suites, in addition to earlier source/security/contract/migration and File19 stub tests.
- Workflow: 40 counted aggregate QA runs for each PHP matrix member. Both PHP variants independently create deterministic **2.1.0-RC6 ZIP**. Record its SHA-256 only from final exact-head CI logs; old RC4/RC5 checksums are invalid for RC6.

## Cross-file and central requirement gaps that cannot be silently marked complete
- File 19 source API: `sun_ingest_domain_event`, `sun_register_notification_producer` and documented acknowledgement fields independently verified in File19 source. Full integration of actual active modules still untested.
- File 17 real conversation creation: the File18 adapter expects `sabri_network_open_context_conversation`, `sn_open_context_conversation` or `sabri_communication_open_context_conversation` hook; targeted searches and the main File17 conversation/context code inspected have not established a matching running provider. Actual bilateral WordPress acceptance **blocked/unverified**, not 'fixed'.
- File00/20/24/25/26 active owner contracts, File24 assurance, File26 indexing and File25 browser/RTL/zoom/readability require staging evidence.
- Central plan's **58 mapped CV requirements** must be individually traced in RTM and proven; these 40 source anchors do **not** certify all 58.
- Other code risks including non-transactional external-media release, durable idempotency marker errors, independent provider retry policy and WordPress/MySQL concurrency need further adversarial acceptance before a release claim.
- No real patient chart is transferred to File18; CF-01 clinical data owner remains conditional. CF-02 may track appeals later but cannot overwrite File18's native listing/dispute decision.

## Exact-deployed-state / live blockers (no production patch authorized)
* GitHub `main`: separate reality; previously observed `04a5872e3e2e170ee400e7c607e39abd5484df65`.
* PR chain: Draft #6 -> Draft #5 -> Draft #4 -> Draft #3, no final main deployment established.
* Deployed plugin runtime files/checksum/version: **UNVERIFIED**.
* Live DB/schema version, `wp_options`, migration state and logs: **UNVERIFIED**.
* WPVibe WordPress site inventory: no registered site at previous check; connector attempts returned Cloudflare HTTP 530, which does **not** prove origin cause or license disabling WAF.
* Live verification: **NOT PERFORMED**; backup/restore and rollback rehearsal, real companion integrations, staging/device acceptance and Founder approval still required.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
**Release classification: repository corrective candidate only; not merged, staged, deployed or resolved in production.**
