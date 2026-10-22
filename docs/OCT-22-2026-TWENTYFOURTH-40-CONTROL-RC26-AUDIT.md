# File 18 RC26 — Twenty-fourth 40-control plan / owner-boundary source audit

**Date:** 22 October 2026 (Pakistan). **Corrective frozen baseline:** RC25 Draft PR #27, final verified HEAD `aabd8a77a9593404578646e7851c13b66a0005e1` (GitHub Actions `39731696285`, dual PHP 40/40 × 55 stages, deterministic candidate hash `c8e65e94583f1cd7d66523d4fada31af6dff0ebb717dedd8a98b699d6683eda8`). GitHub source `main` independently `04a5872e3e2e170ee400e7c607e39abd5484df65`. Neither establishes production deployments.

**Primary original attached sources directly examined:** `18-File-18-Marketplace-Complete-Master-Plan-2026-v1.0(1).docx`; consolidated Three Central Governing Master Plans; companion File17 Communication; CF05 Analytics; CF06 Localization. Source File18 paras 88/90 require state, expected version, actor, atomicity and race safety, paras 95/98 require atomic owner+outbox and authoritative outcome over guess, paras 103/107/111 require trace and legal hold, paras 123–127 require authenticated mutating APIs and safe errors/reliable outbox. Central Plan paras 16/36/38 require canonical owner, traceability, independently repeated adversarial review and supporting deployment evidence. File18 report and dispute facts are owned by File18, with File19 notification delivery and File17 participant conversation strictly separate.

## Four new confirmed SOURCE defect groups / corrections (not production bug claims)

1. **New Report receipt lacked *state/version/reporter* confirmation**: `MKT_Moderation::create_report()` checked only that final SQL readback row existed and its public ID matched the inserted UUID. A wrongly projected or stale final row still allowed a successful transactional receipt. RC26 requires exact submitted status, version 1, matching reporter and UUID before committing report, audit and outbox.
2. **Report moderation transition had unchecked, nullable final readback**: `transition_report_owner()` mutated report state, recorded audit and enqueued event, then called `report_dto($wpdb->get_row(...))`. Null could throw in the DTO after prior writes; wrong/stale row could be accepted as a successful receipt. RC26 enforces correct public ID, expected version+1, destination status; returns specific `mkt_report_transition_readback_failed` within transaction to force rollback.
3. **New Dispute after deal transition had unchecked, nullable final readback**: `open_dispute()` created a dispute, changed the deal through canonical Commerce, wrote audit/outbox and returned `dispute_dto` of an unchecked SQL row. RC26 requires new dispute UUID, deal ID, opening actor, version 1 and opened status; wrong/missing receipt fails the same outer owner transaction and rolls back the dispute/deal side effect.
4. **Dispute moderation transition had unchecked, nullable final readback**: `transition_dispute_owner()` similarly could accept the wrong dispute, stale status or missing result after an update/outbox write. RC26 confirms public ID, version+1 and requested status, returns specific `mkt_dispute_transition_readback_failed` before COMMIT if not verified.

New `tests/test-rc26-moderation-readback.php`: directly exercises all 4 native Moderation owner entrypoints with injected missing/incorrect status/version/identity readback and success controls. Ensures state, mandatory audit and event outbox revert on unsafe readback. These are *deterministic simulated database contract tests*, not actual MySQL/WordPress race tests.

Full static/contract QA now **56 named stages** × **40 complete passes under PHP 8.1 and 40 under PHP 8.3** = 80 repeated suites when FINAL exact-head CI completes; do not report them successful in advance. Candidate `18-marketplace-2.1.0-RC26.zip`. Plugin source version `2.1.0` != proven live plugin version.

## Forty named requirements & cross-owner checkpoints

| # | Requirement/owner | Canonical code boundary | Source evidence | Still required outside repository |
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

Register represents the original 30 File18 FR/NFR and 10 dependent-owner controls File00/10/17/19/20/24/25/26 and conditional CF05/CF06; 40 checkpoints are not 40 independent humans testing every production workflow and not all 58 central CV live/staging acceptance gates.

## Release blockers and exact source vs deployed truth

**#17** WPVibe WordPress connector has no site registered and previously returned Cloudflare HTTP 530 from `https://sabrihomeopathy.com`. No authorized Cloudflare 1xxx/Ray ID/origin/DNS/host cause, actual deployed plugin bytes/version/hash, runtime PHP/WordPress configuration/logs, DB tables/columns/rows, `wp_options`, migration state or staging parity. No guess that firewall/bot rules caused 530 and no unauthorized security bypass.

**#18** Immutable audit log hash-chain approved retention/checkpoint/archive with legal hold-aware deletion, File24 legal/security and Founder signoff pending. Do not prune without approved evidence.

Real WordPress/MySQL staging concurrency and installed File00/10/17/19/20/24/25/26 contracts, conditional CF05/06, 58 central CV checks, backup/restore/rollback, independent code/security reviews, Founder deployment approval, exact artifact parity and live re-test are NOT established.

**Required ledger:** `Repository main HEAD = 04a5872e3e2e170ee400e7c607e39abd5484df65`; new RC26 Draft corrective HEAD after final QA; `Deployed Version = UNVERIFIED`; `DB Version = UNVERIFIED`; `Migration State = UNVERIFIED`; `Live Verification Status = NOT PERFORMED`.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
