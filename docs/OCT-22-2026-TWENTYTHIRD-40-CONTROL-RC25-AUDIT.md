# File 18 Marketplace — 23rd 40-control plan/code and owner-boundary audit (RC25)

**Date:** 2026-10-22, Asia/Karachi. **Evidence freeze:** last successful RC24 Draft PR #26 exact SHA `b657524a5ee2bc13d09fc52eef305de4975af62f`. Independently verified GitHub source `main` `04a5872e3e2e170ee400e7c607e39abd5484df65`. RC25 is stacked DRAFT source work, not merged, not deployed.

**Primary attached plans examined:** `18-File-18-Marketplace-Complete-Master-Plan-2026-v1.0(1).docx` and `Sabri-Social-Homeopathy-Platform-Three-Central-Plans-Consolidated-Governing-Master-Plan-2026۔۔۔4۔۔۔۔۔.docx`. Direct source evidence: File18 master plan paras 68 actionable permission = capability+object state/purpose, 88 each transition current state/version/capability/ownership/policy atomic, 90 concurrency lost update and locks, 95 data+outbox in owner transaction, 98 timeout outcome cannot be guessed, 123 authenticated API/object authority. Central Master Plan v3.0 paras 16 requirement ID/canonical owner/effects/tests/rollback and 36–39 first and adversarial reviews, staging-acceptance gate. Companion owner plans File17 communication, File00 identity, File19 notifications, File24 security, File26 search, CF05/CF06 conditional boundaries.

## Five distinct source defects, proven at RC24 baseline and repaired in RC25
1. **Lock-order inversion on regulated evidence edit**: `MKT_Governance::save_evidence()` locked evidence and then updated listing while native `review_evidence()` (RC24) locks listing then evidence. Concurrent actor sessions could deadlock or starve. **Correction**: listing row locked `FOR UPDATE` first, evidence row locked second, preserving both owners' same order. No unsafe schema/index change.
2. **Current seller and publication-state not revalidated under owner lock**: prior code checked seller ownership/category/status only before transaction and relied on optimistic version UPDATE without status predicate. A concurrent publish/ownership change, including one without a matching version bump, could permit stale evidence mutation or wrong category validation. **Correction**: fresh listing JOIN seller locking read and strict current seller ID, allowed states, expected listing version; validate against locked current category; UPDATE CAS includes version AND status.
3. **Evidence update silently assumed present evidence version when omitted**: old `expected_evidence_version ?? row.version` made a missing version function as a valid fresh version, allowing a stale client to overwrite evidence/approved reviewer decision. New command requires explicit integer version equal to existing evidence version (or `0` for new record); missing or outdated version responds `mkt_stale_evidence_version` with 409. UI `marketplace-completion.js` already submits `expected_evidence_version`.

4. **Post-write evidence readback failures could silently COMMIT a partial command**: both `MKT_Governance::save_evidence()` and `review_evidence()` returned `self::evidence_record(..., true)` directly from inside the owner transaction. A missing/failed final SELECT yielded `null`, which `MKT_DB::transaction()` treats as a successful callback result and commits; only the outer return type could then throw. **Correction**: verify nonempty readback with exact new `version` and expected `status`; return `WP_Error('mkt_evidence_readback_failed')` *inside* the transaction to force ROLLBACK of listing/evidence, audit and outbox together. Proven with fake DB post-write readback outage tests in BOTH save and review actual owner classes.

5. **Recall state mutation had the same unverified final-readback commit**: `MKT_Governance::transition_recall()` formerly returned nullable `recall_record` after updating the canonical Recall, audit, outbox and possibly listing safety state. An empty final SELECT was treated as a successful owner transaction before the public method's return-type failure. **Correction**: require exact new Recall `version` and expected `status` from final canonical read, otherwise return `WP_Error('mkt_recall_readback_failed')` inside transaction. Actual-owner negative test `tests/test-rc25-recall-readback-atomicity.php` verifies normal activation and injected missing readback triggers full Recall/Audit/Event rollback.

**New actual-class negative regression:** `tests/test-rc25-evidence-save-locking.php`. Covers full valid draft edit (listing lock before evidence, exact increment, pending status, audit/outbox), omitted/old evidence version blocked before write, concurrent listing published despite stale preflight blocked, concurrent seller reassignment blocked, and category changed to regulated medicine despite old ordinary product preflight revalidated and rejected.

**QA updated:** 55 named static/source/negative contract stages; target 40 WHOLE suite rounds per PHP 8.1 and 8.3 = 80 runs. Test and release checksum must be verified against final corrective HEAD and not inherited from RC24. Candidate `18-marketplace-2.1.0-RC25.zip`, expected deterministic. Real WordPress/MySQL concurrency remains UNVERIFIED, regardless of fake-DB test successes.

## Exactly 40 named plan FR/NFR and owner controls

| # | Requirement / owner | Canonical code boundary | Source evidence | Operational acceptance remaining |
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

The table maps 30 File18 FR/NFR items and 10 cross-file owners: File00/10/17/19/20/24/25/26 + CF05/CF06. These are source-audit/control checks, not 40 distinct human reviews; deterministic 40 repeated suites are separate evidence and do not equal full 58 central CV staging acceptances.

## Release blockers and live first truth

- **#17**: WordPress connector WPVibe lists 0 registered sites, previously tried `https://sabrihomeopathy.com` responds Cloudflare HTTP 530. No reliable exact deployed plugin/source checksum/version, WP/PHP runtime config, DB tables/columns/wp_options/migration/version, error logs, Cloudflare 1xxx/Ray ID/origin. Cannot diagnose live bug; gather authorized host diagnostics, do NOT disable protections or assume bot cause.
- **#18**: approved signed immutable audit-chain checkpoint/archive, lawful hold-aware retention, privacy/legal/File24/Founder approvals missing. Do not prune hash-chain entries unsafely.
- Real staging MySQL & WordPress lock contention/deadlock testing, installed File00/10/17/19/20/24/25/26 adapters and CF05/CF06 activation, 58 central CV acceptance, backups/restore/rollback, independent reviews, Founder signoff and live parity/retest remain pending.

**Mandatory production ledger:** GitHub main HEAD `04a5872e3e2e170ee400e7c607e39abd5484df65`; RC25 corrective SHA is finalized only after tests; Deployed Version **UNVERIFIED** / DB Version **UNVERIFIED** / Migration State **UNVERIFIED** / Live Verification Status **NOT PERFORMED**.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
