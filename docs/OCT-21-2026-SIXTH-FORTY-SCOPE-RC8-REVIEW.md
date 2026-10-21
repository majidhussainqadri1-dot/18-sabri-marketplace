# File 18 — Sixth Fresh Forty-Scope Plan-to-Source / Companion Audit
**Date:** 21 October 2026 (Asia/Karachi). **RC7 starting exact HEAD:** `251cf7c83f8546e4ff991000a4f3e65c022c06bb` (last green PR #7). **Sixth candidate:** `fix/file18-sixth-forty-plan-audit-2026-10-21`, Draft PR #8 stacked on Draft PR #7 (NOT main). Candidate package `18-marketplace-2.1.0-RC8.zip`; **final HEAD/CI/ZIP SHA requires verification after the final edit**.

## Source-of-authority and owner boundary
Reviewed the uploaded **File 18 Marketplace Complete Master Plan 2026 v1.0** (20 FR, 10 NFR and attached harmonization/58 mapped central-value requirements), the uploaded **Three Central Plans Consolidated Governing Master Plan 2026**, **File 17 Communication Network**, and the relevant File 10/19/20/24/25/26 and Conditional CF-02/CF-03 owner boundaries. Original File18 plan specifies: one canonical owner per fact; external File17 conversations; File10 media; File19 notifications; commission 0%; safe seller eligibility; atomic transitions; bounded retries/recovery and migration/rollback proof. The complete plans are normative; a GitHub source anchor does **not** equal a completed Must test, package or live acceptance.

## 40 distinct source/plan inspection scopes
These are 40 **different inspection subjects**, not a claim to 40 complete independent manual forensic audits. A second CI gate is configured to execute the **full 21-part static/negative/regression suite 40 consecutive times on each of PHP 8.1 and 8.3** (80 runs if both pass). Status of all source anchors requires exact-HEAD source evidence, and functional acceptance is separately **UNVERIFIED** until real owner+DB staging tests. We did not extrapolate 40 source checks into complete proof for all 58 mapped central CV IDs.

| # | Plan/owner ID | Inspection scope | Source owner | Finding/evidence |
|---:|---|---|---|---|
| 1 | F18-FR-001 | Category/prohibited/conditional policy | `includes/class-mkt-policy.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 2 | F18-FR-002 | Seller eligibility, verification, age and risk | `includes/class-mkt-auth.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 3 | F18-FR-003 | Listing create/edit/draft and price | `includes/class-mkt-listings.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 4 | F18-FR-004 | File10 media references, rights and scan | `includes/class-mkt-integrations.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 5 | F18-FR-005 | Regulated evidence and human review | `includes/class-mkt-governance.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 6 | F18-FR-006 | Listing state/version transitions | `includes/class-mkt-state-machines.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 7 | F18-FR-007 | Public search facets and compound cursor | `includes/class-mkt-finalization.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 8 | F18-FR-008 | Public DTO, price and safety labels | `includes/class-mkt-listings.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 9 | F18-FR-009 | Save/report/share with owner state | `includes/class-mkt-moderation.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 10 | F18-FR-010 | File17 buyer–seller contextual chat | `includes/class-mkt-integrations.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 11 | F18-FR-011 | Offer, terms, expiry and idempotency | `includes/class-mkt-commerce.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 12 | F18-FR-012 | Counter/accept/decline/withdraw version | `includes/class-mkt-commerce.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 13 | F18-FR-013 | Deal immutable snapshot and transitions | `includes/class-mkt-commerce.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 14 | F18-FR-014 | Zero commission and single free tier | `includes/class-mkt-commerce.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 15 | F18-FR-015 | Direct/manual payment-status bridge | `includes/class-mkt-events.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 16 | F18-FR-016 | Moderation/takedown and report | `includes/class-mkt-moderation.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 17 | F18-FR-017 | Disputes, decision and appeal | `includes/class-mkt-moderation.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 18 | F18-FR-018 | Promotion off unless approved | `includes/class-mkt-admin.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 19 | F18-FR-019 | Privacy-safe seller insights | `includes/class-mkt-governance.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 20 | F18-FR-020 | Expiry/status/search/contact reconciliation | `includes/class-mkt-maintenance.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 21 | F18-NFR-001 | Object/field/capability authorization | `includes/class-mkt-auth.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 22 | F18-NFR-002 | Privacy export, erasure and legal hold | `includes/class-mkt-privacy.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 23 | F18-NFR-003 | Idempotent inbox/outbox/retry/reconciliation | `includes/class-mkt-events.php` | Inbox stale lease (300s) + SELECT FOR UPDATE; Outbox lease deadline in final WHERE; idempotency uncertain fails closed |
| 24 | F18-NFR-004 | Bounded pagination, query/performance | `includes/class-mkt-finalization.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 25 | F18-NFR-005 | Keyboard, RTL, contrast and responsive UI | `includes/class-mkt-routes.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 26 | F18-NFR-006 | Tamper-evident audit and observability | `includes/class-mkt-audit.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 27 | F18-NFR-007 | Fresh/upgrade/migration/rollback safety | `includes/class-mkt-db.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 28 | F18-NFR-008 | System Check and safe repair | `includes/class-mkt-maintenance.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 29 | F18-NFR-009 | WordPress/PHP/contract compatibility | `includes/class-mkt-integrations.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 30 | F18-NFR-010 | Urdu/Arabic locale and currency/timezone | `includes/class-mkt-finalization.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 31 | CEN-01 | File00 identity and guardian assertions | `includes/class-mkt-integrations.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 32 | CEN-02 | File10 rights/media release owner | `includes/class-mkt-listings.php` | File10 release after audit COMMIT; attachment compensation after ROLLBACK |
| 33 | CEN-03 | File17 messages and context bridge owner | `includes/class-mkt-integrations.php` | Native File17 REST direct conversation, context authorization and fresh listing projection |
| 34 | CEN-04 | File19 notification producer/dedupe owner | `includes/class-mkt-integrations.php` | SUN producer registration, required notification acknowledgement and per-user dedupe |
| 35 | CEN-05 | File20 global navigation/shell owner | `includes/class-mkt-integrations.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 36 | CEN-06 | File24 security/assurance owner | `includes/class-mkt-routes.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 37 | CEN-07 | File25 visual tokens and accessibility | `includes/class-mkt-routes.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 38 | CEN-08 | File26 search projection and takedown | `includes/class-mkt-integrations.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 39 | CEN-09 | CF02/CF03 conditional and zero-custody | `includes/class-mkt-commerce.php` | Owner implementation anchor inspected; functional/staging acceptance pending |
| 40 | CEN-10 | Central release gates/owner atomicity | `includes/class-mkt-rest.php` | Owner implementation anchor inspected; functional/staging acceptance pending |

## Five confirmed code-rooted defect groups corrected on Draft PR #8
**D6-01 / F18-NFR-003 — Inbox permanently stranded after crash.** `MKT_Events::consume_external` reserved `received` outside transaction but would refuse all subsequent duplicate deliveries unless status was `failed`; crash-before-handler therefore left it stranded. Added conservative 300-second stale reservation CAS plus `SELECT ... FOR UPDATE` under the owner transaction, verifying payload hash/status before dispatch. A concurrent or duplicate worker cannot safely execute without this serialized check. Native MySQL race validation remains outstanding.

**D6-02 / F18-NFR-003 — Outbox stale worker could overwrite recovered claim.** `process_outbox` had a 120-second lease but final `processed/retry/dead` updates matched only ID and status, so original worker could overwrite a newer worker's still-processing lease. Final status updates now include that worker's exact `available_at` lease deadline; stale workers' writes affect zero rows. A simulated takeover test checks non-overwrite. File19 per-recipient idempotency still required under at-least-once delivery.

**D6-03 / F18-NFR-003 — False successful idempotency replay and uncertain write replay.** Completed receipt with invalid JSON previously returned literal `true`. Expired `processing` marker was blindly reclaimable after 24h despite a prior committed owner mutation whose response receipt was not saved. Now reject corrupt receipts and return `mkt_idempotency_outcome_uncertain` for stale processing; only verified failed responses are CAS-reclaimable. **Operational blocker:** explicit admin reconciliation/runbook is needed to release genuinely safe pending reservations. This is deliberate fail-closed availability tradeoff, not an exactly-once claim.

**D6-04 / F18-FR-004 — Media owner and audit could diverge, external File10 reference released before local commit.** `attach_media` inserted the local media row before a mandatory audit outside a transaction; a failed audit could leave silently committed media. `remove_media` invoked File10 release before audit even if the local update would later need rollback. Now insert/remove plus audit share a local transaction, and File10 release occurs only after successful local commit or after a failed attach rollback as compensation. **Unresolved:** external File10 reference-release acknowledgement/retry and concurrency-limited `max_media_refs` need real bilateral testing; one-way `do_action` cannot establish durable File10 release.

**D6-05 / F18-FR-001 and CEN-GOV — Admin policy replacement could retire active truth before replacement existed.** `MKT_Admin::handle_policy_save` previously marked active category/business policies as retired, then inserted a new version without checking either write, and only afterward appended audit without a database transaction. An insert/audit failure could leave no current policy, weakening fail-closed listing review. Wrapped retire, immutable new version insertion, error checks and audit in `MKT_DB::transaction` with safe administrator error reporting. `test-four-plan-invariants.php` checks this exact source boundary. **Remaining:** real MySQL concurrent policy-editor race and rollback must be exercised in staging; source assertions do not establish it.

## Real regression evidence and limits
- `tests/test-inbox-crash-recovery.php`: real File18 Inbox handler, simulated stale/fresh/failed reservations, payload conflict, per-event row-lock ordering, rollback.
- `tests/test-outbox-required-notification-ack.php`: real File18 Outbox worker with simulated lost worker lease, required File19 handoff and dead-letter.
- `tests/test-idempotency-durable-receipt.php`: real File18 idempotency handler with corrupt completed JSON, stale uncertain processing, normal receipt and UPDATE failure.
- `tests/test-media-atomicity.php`: real File18 media methods with fake WordPress/MySQL transaction/audit failures and postcommit File10 compensation.
- `tests/test-october-40-scope-regressions.php` and `tests/test-four-plan-invariants.php`: strengthened exact-source safe handling assertions; legacy test changed because automatic uncertain retry is intentionally unsafe.
- Full aggregate `qa-static.sh` plus independent CI steps run on PHP 8.1/8.3. **Do not call MySQL concurrency/migration/real plugin integration PASS from these stub tests.**

## Remaining blockers / release disposition
1. **Live Reality Freeze not possible:** deployed plugin artifact SHA, runtime version, active File00/10/17/19/20/24/25/26, DB `mkt_schema_version`, tables/columns/rows, `wp_options`, logs and migration state **UNVERIFIED**.
2. WPVibe site inventory previously showed no registered site; attempted read-only connect to `https://sabrihomeopathy.com` returned **Cloudflare HTTP 530**. Origin, CF 1xxx error and Ray ID not yet established. Neither WAF bypass nor plugin removal authorized.
3. File17/19/10 actual bilateral WordPress tests, access control, File10 external compensation, admin reconciliation for uncertain Idempotency, database concurrency/rollback and user-role acceptance still outstanding.
4. The central 58 mapped CV requirements and all mandatory FR/NFR end-to-end tests are **not** proven by code-anchor presence alone.
5. Backups and isolated restore proof, staging upgrade/dry-run, PHP/WordPress MySQL versions, keyboard/RTL/zoom/mobile, security/privacy verification, Founder dated approval, package-to-deployment parity and live smoke-retest are **BLOCKED/UNVERIFIED**.
6. Draft PR #8 must not be merged/packaged for production from test green alone. Green CI is repository evidence only. No production patch authorized.

## Mandatory live truth ledger
| Evidence | Current status |
|---|---|
| GitHub `main` HEAD | `04a5872e3e2e170ee400e7c607e39abd5484df65` at start of this audit |
| Correction baseline | `251cf7c83f8546e4ff991000a4f3e65c022c06bb` |
| Latest corrective HEAD | Obtain from Draft PR #8 after final edit |
| Intended package | `2.1.0-RC8` |
| Deployed Version | **UNKNOWN** |
| DB Version | **UNKNOWN** |
| Migration State | **UNKNOWN** |
| Live Verification Status | **NOT PERFORMED** |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

**Disposition: Source-corrected DRAFT RC8 candidate, never 'Live Resolved'.**