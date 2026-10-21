# File18 Marketplace — Eighth 40-Scope Exact-Source/Central-Owner Review (21 October 2026)
**Starting baseline:** `eb11cebd866205543e11888e824cd2a75609c631` — verified successful RC9 Draft PR #9 (never equivalent to production).
**Review branch:** `fix/file18-eighth-forty-plan-review-2026-10-21` / Draft PR #10 stacked onto PR #9.
**RC10 package:** `18-marketplace-2.1.0-RC10.zip`. The actual final HEAD/CI/SHA-256 must be established *after all commits*.

## Governing materials
- Uploaded File18 Marketplace Complete Master Plan, including its later central harmonization amendment: canonical seller/listing/offer/deal/safety owner; zero percent commission; File17 communication, File19 delivery, File10 media; privacy/legal hold and reliable replay; WordPress/MySQL migration/rollback and staging-first Founder acceptance.
- Uploaded Three Central Plans Consolidated Governing Master Plan: one canonical owner per fact; 58 central mapped requirements governing this file; no donation advantage or core fee; acceptance gates, source-to-package-to-staging-to-live traceability.
- Uploaded File17 communication plan and CF02/CF03 conditional plans: no duplicate conversation, appeals or financial-owner truth.
- Exact GitHub RC9 source and candidate RC10 source. These are *repository evidence only*. None of the uploaded plans proves that an implementation or deployed fix exists.

## Forty individually identified review dimensions
**Scope definition:** 20 functional, 10 nonfunctional and 10 central/cross-file checks. The entire static/contract/fault-injection suite is separately run 40 times per PHP 8.1 and 8.3 in CI. This does not mean 40 complete fresh manual end-to-end audits. Existing `tests/test-seventh-40-plan-anchors.php` checks all 40 source anchors; specific failed-state fixtures in this branch test privacy/schema. All real WordPress/MySQL integrations remain unverified.

| # | Requirement | What was checked | Current source | Evidence boundary / still needed |
|---:|---|---|---|---|
| 1 | F18-FR-001 | Marketplace ethical scope, prohibited products | `includes/class-mkt-policy.php` — Backend policy rules and rejects | Real category/medicine moderation roles |
| 2 | F18-FR-002 | Seller active identity/guardian eligibility | `includes/class-mkt-auth.php` — File00 assertions; fail-closed eligibility | Actual File00 suspended/underage live owner |
| 3 | F18-FR-003 | Listings, seller/price truth | `includes/class-mkt-listings.php` — Creation, raw numeric checks, draft | Real MySQL failover/transactions |
| 4 | F18-FR-004 | File10 media permissions/rights | `includes/class-mkt-listings.php` — Transactional local references | File10 owner release/ack |
| 5 | F18-FR-005 | Evidence and medicinal review | `includes/class-mkt-governance.php` — Evidence status, reviewer decision | Human-role decision & claimant privacy |
| 6 | F18-FR-006 | Optimistic listing transition | `includes/class-mkt-listings.php` — Expected version state gate | Simultaneous WP/MySQL actors |
| 7 | F18-FR-007 | Search/filter pagination | `includes/class-mkt-finalization.php` — Bounded public cursor | Large/RTL/low bandwidth datasets |
| 8 | F18-FR-008 | Public-safe listing DTO | `includes/class-mkt-listings.php` — Privacy-filtered public contract | Index/cache leakage acceptance |
| 9 | F18-FR-009 | Saved items, safety reporting | `includes/class-mkt-listings.php` — Audited write failure guards | Other persons and spam throttling |
| 10 | F18-FR-010 | File17 buyer/seller chat context | `includes/class-mkt-integrations.php` — Native File17 REST owner and current source card | Live File17 REST permissions/authorization |
| 11 | F18-FR-011 | Offers, amount, expiry, replay | `includes/class-mkt-commerce.php` — Finite-price, idempotent offer | DB duplicate and crash cases |
| 12 | F18-FR-012 | Counteroffers and acceptance | `includes/class-mkt-commerce.php` — Versioned transitions | Competing offers under MySQL |
| 13 | F18-FR-013 | Deal canonical snapshot | `includes/class-mkt-commerce.php` — Immutable deal ID and snapshot | Cross-actor concurrent acceptance |
| 14 | F18-FR-014 | Zero commission law | `includes/class-mkt-commerce.php` — Commission 0 on snapshots | CF03 later zero-fee contract |
| 15 | F18-FR-015 | Direct payment status bridge | `includes/class-mkt-events.php` — Source event/reconciliation guards | Actual provider event signed sandbox |
| 16 | F18-FR-016 | Safety report privacy and triage | `includes/class-mkt-moderation.php` — Atomic report/audit/outbox | Moderator abuse and legal hold |
| 17 | F18-FR-017 | Deal disputes, case evidence | `includes/class-mkt-moderation.php` — Dispute state, participant identity | CF02 appeals future boundary |
| 18 | F18-FR-018 | No paid promotions advantage | `includes/class-mkt-db.php` — Promotions off in settings | UI/audit/donor-parity check |
| 19 | F18-FR-019 | Seller studio and insights | `includes/class-mkt-governance.php` — Bounded seller dashboard | Live File17 SLA projection |
| 20 | F18-FR-020 | Expiry and status reconciliation | `includes/class-mkt-maintenance.php` — Scheduled owner repair | Real WP Cron event delivery |
| 21 | F18-NFR-001 | Role and object authorization | `includes/class-mkt-auth.php` — Capability/ownership | IDOR + CSRF browser acceptance |
| 22 | F18-NFR-002 | Privacy export, erasure and hold | `includes/class-mkt-privacy.php` — Fail-closed hold gate and failed-write detection | Legal review of shared dispute evidence |
| 23 | F18-NFR-003 | Inbox/outbox/idempotency replay | `includes/class-mkt-events.php` — Leases and transactional dedupe | Real two-worker MySQL race |
| 24 | F18-NFR-004 | Bounded search queries | `includes/class-mkt-finalization.php` — LIMIT/cursor | Load/soak and index plan |
| 25 | F18-NFR-005 | Accessible and responsive routes | `includes/class-mkt-routes.php` — RTL/a11y assets | Real mobile + keyboard/screen reader |
| 26 | F18-NFR-006 | Tamper-evident audit trail | `includes/class-mkt-audit.php` — Hash chain/lock lifetime | Concurrent MySQL and retention |
| 27 | F18-NFR-007 | Fresh/upgrade/migration readiness | `includes/class-mkt-governance.php` — Real tables/columns verified before version | Live DB version, indexes, rollback |
| 28 | F18-NFR-008 | Recovery and maintenance | `includes/class-mkt-maintenance.php` — Expiry and seller status repair | Staging restore / WP Cron |
| 29 | F18-NFR-009 | Contract compatibility | `includes/class-mkt-integrations.php` — File17/19 notification and chat | Real provider version negotiation |
| 30 | F18-NFR-010 | Language, currency, timezone | `includes/class-mkt-finalization.php` — Locale-aware filters | Urdu/English RTL and money display |
| 31 | CENTRAL-01 | Canonical identity File00 | `includes/class-mkt-integrations.php` — Identity assertions not rewritten | Real File00 blocked account |
| 32 | CENTRAL-02 | Canonical File10 media | `includes/class-mkt-listings.php` — Only references, no ownership duplication | Provider compensation ack |
| 33 | CENTRAL-03 | Canonical File17 communications | `includes/class-mkt-integrations.php` — Context reference REST bridge | Real conversation/context lifecycle |
| 34 | CENTRAL-04 | Canonical File19 notifications | `includes/class-mkt-integrations.php` — Native SUN producer and intake | Actual File19 subscriptions/delivery |
| 35 | CENTRAL-05 | Global shell File20 | `includes/class-mkt-integrations.php` — Navigation shell hook | Live coexistence and fallback |
| 36 | CENTRAL-06 | File24 assurance ownership | `includes/class-mkt-integrations.php` — Control registration boundary | Live security owner parity |
| 37 | CENTRAL-07 | File25 design/visual tokens | `includes/class-mkt-routes.php` — Mobile/RTL CSS/JS | Actual tokens, contrast, zoom |
| 38 | CENTRAL-08 | File26 global search owner | `includes/class-mkt-integrations.php` — Index downstream projection | Deletion/recall reindex |
| 39 | CENTRAL-09 | Privacy/legal hold governance | `includes/class-mkt-governance.php` — Hold guard + retention facts | MySQL legal records, audit |
| 40 | CENTRAL-10 | Release and provenance gates | `includes/class-mkt-release-gates.php` — QA/manifest/staging gate source | Founder approval and live parity |

## Three additional confirmed source-defect categories repaired
**R8-01 — Legal hold failure was interpreted as permission to erase.** `MKT_Governance::has_active_hold` returned FALSE when hold table was absent, and nullable/error COUNT query was cast to zero. Now it considers missing/partial hold table, unavailable count, or SQL error as hold truth **unknown/protective**. `MKT_Privacy::erase` checks minimum deployed hold columns *before any destructive work* and returns `done=false, items_retained=true` with repair guidance rather than silently destroying evidence. This is safety-oriented fail-closed, not a claim of a live legal hold.

**R8-02 — Failed erasure writes could be marked complete.** Former `MKT_Privacy::erase` ignored failed seller anonymization and saves deletion, and marked report/dispute evidence minimization as `items_removed` for any non-false result without treating false as a blocker. Now any SQL write failure returns an incomplete privacy request; actual affected-row success is distinguished from zero-effect. The existing shared-dispute evidence/retention policy still requires explicit staging/legal acceptance.

**R8-03 — Governance schema version could lie about actual deployment.** `MKT_Governance::install_schema` called `dbDelta` then unconditionally updated `mkt_governance_schema_version`. `maybe_upgrade` trusted the option and skipped repair even if tables/columns disappeared. Added `schema_ready()` verifying all 3 owner tables and required columns; schema version is promoted only after fresh verification, and invalidated with `mkt_governance_schema_error=schema_verification_failed` otherwise. An installed plugin must still be checked in its own WP/MySQL schema; repository code does not establish installed state.

**Tests:** `tests/test-privacy-hold-failclosed.php` exercises actual privacy + governance classes under absent/partial tables, unknown hold query, seller/save/report writes failing. `tests/test-governance-schema-truth.php` exercises actual `schema_ready`, `maybe_upgrade` and failed/promoted `wp_options` markers with fake `dbDelta`. Both are standalone direct GitHub Actions matrix steps and inside the full 26-stage QA suite; repeated 40 times per PHP version if final CI passes.

## Blockers that cannot be removed by an unverified GitHub patch
1. Current Live deployed package, active plugins, checksums, File18 DB schema/indexes, migration options, runtime error and log have **not** been acquired. **Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
2. WPVibe inventory has no connected WordPress site; connector has encountered Cloudflare HTTP 530. Neither the exact 1xxx diagnostic error nor the Ray ID/origin DNS routing path is available, so do not assume server-side root cause or disable security.
3. MySQL legal-hold concurrency / index constraints, data migration across old releases, actual WordPress privacy exporter/eraser, real File00/10/17/19/20/24/25/26 integrations and File24 legal classification have **not** passed real staging acceptance.
4. The 58 mapped central CV requirements require individual acceptance. Forty source anchors ≠ 58/58 full accepted requirements.
5. Two fresh final reviews, staging with verified backup/restore and rollback, Founder signed approval, controlled deployment, exact SHA parity and live retest are **not** complete.
6. Nested Draft PR #10→#9→#8... has not been merged to `main`. This branch contains candidate source fixes only.

## Mandatory truth ledger
| Element | Status |
|---|---|
| Repository main (at initial inspection) | `04a5872e3e2e170ee400e7c607e39abd5484df65` |
| RC9 reviewed baseline | `eb11cebd866205543e11888e824cd2a75609c631` |
| RC10 exact corrective HEAD | To verify after final edit |
| Deployed Version | UNVERIFIED |
| DB Version | UNVERIFIED |
| Migration State | UNVERIFIED |
| Live Verification Status | NOT PERFORMED |

**Disposition:** source-level Draft corrective candidate, *not Staging-Accepted*, *not Live-Deployed*, and *not production Resolved*.