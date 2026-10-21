# File 18 Marketplace — Seventh Separate 40-Scope Review (RC9 Candidate)
**Review date:** 2026-10-21 (PKT). **Source baseline:** exact last green RC8 `5c21e35d944210e2706480154632eb74ce1ac2e2`.
**Correction branch:** `fix/file18-seventh-forty-review-2026-10-21`, Draft PR #9 stacked on Draft PR #8. **Do not confuse this branch with main or deployed code.**

## Governing sources and limits
The uploaded **File18 Marketplace Complete Master Plan v1.0**, **Three Central Plans Consolidated Governing Master Plan**, and **File17 Communication Plan**, plus File10/19/20/24/25/26 owner rules, were reviewed against the repository at the baseline above. The plans require one canonical owner per fact, File17 conversations, File19 notification delivery, File10 media ownership, zero commission, explicit safety/retention policy, audit proof, secure replay, and staging-before-live release. CF02 Support and CF03 financial source ownership remain **conditional**; File18 may not silently instantiate duplicate finance/support authorities.

The table records **40 different requirement-based source inspections**—not forty independent complete code audits and not acceptance of all 58 mapped central CV requirements. Existence of a source anchor is evidence of some implementation, **not** assurance that every clause is complete or that it works on WordPress/MySQL/staging/live. Source anchor automated checks are in `tests/test-seventh-40-plan-anchors.php`.

| # | Governing scope | Item checked | Source file | Finding |
|---:|---|---|---|---|
| 1 | F18-FR-001 | Category and prohibited-good governance | `includes/class-mkt-policy.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 2 | F18-FR-002 | Seller verification/guardian/current eligibility | `includes/class-mkt-listings.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 3 | F18-FR-003 | Listing creation/validation/draft | `includes/class-mkt-listings.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 4 | F18-FR-004 | File10 attachment, rights, scan lifecycle | `includes/class-mkt-listings.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 5 | F18-FR-005 | Regulated evidence and reviewer | `includes/class-mkt-governance.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 6 | F18-FR-006 | Listing state transitions/CAS | `includes/class-mkt-listings.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 7 | F18-FR-007 | Public search/filter/cursor | `includes/class-mkt-finalization.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 8 | F18-FR-008 | Public listing DTO | `includes/class-mkt-listings.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 9 | F18-FR-009 | Save, share, report | `includes/class-mkt-listings.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 10 | F18-FR-010 | File17 conversation context | `includes/class-mkt-integrations.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 11 | F18-FR-011 | Offer amount/expiry | `includes/class-mkt-commerce.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 12 | F18-FR-012 | Counteroffer and negotiation | `includes/class-mkt-commerce.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 13 | F18-FR-013 | Accepted Deal immutable identity | `includes/class-mkt-commerce.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 14 | F18-FR-014 | Zero platform commission | `includes/class-mkt-commerce.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 15 | F18-FR-015 | Payment-status projection | `includes/class-mkt-events.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 16 | F18-FR-016 | Reports and moderation | `includes/class-mkt-moderation.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 17 | F18-FR-017 | Dispute case/appeal | `includes/class-mkt-moderation.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 18 | F18-FR-018 | Unapproved promotions disabled | `includes/class-mkt-db.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 19 | F18-FR-019 | Seller studio metrics | `includes/class-mkt-governance.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 20 | F18-FR-020 | Expiry/reconciliation | `includes/class-mkt-maintenance.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 21 | F18-NFR-001 | Object+field authorization | `includes/class-mkt-auth.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 22 | F18-NFR-002 | Privacy export/erasure/legal hold | `includes/class-mkt-privacy.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 23 | F18-NFR-003 | Inbox, outbox, idempotency and retry | `includes/class-mkt-events.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 24 | F18-NFR-004 | Performance and bounded pagination | `includes/class-mkt-finalization.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 25 | F18-NFR-005 | RTL, keyboard, accessible UI source | `includes/class-mkt-routes.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 26 | F18-NFR-006 | Tamper-evident audit | `includes/class-mkt-audit.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 27 | F18-NFR-007 | DB schema upgrade/migration | `includes/class-mkt-db.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 28 | F18-NFR-008 | Maintenance, recovery and System Check | `includes/class-mkt-maintenance.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 29 | F18-NFR-009 | Versioned external contracts | `includes/class-mkt-integrations.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 30 | F18-NFR-010 | Locale and currency | `includes/class-mkt-finalization.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 31 | CENTRAL-OWN-01 | File00 identity is canonical | `includes/class-mkt-integrations.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 32 | CENTRAL-OWN-02 | File10 owns media | `includes/class-mkt-listings.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 33 | CENTRAL-OWN-03 | File17 owns messages | `includes/class-mkt-integrations.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 34 | CENTRAL-OWN-04 | File19 owns notification delivery | `includes/class-mkt-integrations.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 35 | CENTRAL-OWN-05 | File20 owns global shell | `includes/class-mkt-integrations.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 36 | CENTRAL-OWN-06 | File24 owns security assurance | `includes/class-mkt-integrations.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 37 | CENTRAL-OWN-07 | File25 design/accessibility tokens | `includes/class-mkt-routes.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 38 | CENTRAL-OWN-08 | File26 global discovery index owner | `includes/class-mkt-integrations.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 39 | CENTRAL-OWN-09 | Legal hold releases and privacy owner | `includes/class-mkt-governance.php` | Relevant source anchor found; complete runtime acceptance unverified |
| 40 | CENTRAL-OWN-10 | Marketplace recall/takedown owner | `includes/class-mkt-governance.php` | Relevant source anchor found; complete runtime acceptance unverified |

## New source-confirmed defect classes corrected this round
- **R7-01 — Recall record not atomic with mandatory audit.** `MKT_Governance::create_recall` inserted a recall draft independently of `MKT_Audit::record`. Failure could leave an unaudited governance record. Corrected with a transaction, failed-write checking and matching canonical readback; injected audit and write failures tested.
- **R7-02 — Legal retention hold create/release not atomic with mandatory audit.** `MKT_Governance::create_hold` and `release_hold` previously committed hold state independently of audit. Corrected transaction boundaries, error propagation and rollback before success; injected audit failure proves legal hold remains active when release audit is unavailable.
- **R7-03 — Seller identity projection write/readback falsely trusted.** `MKT_Listings::ensure_seller` used mutable `insert_id` without checking successful INSERT and ignored UPDATE outcome when refreshing File00 eligibility. Corrected with seller insert/result validation, immediate ID/readback, version-CAS refresh and immutable created-seller audit inside a transaction. Rejects stale/failed writes. Identity truth remains File00 owned.

## Test evidence and known limitations
- `test-governance-recall-hold-atomicity.php` executes real File18 governance methods against simulated WordPress database failures, checks rollback and committed audits.
- `test-seller-atomicity.php` executes the real File18 seller method against simulated stale insert ID, failed seller CAS refresh, missing readback and failed audit.
- `test-seventh-40-plan-anchors.php` makes 40 explicitly named requirement-to-source code assertions, and prints a scope count.
- `qa-static.sh` includes all tests; PHP 8.1/8.3 GitHub CI is configured for 40 repeated *full* static/regression executions per PHP member, with separate direct gate steps.
- Automated tests are not real MySQL concurrency, WP rest authentication, actual File00 identity, cross-module File10/17/19 handoffs, browser accessibility, upgrade/rollback or live proof.

## Unresolved issues and blockers requiring staging/live
1. **Retention hold duplicate active race:** pre-check and nonunique `target_status` index could allow overlapping active holds in concurrent MySQL sessions; no concurrent verified acceptance. Treat as unresolved safety acceptance, not proven fixed.
2. **Governance version / WordPress/MySQL:** real migration and already deployed schema table state missing; verification cannot derive from source `dbDelta`.
3. **Release parity/Cloudflare:** connector site inventory still requires authorization and live attempts have returned HTTP 530; DNS/origin/security root cause unverified. Never bypass protection to force tests.
4. **The other owner modules:** File00,10,17,19,20,24,25,26 real versions and integration behavior not established. Privacy erasure with hold, recall propagation to buyers, payment/offer negative paths, and hard-blocked seller status require end-to-end user-role and concurrency tests.
5. **Recovery/rollback:** backup/restore, migration rehearsal, host-equivalent staging and verified revert still need evidence. No release without two fresh reviews after final code edit, zero unresolved release-blocking defects or Founder-approved risk acceptance.
6. **GitHub PR stacking:** PR #9 targets #8, which remains Draft and not merged with main. GitHub CI green would prove repository candidate quality only.

## Mandatory live incident truth ledger
| Element | Evidence |
|---|---|
| Repository `main` HEAD | `04a5872e3e2e170ee400e7c607e39abd5484df65` at review start |
| Corrective baseline | `5c21e35d944210e2706480154632eb74ce1ac2e2` |
| Final PR9 HEAD / package SHA | Verify from the final exact-HEAD CI after all edits |
| Candidate name | `18-marketplace-2.1.0-RC9.zip` |
| Deployed Version | **Unverified** |
| DB Version | **Unverified** |
| Migration State | **Unverified** |
| Live Verification Status | **Not performed** |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

**Disposition:** Source-corrected DRAFT candidate pending independent final-head QA evidence, real owner staging acceptance and explicit Founder release decision.