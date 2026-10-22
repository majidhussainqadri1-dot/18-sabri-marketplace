# File 18 — Twenty-second 40-control plan/source and owner-boundary audit (RC24)

**Date:** 22 October 2026, Pakistan. **Source baseline:** last successful RC23 Draft PR #25 exact HEAD `a3f973e4b07ba28b96501c7ff2c40effebb55fbd`; GitHub `main` separately `04a5872e3e2e170ee400e7c607e39abd5484df65`. No verified production or staging deployment.

**Primary plan:** user-attached `18-File-18-Marketplace-Complete-Master-Plan-2026-v1.0(1).docx` and consolidated Three Central Governing Plans, plus File17 Communication, File00 identity, File19 notifications, File24 safety, File26 search, and CF05/CF06 conditional owner plans. The governing File18 source specifically requires: one canonical owner (§2, paragraphs 47/59–64); actor/object/safety at EVERY mutation (paragraphs 63/74–77/99); owner data+outbox atomic (§6.3 paragraph 106); no externally implied acceptance without deployment tests (paragraphs 5/27/39/40); medical claims/regulated evidence and privacy safety (§9).

## Three newly proved SOURCE defect groups and in-repo corrections

1. **Regulated evidence self-approval bypass in native Governance API.** `MKT_Governance::review_evidence()` accepted `mkt_moderate` and actor matching `get_current_user_id()`, but never denied an actor who is simultaneously listing seller or creator. The strict REST override `MKT_Release_Gates::review_evidence_atomic()` already explicitly denied self-review. RC24 enforces the same prohibition both before mutation and against the locked fresh listing owner, preventing actor-seller changes between checks. Scope: File18 FR-005/016 and File00/24 identity/safety.
2. **Rejected regulated evidence left an active product published via native owner.** Direct `MKT_Governance::review_evidence()` previously changed only `listing_evidence.status` while leaving `listings.status=active`; the strict REST route paused active listings. RC24 pauses the listing with optimistic version CAS **in the same owner transaction** and emits BOTH evidence and listing status File19 events, with seller notification recipient and audited reason. Scope: File18 FR-005/006/020, File19, File24.
3. **Out-of-transaction evidence/listing prechecks allowed stale decisions.** Pre-existing Governance method verified both rows before beginning its transaction, then updated evidence without a fresh locking read. RC24 follows `listing FOR UPDATE` → `evidence FOR UPDATE`, rechecks current seller and evidence version, validates fresh evidence on approval, and fails closed on concurrent change or failed listing pause. Scope: NFR-003/007, privacy/safety owner.

**New actual-class regression:** `tests/test-rc24-native-evidence-review-safety.php`: independent seller self-review rejection, seller identity changed between preflight/locking read, an active medicine listing MUST pause when its evidence is rejected, two local outbox facts and audit truth, and rollback with simulated SQL pause failure. WordPress + real MySQL concurrent transactions are a separate **unverified staging gate**, not proven by fake-DB tests.

## Exactly 40 governing control mappings (not 40 independent manual reviews)

| # | Governing requirement / owner | Canonical implementation | Code evidence | Further acceptance |
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

The review covers 30 File18 FR/NFR numbered controls and 10 companion/conditional boundaries: File00/10/17/19/20/24/25/26/CF05/CF06. It does not certify the 58 central CV runtime criteria, live authorization, translations or actual external service delivery.

## QA/release truth discipline

- Source QA now has **53 named aggregate static/contract/negative stages** and the GitHub quality workflow repeats the WHOLE suite 40 times independently on PHP 8.1 and PHP 8.3. Final exact-head CI conclusions and hash must be verified, not predicted.
- Deterministic candidate name: `18-marketplace-2.1.0-RC24.zip`; plugin source version 2.1.0, target core schema source 2.1.0, governance schema source 1.0.0; none proves the actual installed files.
- Source corrections are not production changes. No merge, deploy, or live-resolved claim absent File18/central approval gates.

## Release blockers / live-reality freeze

1. **#17**: WPVibe currently lists no connected sites; latest attempts on `https://sabrihomeopathy.com` produced Cloudflare HTTP 530. Missing owner-authorized 1xxx subcode, Ray ID, DNS/origin logs, active plugin version/hash, WP/PHP environment and deployed runtime code, actual database tables/columns/rows/options and migration markers. Do not disable protection or infer bot firewall from 530.
2. **#18**: Immutable audit-chain archive/checkpoint legal hold-aware retention and signed security/legal/Founder protocol not approved; no unsafe destructive pruning.
3. WordPress/MySQL staging contention, File00/10/17/19/20/24/25/26 actual adapters, CF05/CF06 activation, backup/restore/rollback, 58 central CV checks, two independent reviews and Founder production signoff all remain **unverified**.

**Truth ledger:** Repository main HEAD `04a5872e3e2e170ee400e7c607e39abd5484df65`; corrective RC24 candidate HEAD must be frozen after all changes; Deployed Version = UNVERIFIED; DB Version = UNVERIFIED; Migration State = UNVERIFIED; Live Verification Status = NOT PERFORMED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
