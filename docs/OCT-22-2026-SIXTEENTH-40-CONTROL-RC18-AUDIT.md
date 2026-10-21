# File18 RC18 — Sixteenth 40-Control Governing/Companion Plan Audit

Date: **22 October 2026**. Evidence basis: attached File18 Marketplace Complete Master Plan v1.0, consolidated three central governing plans, File17 Communication harmonized plan, and source-owner interface contracts with Files 00/10/19/20/24/25/26; CF05/CF06 remain conditional.

## Repository truth freeze
- `main` HEAD `04a5872e3e2e170ee400e7c607e39abd5484df65`; this is **not** the plugin source in the corrective candidate.
- Last successful RC17 draft PR #19 HEAD `e85cf9f21a870b9729a7f15d0a1292f3f2db50ff`; RC18 Draft PR #20 is based on that exact prior head.
- Runtime metadata 2.1.0, target core DB schema 2.1.0 and File18 contract 1.2.0 in repository only.
- Exact live deployed plugin, zip hash, database version/tables/columns, wp_options, migration state, PHP logs, active companion plugins and backups **NOT VERIFIED**.

## Four new source defect groups found and changed
1. **Direct listing submit bypass:** `MKT_Listings::submit()` enforced policy/media but did not run the language, approved regulated-product evidence and verified recall prerequisite checks applied by REST. Owner now invokes `MKT_Plan_Completion::publication_gate()` before state mutation.
2. **Direct listing activation bypass:** `MKT_Listings::transition(...,'active')` (including wp-admin) had recall/expiry/policy/media but lacked the same language/structured-evidence check. Owner now invokes the same gate. Wrapper validation remains defense in depth.
3. **Native moderation atomicity:** WordPress admin form could write listing, report or dispute state and then fail audit/outbox after the irreversible write, because no transaction enveloped owner methods. Added `MKT_DB::transaction` and explicit failure handling to the native admin action; transactions roll back on errors.
4. **Language-facets migration truth:** `mkt_plan_completion_schema_version` was advanced regardless of whether `dbDelta` actually created `mkt_listing_facets` with required columns/unique `PRIMARY(listing_id)`. The option could also suppress repair of a stale/partial DB. Added `schema_ready()`, per-boot option-vs-schema check, failure marker and prevention of false schema-version promotion.

The RC17 five-group recall/policy corrections remain cumulative; this iteration adds **4 new nonoverlapping source defect groups**. No live-production defect is marked fixed. **Source correction, not production resolution.**

## Coverage and test interpretation
- Source-to-plan matrix: **40 named acceptance checkpoints**, 30 File18 requirements plus 10 related owner boundaries. Each has an independent operational gate; source/test presence is not a live pass.
- New actual-class regressions `tests/test-rc18-core-publication-gates.php` and `tests/test-rc18-facet-schema-truth.php` validate denial before write and facet migration truth; native admin transaction is also source-anchored.
- Full aggregate: **43 static/unit/negative/contract stages** on each automated pass; GitHub Actions schedules **40 repeated whole suites on PHP 8.1 and 40 on PHP 8.3**. Report final pass count and deterministic RC18 ZIP SHA-256 only from exact final head.
- The plan asks more than repeating a static suite: **real staging WordPress/MySQL, concurrent buyer/seller scenarios, File00/10/17/19/20/24/25/26 integration, privacy/export/erase, two independent reviews and 58 mapped central CV requirements**. None may be inferred from source CI.

## 40 named requirement/owner control inspection register

| # | ID | Planned requirement / owner | Repository anchor | Mandatory independent operational gate |
|---:|---|---|---|---|
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

**Evidence limits:** These are **40 named requirement/owner source checkpoints**, not 40 independent human source-code passes; the CI `40×` is repeated 43-step automated regression, not complete end-to-end acceptance. Plan's 58 central CV acceptance requirements, real commerce/users, staging MySQL, browser/RTL accessibility, File00/10/17/19/20/24/25/26 owner integrations and CF05/CF06 conditional extraction are not proven by this matrix.

## Release blockers (strict, do not bypass)
1. `https://sabrihomeopathy.com` WPVibe has no site registered; previously yielded Cloudflare HTTP 530. HTTP 530 alone is not proof of where origin/routing failed. Obtain Cloudflare Ray ID, 1xxx code, origin logs, DNS records from authorized host. Issue #17.
2. No approved tamper-evident audit-chain checkpoint/archive/legal retention protocol. RC15/16 protect by disallowing raw pruning. Issue #18.
3. Actual deployed File18 plugin and SHA, schema/options/migration/DB error logs, staging WordPress/MySQL concurrency, required companion owner contracts, backup/restore and rollback are unverified.
4. Two final independent reviews, Founder approval, exact release package parity, deploy and live re-test not completed. Never merge or mark production Resolved based on CI alone.

## Mandatory final truth ledger
| Fact | State |
|---|---|
| `main` HEAD | `04a5872e3e2e170ee400e7c607e39abd5484df65` |
| Corrective source HEAD | Draft PR #20 HEAD, verify after all commits |
| Deployed version | UNVERIFIED |
| Live DB version | UNVERIFIED |
| Live migration state | UNVERIFIED |
| Live verification | NOT PERFORMED |

Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
