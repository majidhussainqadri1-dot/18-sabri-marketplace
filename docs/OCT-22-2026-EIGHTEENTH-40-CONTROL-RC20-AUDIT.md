# File 18 RC20 — Eighteenth Governing-Plan and Cross-Owner Audit

Date: **22 October 2026**, Pakistan. Governing evidence: attached File18 Marketplace Complete Master Plan v1.0; three consolidated central plans; File17 Communication; CF05 Analytics and CF06 Localization conditional plans; owner interfaces for Files 00, 10, 19, 20, 24, 25, 26.

## Reality freeze
- Exact source baseline: **RC19 Draft PR #21**, successful head `5f30216803adc5c573a19483c8bb628bce7b49dc`; GitHub main separately `04a5872e3e2e170ee400e7c607e39abd5484df65`.
- RC20 is a **Draft stacked source change** based on RC19; it is not live or staging code. Repository plugin `2.1.0`; production installed version, deployed file SHA, schema/options/migrations, logs, and third-party active contracts UNVERIFIED.
- New review requirement IDs: same 30 File18 FR/NFR plus same 10 owner boundaries below. Checkpoint/source presence **does not mean real acceptance**.

## New proven source defects and corrective boundaries (four scoped issues)
1. **New offer recall race (FR-011, FR-020, File24)**: initial public lookup did exclude recalls, but in-transaction locked listing recheck did not verify active recalls. A concurrent recall could invalidate the earlier public check. Revalidate recall with `SELECT ... FOR UPDATE` under the listing lock.
2. **Counter-offer recall race (FR-012, File17/19)**: newly issued counter-offers use fresh listing status/eligibility but not the recall owner state. Add the same locked safety-owner check before creating a new commitment.
3. **Offer acceptance to deal recall race (FR-013, File19)**: accepting a pre-existing offer could create a deal after recall activation if listing remained active or reconciliation lagged. Use locking recall guard in `create_deal_from_offer()` before any insert/update.
4. **Staging-readiness false positive (NFR-006/NFR-007, File24)**: `MKT_Admin::system_status()` verified table names, not deployed core, governance or facets columns/indexes. It could falsely return `ready_for_staging_tests` after partial migration. Add three schema-ready blocking checks, and fail closed if zero-commission `DESCRIBE` errors/returns empty.

**Important concurrency limit:** SQL row locks, transaction boundaries, and real MySQL isolation/locking timing must still be confirmed on authorized WordPress staging. Source-level negative tests cannot prove all serializability scenarios.

## Exact 40 source/owner control checklist

| # | Governing requirement/owner | Canonical code or boundary | Source evidence | Remaining acceptance evidence |
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

## Verification methodology
- `tests/test-rc20-transactional-recall-commitments.php`: execute actual Commerce class against WordPress fake DB; assert six denial cases (recall active / database unavailable × create/counter/accept) and **zero** commerce state writes; require locking recall owner read.
- `tests/test-rc20-readiness-schema-truth.php`: execute actual Admin status class with separately damaged core/governance/facets, plus simulated SQL DESCRIBE outage; assert all fail closed.
- `qa-static.sh` combines **47 named static/contract/negative stages**, repeated **40 full aggregate passes on PHP 8.1 and 40 on PHP 8.3**; this is repeated deterministic QA, NOT 40 independent full manual audits. Check exact final GitHub Actions HEAD, both logs, and deterministic ZIP hash after final commit.

## Release blockers
- Issue #17: WPVibe WordPress site not registered; prior Cloudflare HTTP 530; no precise deployed code checksum, error 1xxx, Ray ID/origin logs or live DB truth. Never bypass security, guess root cause or deploy without approval.
- Issue #18: approved tamper-evident audit-chain archive/checkpoint and legal retention policy absent. Do not prune immutable ledger as a workaround.
- Real staging MySQL concurrency, backup/restore/rollback, File00/10/17/19/20/24/25/26 deployed adapters, 58 central CV acceptance, RTL/accessibility, independent reviews and Founder authorization remain unverified.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
