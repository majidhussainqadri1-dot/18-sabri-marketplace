# File 18 — Fifth Fresh 40-Scope Plan/Source/Companion Audit (21 October 2026)

**Baseline examined:** `08feb707a80dda36202ca250f47a4c6bd84751b2` (RC6, Draft PR #6).  
**Corrective branch:** `fix/file18-fifth-forty-review-2026-10-21` (Draft PR #7 over PR #6; not main).  
**Candidate:** `18-marketplace-2.1.0-RC7.zip` — actual final SHA/checksum require final-head CI proof.

## Sources and precedence
1. Uploaded `18-File-18-Marketplace-Complete-Master-Plan-2026-v1.0(1).docx`: F18-FR-001…020, F18-NFR-001…010, ownership rules and final central reconciliation appendix.
2. Uploaded `Sabri-Social-Homeopathy-Platform-Three-Central-Plans-Consolidated-Governing-Master-Plan-2026…docx`: latest binding Founder/central ownership, free access, no donor advantage, safety and release gates.
3. Uploaded `17-File-17-Sabri-Communication-Network-Final-Harmonized-Master-Plan-2026…docx`: File17 retains all conversation and attachment truth.
4. Independently inspected File17 `main` `SN_REST::create_conversation`, `SN_Context_Adapters::attach_context`, `SN_Messages::messages_url` and File19 `SUN_Event_Validator`, `SUN_Producer_Registry`, `SUN_Notification_Service`.
5. Current GitHub exact candidate source. Github and live remain distinct realities.

## What the forty reviews prove
40 **separately enumerated source/plan inspection scopes** (20 FR + 10 NFR + 10 central/companion) found source implementation anchors. A source anchor is **not** evidence of complete functionality, security, performance, WordPress/MySQL concurrency, compatibility or live activation. The CI matrix separately repeats the entire static/contract/negative-test suite 40 times on each of PHP 8.1/8.3; the **last commit's** logs alone may confirm completion of those 80 executions.

| # | Governing ID | Verified source scope | Candidate code owner | Scope outcome |
|---:|---|---|---|---|
| 1 | F18-FR-001 | Governed product/service categories | `includes/class-mkt-policy.php` — evaluate_listing/jurisdiction | Anchor found; runtime not accepted |
| 2 | F18-FR-002 | Seller eligibility/guardian/risk | `includes/class-mkt-auth.php` — seller_eligibility/guardian | Anchor found; runtime not accepted |
| 3 | F18-FR-003 | Listing draft, required fields | `includes/class-mkt-listings.php` — create/title/price | Anchor found; runtime not accepted |
| 4 | F18-FR-004 | Media security and rights | `includes/class-mkt-integrations.php` — media_reference/scan_status/rights_status | Anchor found; runtime not accepted |
| 5 | F18-FR-005 | Review for regulated items | `includes/class-mkt-plan-completion.php` — publication_gate/evidence | Anchor found; runtime not accepted |
| 6 | F18-FR-006 | Versioned listing lifecycle | `includes/class-mkt-listings.php` — transition/expected_version | Anchor found; runtime not accepted |
| 7 | F18-FR-007 | Search filters/cursor | `includes/class-mkt-finalization.php` — search_array/cursor | Anchor found; runtime not accepted |
| 8 | F18-FR-008 | Public card/detail DTO | `includes/class-mkt-listings.php` — public_dto/price | Anchor found; runtime not accepted |
| 9 | F18-FR-009 | Save/share/report | `includes/class-mkt-listings.php` — save/transaction | Anchor found; runtime not accepted |
| 10 | F18-FR-010 | Product-linked File17 conversation | `includes/class-mkt-integrations.php` — native_file17_handoff; REST context owner | Anchor found; runtime not accepted |
| 11 | F18-FR-011 | Offer amount/expiry/replay safety | `includes/class-mkt-commerce.php` — create_offer/is_numeric/expires_hours | Anchor found; runtime not accepted |
| 12 | F18-FR-012 | Counteroffer CAS | `includes/class-mkt-commerce.php` — transition_offer/countered | Anchor found; runtime not accepted |
| 13 | F18-FR-013 | Deal acceptance and PK | `includes/class-mkt-commerce.php` — create_deal_from_offer/new_deal_id | Anchor found; runtime not accepted |
| 14 | F18-FR-014 | Hard zero commission | `includes/class-mkt-commerce.php` — commission_percent 0 | Anchor found; runtime not accepted |
| 15 | F18-FR-015 | Payment status (no custody) | `includes/class-mkt-events.php` — PaymentStatusChanged/payment_status | Anchor found; runtime not accepted |
| 16 | F18-FR-016 | Moderation and report | `includes/class-mkt-moderation.php` — create_report/readback_failed | Anchor found; runtime not accepted |
| 17 | F18-FR-017 | Structured dispute | `includes/class-mkt-moderation.php` — open_dispute/transition_dispute | Anchor found; runtime not accepted |
| 18 | F18-FR-018 | Unapproved promotion disabled | `includes/class-mkt-db.php` — promotions_enabled 0 | Anchor found; runtime not accepted |
| 19 | F18-FR-019 | Seller studio/insights | `includes/class-mkt-governance.php` — seller_studio/metrics | Anchor found; runtime not accepted |
| 20 | F18-FR-020 | Expiry and status reconciliation | `includes/class-mkt-maintenance.php` — expire_records/reconcile_sellers | Anchor found; runtime not accepted |
| 21 | F18-NFR-001 | Object/action authorization | `includes/class-mkt-auth.php` — can/own_listing | Anchor found; runtime not accepted |
| 22 | F18-NFR-002 | Privacy export/erasure | `includes/class-mkt-privacy.php` — export/erase | Anchor found; runtime not accepted |
| 23 | F18-NFR-003 | Idempotent replay durability | `includes/class-mkt-idempotency.php` — receipt_failed/saved CAS | Anchor found; runtime not accepted |
| 24 | F18-NFR-004 | Bounded query performance | `includes/class-mkt-finalization.php` — LIMIT/cursor | Anchor found; runtime not accepted |
| 25 | F18-NFR-005 | A11y/RTL route assets | `includes/class-mkt-routes.php` — enqueue_assets | Anchor found; runtime not accepted |
| 26 | F18-NFR-006 | Audit chain lock | `includes/class-mkt-audit.php` — named lock/entry_hash | Anchor found; runtime not accepted |
| 27 | F18-NFR-007 | Schema and migration | `includes/class-mkt-db.php` — required_columns/migrate_legacy_foundation | Anchor found; runtime not accepted |
| 28 | F18-NFR-008 | Maintenance/repair | `includes/class-mkt-maintenance.php` — process_communication_handoffs | Anchor found; runtime not accepted |
| 29 | F18-NFR-009 | Owner contract negotiation | `includes/class-mkt-integrations.php` — register_notification_producer/communication_status | Anchor found; runtime not accepted |
| 30 | F18-NFR-010 | Language/localization | `includes/class-mkt-finalization.php` — normalize_language | Anchor found; runtime not accepted |
| 31 | CEN/File00 | Identity claims are external | `includes/class-mkt-integrations.php` — identity_assertions/smc_get_marketplace_assertions | Anchor found; runtime not accepted |
| 32 | CEN/File17 | Conversation/context external owner | `includes/class-mkt-integrations.php` — rest_do_request/SN_Messages::messages_url | Anchor found; runtime not accepted |
| 33 | CEN/File19 | Notification projection external | `includes/class-mkt-integrations.php` — sun_ingest_domain_event/producer | Anchor found; runtime not accepted |
| 34 | CEN/File20 | Single shell/navigation owner | `includes/class-mkt-integrations.php` — shell_navigation | Anchor found; runtime not accepted |
| 35 | CEN/File24 | Assurance evidence remains derivative | `includes/class-mkt-integrations.php` — assurance_controls | Anchor found; runtime not accepted |
| 36 | CEN/File26 | Search index downstream only | `includes/class-mkt-integrations.php` — search_provider | Anchor found; runtime not accepted |
| 37 | CEN/Finance | Single-free/zero platform commission | `includes/class-mkt-commerce.php` — commission_percent 0 | Anchor found; runtime not accepted |
| 38 | CEN/Security | Browser CSRF, application auth | `includes/class-mkt-rest-security.php` — nonce/application-password | Anchor found; runtime not accepted |
| 39 | CEN/Privacy | Private routes no-store/noindex | `includes/class-mkt-routes.php` — DONOTCACHEPAGE/noindex | Anchor found; runtime not accepted |
| 40 | CEN/Atomicity | Owner state, audit and event transaction | `includes/class-mkt-rest.php` — atomic_owner_commands/transaction | Anchor found; runtime not accepted |

## Newly confirmed defects, source fixes, test evidence
**F18-R5-01 — File17 hard integration mismatch (FIXED IN REPOSITORY; LIVE UNVERIFIED).** File18 historically searched `sabri_network_open_context_conversation`, `sn_open_context_conversation` or `sabri_communication_open_context_conversation`. The inspected File17 `main` supplied canonical REST create-direct and attach-context endpoints, not one of these provider bindings. Implemented a REST-owner fallback with actor equality, live active listing and seller eligibility checks, a File18 `sn_network_context_authorize` policy and public-only context projection; routing uses the File17 `SN_Messages::messages_url`. File18 does not write to File17 tables. If context attach fails after direct conversation creation, the response fails closed and a retry reuses the File17 direct conversation; this remains a non-atomic cross-module saga requiring staging verification.

**F18-R5-02 — False notification dependency health (FIXED IN REPOSITORY; LIVE UNVERIFIED).** `MKT_Integrations::status()` did not include File19's actual SUN native producer/ingest API in its availability predicate. Corrected and added simulated native-provider health assertion.

**F18-R5-03 — Non-durable idempotency response misreported as successful (FIXED IN REPOSITORY; LIVE UNVERIFIED).** `MKT_Idempotency::run` ignored return values of the completed/failed receipt UPDATE, allowing a mutation's apparent success while replay evidence was not safely stored. Now checks update count with processing CAS, returning safe 503 when persistence fails; does not blindly move uncertain owner mutation to a reusable failed state. **Remaining:** a 24-hour stale processing lease and mutations already committed before receipt failure require live reconciliation policy; this patch does not prove exactly-once semantics.

**New tests:** `test-file17-native-context-bridge.php` exercises actual File18 methods against simulated File17 owner REST responses, context authorization and provider outage; `test-idempotency-durable-receipt.php` injects UPDATE failure into the real File18 idempotency class. Both are included in aggregate `qa-static.sh` and direct PHP 8.1/8.3 CI steps. Existing File19 native contract/acknowledgement tests preserved. They are **simulations**, not real installed dual-plugin tests.

## Unresolved cross-file and production blockers
- File17 actual currently **deployed** functions/routes/version and existing conversation migration/state **unverified**. Need native File17+File18 multi-user staging acceptance, context reauthorization after listing takedown, duplicate handoffs, different customer/actor, temporary File17 downtime and WordPress REST nonce/auth.
- File19 native registration/event envelope remains stub-tested only, with actual File19 live producer, preferences, retry/digest, delivery and privacy acceptance pending.
- File00 identity/age/guardian/suspension, File20 shell, File24 controls, File25 RTL/keyboard/zoom and File26 indexing/deletion are not full real integration tests.
- 58 central-plan CV requirements are governed by owner-by-owner RTM. Forty source scopes do not certify all 58 independent acceptance results.
- Idempotent mutation/receipt partial-commit recovery, DB transaction concurrency, schema migration/rollback, real payment/direct deal, regulated-product review, rights/erasure and real accessibility need staging or owner-specific tests.
- Live WordPress connection earlier returned Cloudflare HTTP 530 and no WPVibe site was registered; **failure origin not established**. Do not disable WAF, plugins or security features to force a connection.
- Exact deployed version/checksum, runtime config, wp_options, DB/schema/migration state, active dependencies and logs are **UNVERIFIED**; therefore source-based diagnosis is **provisional**.
- Release gates remain draft, require independent last-head CI+reproducible checksum, two fresh code reviews after final modification, Hostinger-equivalent staging, verified backup/restore, migration rehearsal, rollback, Founder approval, controlled rollout, live re-test and parity evidence.

## Incident truth ledger
| Required field | Status |
|---|---|
| GitHub repository `main` HEAD | `04a5872e3e2e170ee400e7c607e39abd5484df65` (observed before this corrective batch) |
| Corrective code HEAD | Resolve exactly from PR #7 after final documentation/test changes |
| Intended candidate | 2.1.0-RC7, not RC6 |
| Deployed Version | **UNVERIFIED** |
| DB Version | **UNVERIFIED** |
| Migration State | **UNVERIFIED** |
| Live Verification Status | **NOT PERFORMED** |
| Release decision | **DRAFT, NOT AUTHORIZED FOR PRODUCTION** |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
