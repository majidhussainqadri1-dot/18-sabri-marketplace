# File 18 — Third Fresh 40-Scope Review and Corrective Record
**Review date:** 21 October 2026 (Pakistan local date); **Source baseline:** `57a742a774db6e05a3a27a241c0adc64d45d6abf`; **corrective branch:** `fix/file18-third-deep-audit-2026-10-21`, Draft PR #5 stacked on Draft PR #4 (not main).

## Governing source & interpretation
The uploaded **File 18 Marketplace Complete Master Plan** specifies 20 FRs, 10 NFRs and central-plan traceability, including 58 mapped CV requirements. The **Three Central Plans Consolidated Governing Master Plan** takes precedence over conflicting earlier module text. Also cross-reviewed the File 17 plan and File 19's published PHP producer/validator API. Canonical ownership: File 18 alone owns listings/offers/deals/report/dispute; File 17 owns conversation truth; File 19 notification projection/delivery; File 00 identity; File 20 shell; File 24 assurance; File 26 search derivative. Conditional CF-01/CF-02 plans do not authorize new live runtime or copy clinical/support truth into File 18.

**Method:** forty independently named source inspection *scopes*, shown below, not forty complete manual end-to-end audits. Source anchors are visible for all forty; existence is **not functional acceptance**. A separate CI matrix runs the full static/contract/fault-injection suite **40 consecutive times per PHP version (8.1 and 8.3)**. Test counts must be read from the final exact-HEAD workflow log, not old green runs. Real WordPress/MySQL, browser, connected companions, migration/rollback and live smoke tests remain separate.

## Forty scoped source inspection register

| # | Plan anchor | Specific contract inspected | Source evidence (not acceptance) |
|---:|---|---|---|
| 1 | F18-FR-001 | Safety/prohibited category policy | `class-mkt-policy.php`: evaluate_listing/categories |
| 2 | F18-FR-002 | Verified seller current eligibility | `class-mkt-listings.php`: seller_eligibility/eligibility_snapshot |
| 3 | F18-FR-003 | Draft and edit listing | `class-mkt-listings.php`: create/update |
| 4 | F18-FR-004 | Media provider boundary | `class-mkt-listings.php`: attach_media/media_reference |
| 5 | F18-FR-005 | Governed evidence reviewer decision | `class-mkt-governance.php`: review_evidence/reviewed_by |
| 6 | F18-FR-006 | Listing versioned transitions | `class-mkt-listings.php`: transition/expected_version |
| 7 | F18-FR-007 | Composite cursor search | `class-mkt-finalization.php`: search_array/published_at cursor |
| 8 | F18-FR-008 | Public safe listing DTO | `class-mkt-listings.php`: public_dto |
| 9 | F18-FR-009 | Saved-listing safety and report | `class-mkt-listings.php`: save/transaction failure check |
| 10 | F18-FR-010 | File 17 product conversation handoff | `class-mkt-integrations.php`: open_context_conversation/same_origin |
| 11 | F18-FR-011 | Offer and expiry | `class-mkt-commerce.php`: create_offer/expires_hours |
| 12 | F18-FR-012 | Counter-offer lifecycle | `class-mkt-commerce.php`: transition_offer/countered |
| 13 | F18-FR-013 | Deal accepted & immutable readback | `class-mkt-commerce.php`: create_deal_from_offer/new_deal_id |
| 14 | F18-FR-014 | Zero platform commission | `class-mkt-commerce.php`: commission_percent=0 |
| 15 | F18-FR-015 | External payment status bridge | `class-mkt-events.php`: PaymentStatusChanged/payment_status |
| 16 | F18-FR-016 | Atomic safety-report workflow | `class-mkt-moderation.php`: create_report/transaction/canonical report PK |
| 17 | F18-FR-017 | Structured disputes & state review | `class-mkt-moderation.php`: open_dispute/transition_dispute |
| 18 | F18-FR-018 | Promotions disabled baseline | `class-mkt-db.php`: promotions_enabled=0 |
| 19 | F18-FR-019 | Seller studio metadata | `class-mkt-governance.php`: seller_studio/metrics |
| 20 | F18-FR-020 | Listing/offer expiry | `class-mkt-maintenance.php`: expire_records |
| 21 | F18-NFR-001 | Actor/object authorization | `class-mkt-auth.php`: can/own_listing |
| 22 | F18-NFR-002 | Privacy export and erasure | `class-mkt-privacy.php`: export/erase |
| 23 | F18-NFR-003 | Outbox retry/lease | `class-mkt-events.php`: process_outbox/lease |
| 24 | F18-NFR-004 | Bounded query pagination | `class-mkt-finalization.php`: LIMIT/cursor |
| 25 | F18-NFR-005 | Accessible route assets (source only) | `class-mkt-routes.php`: enqueue_assets |
| 26 | F18-NFR-006 | Audit serial lock lifetime | `class-mkt-audit.php`: transaction_active/deferred release |
| 27 | F18-NFR-007 | Schema version and migration | `class-mkt-db.php`: required_columns/migrate_legacy_foundation |
| 28 | F18-NFR-008 | Maintenance and source reconciliation | `class-mkt-maintenance.php`: reconcile_sellers/communication_handoffs |
| 29 | F18-NFR-009 | Companion contract registry | `class-mkt-integrations.php`: contract_registry/assurance_controls |
| 30 | F18-NFR-010 | Locale and language filtering | `class-mkt-finalization.php`: normalize_language |
| 31 | CEN-OWN/File00 | Identity assertions only from File 00 | `class-mkt-integrations.php`: identity_assertions |
| 32 | CEN-OWN/File17 | File 17 owns conversation state | `class-mkt-integrations.php`: open_context_conversation |
| 33 | CEN-OWN/File19 | File 19 owns notification projection | `class-mkt-integrations.php`: sun_ingest_domain_event |
| 34 | CEN-SHELL/File20 | Global shell remains File 20 owned | `class-mkt-integrations.php`: shell_navigation |
| 35 | CEN-SEC/File24 | File 24 assurance boundary | `class-mkt-integrations.php`: assurance_controls |
| 36 | CEN-SEARCH/File26 | Search derivative only | `class-mkt-integrations.php`: search_provider |
| 37 | CEN-BIZ/0% | No commission in deal snapshot | `class-mkt-commerce.php`: commission_percent=0 |
| 38 | CEN-SEC/CSRF | WordPress authenticated mutation protection | `class-mkt-rest-security.php`: nonce + application password |
| 39 | CEN-PRIV/Routes | Private account routes no-store/noindex | `class-mkt-routes.php`: DONOTCACHEPAGE/noindex |
| 40 | CEN-OWN/REST | Owner mutation+audit+outbox atomic | `class-mkt-rest.php`: atomic_owner_commands/transaction |

## Confirmed corrections this session

1. **CR3-01 — Report identity:** `MKT_Moderation::create_report` used `$wpdb->insert_id` after audit/outbox inserted unrelated records. Capture report PK immediately after insert and verify same report public ID.
2. **CR3-02 — Partial safety report:** report row, mandatory audit and outbox were not in one native transaction. A single `MKT_DB::transaction` now commits or rolls back all three. Runtime-shaped regression simulates audit failure, outbox failure and missing readback.
3. **CR3-03 — Save/unsave false success:** raw `INSERT IGNORE` / `DELETE` return values were ignored. Save now rejects DB failure with a safe WP_Error; actual state changes and audit are transactional; idempotent no-ops remain success.
4. **CR3-04 — REST mutation boundary:** listing creation/edit/submit/transition and deal/report/dispute transitions previously called owner methods without the required common DB transaction at the REST boundary. Added selective transaction wrappers; already transactional offer, report-create, save and dispute-open commands stay outside wrapper to avoid incorrect nested commits. `MKT_DB` itself now safely reuses an existing outer transaction.
5. **CR3-05 — Audit chain race:** the named audit serialization lock was released after INSERT but **before owner COMMIT**, allowing a competing session to read stale last-committed hash. Audit now defers lock release to owner COMMIT/ROLLBACK with re-entrant acquire/release count. Outside owner transactions, lock is released in `finally` even after exceptions.

**Additional hardening (not independently counted as a proven live incident):** MySQL nested `START TRANSACTION` implicitly commits an outer transaction. The helper now checks an active unit of work and does not issue another START, reducing potential premature commit.

## Test evidence / release identity

- `tests/test-report-atomicity.php`: executes actual moderation class with fake WPDB and injected failing audit/outbox/readback, verifies no committed report on failure.
- `tests/test-audit-transaction-lock.php`: executes actual transaction/audit classes with a simulated WPDB, verifies lock order after COMMIT/ROLLBACK, nested START avoidance and correct release counts.
- `tests/test-october-40-scope-regressions.php`: includes new source assertions for report and save.
- `tests/qa-static.sh` includes both suites. GitHub Actions PHP 8.1/8.3 matrix + counted 40 full-suite runs; deterministic ZIP candidate renamed **2.1.0-RC5** (runtime version 2.1.0). **Final checksum must be read only from final-head CI.**
- These are not MySQL concurrency/proxy/browser integration tests. Do not infer real deployed correctness.

## Outstanding acceptance and obstacles
1. **Live Reality Freeze missing:** exact deployed plugin checksum/version, `wp_options`, schema and migration state, runtime logs and actual activated modules are unavailable. **Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
2. WordPress connector was previously refused at the Cloudflare edge (HTTP 530) and current connector inventory reports **no registered site**. Neither response establishes root cause; no security controls have been disabled or bypassed.
3. Real File 00/17/19/20/24/25/26 contract tests, clinical/private safety boundaries, browser Urdu RTL/keyboard/zoom, backup restoration and rollback rehearsal, staging, migration dry-run and Founder approval not evidenced.
4. Some legacy/non-REST invocations and post-commit external media release/handoffs still require dedicated failure-atomicity inspection and staging fault-injection. A safe merge cannot be declared from source anchors alone.
5. Draft PR #5 targets Draft PR #4; PR #4 targets Draft PR #3. No merge, deployment or live re-test completed.

## Incident truth ledger
| Field | Status |
|---|---|
| Repository main | Separate from corrective branches; parity unproven |
| Corrective reviewed baseline | `57a742a774db6e05a3a27a241c0adc64d45d6abf` |
| Exact corrective final HEAD | Check PR #5 after final documentation changes |
| Source candidate version | 2.1.0-RC5 |
| Deployed Version | **Unverified** |
| DB Version | **Unverified** |
| Migration State | **Unverified** |
| Live Verification Status | **Not performed** |

**Disposition: DRAFT / automated QA candidate / no production authorization.**