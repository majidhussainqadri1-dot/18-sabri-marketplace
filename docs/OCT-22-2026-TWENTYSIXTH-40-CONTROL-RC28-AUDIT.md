# File18 RC28 — Twenty-sixth 40-control Source, Central and Companion-Owner Audit

Date: 22 October 2026, Pakistan. **Exact baseline:** successful RC27 Draft PR #29 HEAD `cb5240af983e5d61ce20eaeb9dea9bda76837ea9` (PHP 8.1 and PHP 8.3 40/40 complete 57-stage aggregate suites, CI `39740471492`). GitHub source `main` independent `04a5872e3e2e170ee400e7c607e39abd5484df65`. Neither constitutes deployed source evidence.

**Attached governing originals inspected:** File18 Marketplace Complete Master Plan 2026 v1.0; Three Central Plans Consolidated Governing Master Plan v3.0; File17 Communication Master Plan, plus conditional CF05 Analytics and CF06 Localization plans. The source explicitly demands canonical owner state + audit/outbox transactions (File18 para 29, 54–58, 88–98; central para 16, 33, 36–40), direct/native Owner authorization and documented rollback. File18 owns Listings; File17 communications and File19 notifications are outside its canonical data ownership.

## Four newly evidenced source defect groups corrected in RC28

1. **Direct Listing creation non-atomic:** `MKT_Listings::create()` previously inserted a draft, wrote mandatory Audit and File19 outbox **without wrapping the owner command in MKT_DB::transaction**. An injected Audit/Outbox failure could strand a created draft or seller linkage and report a false failure. The insert ID and returned Listing could also be absent/wrong. RC28 wraps the canonical PHP create (including nested seller preparation) in an owner transaction and checks inserted PK, Listing UUID, seller, `draft`, version 1 before commit. On post-write readback/audit/outbox failure, full rollback.
2. **Listing edit non-atomic and current state CAS incomplete:** `update()` updated fields before writing audit without owner transaction, returned unchecked private DTO after writes, and compared only `id/version` (not source `status`) on SQL UPDATE. RC28 wraps native edit in owner transaction, requires version + source state on CAS, and verifies final row identity, seller, effective `draft` reset when applicable, and version+1 before commit. No stale edit can masquerade as success.
3. **Listing Review submission non-atomic:** `submit()` moved a draft to `review`, recorded Audit and outbox outside transaction, then returned an unchecked private DTO. RC28 wraps owner command in transaction and requires final row identity, seller, `review`, expected version+1. Mandatory audit/outbox failures and missing/wrong readback trigger rollback.
4. **Listing status transition had unchecked final readback:** `transition_owner()` already used owner transaction but after status/audit/outbox returned `private_dto(self::get(..., true))` without verifying a record exists or matches final status/version/ID. RC28 rejects missing/wrong row identity/state/version inside transaction before success and rolls back Listing + File19 event + audit.

**Negative actual PHP owner-class test:** `tests/test-rc28-listing-atomic-receipts.php`. Executes create/update/submit/transition on fake transactional WordPress DB with success, audit crash, missing readback, wrong identity, wrong status, wrong version. Asserts rollback of Listings, seller owner projection, audit and outbox. These source tests do not prove real installed WordPress/MySQL lock contention, native UI or external File19 delivery.

**QA target**: 58 complete named stages ×40 aggregate repeats per PHP 8.1 and 8.3, 80 rounds total when final exact-head CI green. Deterministic candidate `18-marketplace-2.1.0-RC28.zip`. Do not infer final tests or hash from older run.

## Exactly 40 cross-plan / cross-file requirements
| # | Governing requirement / owner | Implementation | Source signal | External acceptance missing |
|---|---|---|---|---|
| 1 | F18-FR-001 Category/policy taxonomy | `MKT_Policy` | `mkt_policies`, seeded category/prohibited rules | policy/source tests + staging categories |
| 2 | F18-FR-002 Seller eligibility | `MKT_Auth`, File 00 adapter | identity assertions, seller reconciliation | approved/pending/suspended/minor/risk matrix |
| 3 | F18-FR-003 Listing draft | `MKT_Listings` | create/update, normalized fields, declarations | REST + real seller journey |
| 4 | F18-FR-004 Listing media | central media adapter | `mkt_media_refs`, rights/scan publish gate | provider contract + malicious upload tests |
| 5 | F18-FR-005 Listing review | `MKT_Policy`, moderators | review state, human holds, reasons | regulated/claim policy tests |
| 6 | F18-FR-006 Listing lifecycle | state machine | versioned atomic transitions | positive/negative/stale-state tests |
| 7 | F18-FR-007 Search/facets | `MKT_Listings::search` | bounded cursor, indexed filters, File 26 provider | query/load/index removal tests |
| 8 | F18-FR-008 Cards/detail | public DTO/templates | exact price/currency/status/seller/update labels | UI/SEO/accessibility matrix |
| 9 | F18-FR-009 Save/share/report | listings/moderation | private saves, public URL, structured report | auth/IDOR/report abuse tests |
| 10 | F18-FR-010 Product-linked chat | File 17 adapter | context card, no chat table | real File 17 one-conversation test |
| 11 | F18-FR-011 Offer | `MKT_Commerce` | amount/currency/terms/expiry/idempotency | duplicate/replay/eligibility tests |
| 12 | F18-FR-012 Negotiation | offer state machine | counter/accept/decline/withdraw/version | actor and stale-offer tests |
| 13 | F18-FR-013 Deal lifecycle | `MKT_Commerce` | atomic accepted snapshot, direct status | concurrent accept + participant tests |
| 14 | F18-FR-014 Zero commission | contracts/code/schema | constant 0, no commission column/calculation | static invariant + UI/terms review |
| 15 | F18-FR-015 Payment/manual proof bridge | deal status + external event | declared/manual/provider statuses, no escrow claim | verified event and outage tests |
| 16 | F18-FR-016 Moderation | `MKT_Moderation` | prohibited rules, restrict/remove/appeal | illegal/fraud/counterfeit/false-claim tests |
| 17 | F18-FR-017 Disputes | dispute state machine | purpose-limited evidence refs/decision | participant/reviewer/access/retention tests |
| 18 | F18-FR-018 Promotions | setting/policy | disabled by default; labeled field only | no hidden ranking test |
| 19 | F18-FR-019 Seller insights | aggregate metrics | daily counts only, no buyer identity leakage | privacy threshold/manual dashboard gate |
| 20 | F18-FR-020 Expiry/reconciliation | maintenance/events | hourly/daily expiry, seller suspension, events | delayed cron and stale index tests |
| 21 | F18-NFR-001 Object/field authorization | `MKT_Auth` + native services | current claims, ownership/state checks | full IDOR matrix |
| 22 | F18-NFR-002 Privacy lifecycle | `MKT_Privacy`, retention | exporter/eraser/no-store/redaction | rights request and deletion propagation |
| 23 | F18-NFR-003 Reliability | DB/outbox/inbox | transactions, retry/dead-letter/reconciliation | failure injection and duplicate delivery |
| 24 | F18-NFR-004 Performance | indexed schema/search | bounded limits/cursors/conditional assets | p75/p95 staging load evidence |
| 25 | F18-NFR-005 Accessibility | templates/CSS/JS | keyboard, focus, semantics, RTL, reduced motion | WCAG 2.2 AA objective review |
| 26 | F18-NFR-006 Observability | audit/status/metrics | trace IDs, System Check, aggregate metrics | alert/runbook drill |
| 27 | F18-NFR-007 Migration/rollback | `MKT_DB`, handoff ledger | idempotent import, no destructive delete | fresh/upgrade/restore/rollback rehearsal |
| 28 | F18-NFR-008 Operability | admin/maintenance | safe mode, system check, repair dry-run | operator runbook acceptance |
| 29 | F18-NFR-009 Compatibility | runtime metadata/CI | PHP 8.1/8.3, WP project baseline | Hostinger staging matrix |
| 30 | F18-NFR-010 Localization | UI/text domain/CSS | American English base, RTL logical layout | Urdu/Arabic/date/currency tests |
| 31 | File00 | Verified adult/guardian/risk scoped seller and direct-deal permissions | MKT_Auth + MKT_Integrations | Live/deployed assertions and role UAT missing |
| 32 | File10 | Media rights, scanning and reference handoff | MKT_Listings media adapter | Real provider signature/rights integration pending |
| 33 | File17 | Product-context chat; no duplicate communication owner | MKT_Integrations | Actual File17 owner and legacy handoff pending |
| 34 | File19 | Offer/deal/report notification ACK and delivery | MKT_Events | Real delivery acknowledgement pending |
| 35 | File20 | Shell, navigation, route layout, Safe Mode | MKT_Routes + integration hooks | Responsive real shell UAT pending |
| 36 | File24 | Security/privacy assurance, audit and retention | MKT_Release_Gates + MKT_Governance | Immutable archive protocol and security approval pending |
| 37 | File25 | Visual tokens, RTL, accessibility | CSS/templates | Visual/assistive tech evidence pending |
| 38 | File26 | Safe global search, no source-of-truth duplication | MKT_Listings search_provider_query | Index invalidation and real search provider pending |
| 39 | CF05 | Conditional derivatives, not canonical analytics owner | MKT_Maintenance aggregate_metrics | Activation not authorized; no warehouse claim |
| 40 | CF06 | Conditional translations, not current File18 language owner | MKT_Plan_Completion | Activation not authorized; locale QA remains |

This is 30 File18 FR/NFR requirements plus 10 other-file/conditional owner dependencies (File00/10/17/19/20/24/25/26, CF05 and CF06), **not** 40 independent human audits or 58 actual CV staging acceptance checks.

## External blockers / no invented live truth
- #17 WPVibe WordPress connector has no registered sites; `sabrihomeopathy.com` yielded Cloudflare HTTP 530. Host-specific 1xxx code, Ray ID, origin DNS/health logs, exact deployed plugin ZIP/source SHA/version, WordPress runtime, DB tables/columns/rows/schema/`wp_options`/migration state/error logs not yet verified. Cloudflare 530 does not establish bot/rule failure; do not weaken security or assert live fix.
- #18 Immutable Audit hash-chain authorized archive/checkpoint, legal-hold retention rules and File24/legal/security/Founder approval are still open. Never prune unaudited facts.
- Other required independent acceptance: genuine WordPress/MySQL staging concurrency and File00/10/17/19/20/24/25/26 integrations, conditional CF05/06 activation, 58 central CV, backup/restore/rollback proof, 2 independent reviews, Founder production authorization, deployment artifact parity, live retest.

**Truth ledger:** GitHub source main HEAD `04a5872e3e2e170ee400e7c607e39abd5484df65`; RC28 candidate head to be frozen after QA. Deployed Version UNVERIFIED; DB Version UNVERIFIED; Migration State UNVERIFIED; Live Verification Status NOT PERFORMED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
