# File 18 — Nineteenth 40-control governing-plan/companion-owner audit (RC21)

Date: 22 October 2026 (Asia/Karachi). Documentary evidence: uploaded **File18 Marketplace Complete Master Plan 2026 v1.0**, consolidated Three Central Governing Plans, File17 Communication; CF05/CF06 conditional owners. Source evidence: exact RC20 successful Draft PR #22 HEAD `6af512e16eaa954ebba179408c873a71b273b7cf`.

## Frozen and separated source/staging/live truth
- Repository `main` exact HEAD: `04a5872e3e2e170ee400e7c607e39abd5484df65`, **not** corrective source and **not** proven deployed code.
- Source baseline: RC20 HEAD `6af512e16eaa954ebba179408c873a71b273b7cf`; new RC21 changes are stacked Draft only.
- Source plugin metadata `2.1.0`, source target DB `2.1.0`, source contract `1.2.0`. Live deployed version/hash, real DB schema and `wp_options`, migration state, server logs, active File00/17/19 providers and staging parity **unknown**.
- Root-cause investigation here is source-only; not a diagnosed live WordPress defect.

## Four new source defect groups, root cause and correction
1. **File00 deal permission skipped for cancellations and disputes**: `MKT_Commerce::transition_deal()` only called `MKT_Auth::can(mkt_buy|mkt_sell)` for participant transitions *except* `disputed` and `cancelled`. File18 plan requires verification/risk/guardian/current capability on **each mutation**, and File00 owns these decisions. Require the current positive owner permission for every participant transition, including both exceptional states. Actual-class regression exercises denial before any DB write.
2. **Direct deal transitions not atomic with event/audit**: only REST wrappers were transactional; the public PHP owner method could change a deal row and then fail immutable audit or File19 outbox. Add `MKT_DB::transaction` boundary inside the canonical owner method, retaining nested-transaction delegation for already-wrapped callers. Actual-class rollback regression crashes audit after update.
3. **Direct listing transitions not atomic with event/audit**: direct `MKT_Listings::transition` used an unguarded database update plus audit/outbox, whereas admin/REST wrapped it. Add canonical owner transaction and fail-closed error on commit/audit failure; verify rollback of listing status and version.
4. **Direct report/dispute moderation not atomic**: native `MKT_Moderation::transition_report` and `transition_dispute` mutated evidence/decisions then wrote audit/outbox without a transaction if called directly. Add owner transactions for both public methods; verify report and dispute rollback on mandatory audit outage.

## Targeted RC21 regressions
- `tests/test-rc21-deal-auth-audit-atomicity.php` calls real Commerce owner: File00 denies `cancelled` and `disputed`, no state write; authorized cancellation with injected Audit failure rolls back state.
- `tests/test-rc21-direct-owner-atomicity.php` calls real Listing and Moderation classes with injected Audit failure after status UPDATE; asserts rollback and explicit error codes for listing/report/dispute.
- Prior `test-rc18-core-publication-gates.php` fixture updated for newly required canonical listing owner `MKT_DB::transaction()`.
- Full aggregate: **49 named QA test stages** (previous 47), run as **40 whole-suite rounds for PHP 8.1 + 40 whole-suite rounds for PHP 8.3**. Exact final-head CI conclusion must be checked, not inferred.
- `18-marketplace-2.1.0-RC21.zip` candidate only; do not equate deterministic artifact with deployed plugin.

## Forty named requirement and owner checkpoints (source mapping; manual/live acceptance still pending)

| # | Governing requirement / owner | Canonical code boundary | Source evidence | Unverified operational acceptance |
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

**Interpretation:** Exactly 40 plan/owner checkpoints, including 30 File18 FR/NFR + 10 owner/conditional contracts (File00, 10, 17, 19, 20, 24, 25, 26, CF05, CF06). Automated 40× repetition is **not** 40 separate expert code reviews and is **not** proof of all 58 governing CV live criteria.

## Release blockers (cannot be patched away by conjecture)
- Open issue #17: no registered WPVibe WordPress connection; prior `https://sabrihomeopathy.com` attempts returned Cloudflare HTTP 530. Origin/1xxx code, Ray ID, DNS, deployed files, checksum and runtime logs must be retrieved through authorized host before any live diagnosis.
- Open issue #18: immutable hash-chain audit archive/checkpoint and legally approved hold-aware retention protocol missing. Unsafe pruning is forbidden.
- Actual staging MySQL concurrency, real File00/10/17/19/20/24/25/26 integration, conditional CF05/CF06 activation, backup/restore/rollback, two independent reviews, 58 central CV functional acceptances, Founder approval, package deployment parity and live re-test remain unverified.

## Mandatory final ledger
Repository HEAD / Deployed Version / DB Version / Migration State / Live Verification Status must be reported separately.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
