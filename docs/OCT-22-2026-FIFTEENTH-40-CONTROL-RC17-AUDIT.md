# File18 RC17 — Fifteenth Source/Plan 40-Control and Cross-Owner Audit

Date: **22 October 2026**. Specification evidence: uploaded File18 Marketplace Complete Plan v1.0, three consolidated central plans v3.0, File17 Communication final, conditional CF05 Analytics v1.1 and CF06 Localization v1.1, relevant File00/10/19/20/24/25/26 owner interfaces.

## Frozen truth and scope
- **Source baseline:** PR #16 exact green HEAD `92c530bb6649f569637b71b783e052450c369f64` (40/40 QA in each PHP matrix member). Base is not deployed.
- **Repository main independently:** `04a5872e3e2e170ee400e7c607e39abd5484df65`. PR #17 is stacked on PR #16 and not main.
- **Source fix target:** RC17 candidate plugin 2.1.0 / target schema 2.1.0 / contract 1.2.0. Historical RC16 package is not this candidate.
- **Deployed/DB facts:** installed version, exact code/archive SHA, runtime config, tables/columns/options, migration state, live File00/10/17/19/20/24/25/26 integration, server logs and backup parity **unverified**. No live issue resolved.

## Newly confirmed source fault groups and correction
1. **Recall read outage bypassed publication:** `public_recall()` conflated missing governance table or SQL failure with no active recall. Added `MKT_Governance::recall_publication_guard()`, returning explicit 503 on unavailable truth and enforcing it in Plan Completion, Release Gate, and direct listing transition.
2. **Recall remained eligible in public projection:** public listing detail/by-ID, canonical Marketplace search and File26-facing enhanced search only filtered active/approved/expiry, not the Recall owner's live state. Added indexed SQL `NOT EXISTS` against `recalls.listing_status` in all four query surfaces. An unavailable schema cannot return a falsely eligible public result.
3. **Prohibited-item policy failed open:** absent active prohibited-policy row or corrupt `rules_json` became empty arrays, which may permit otherwise declared listing. Added mandatory `prohibited_policy_unavailable` refusal; explicit, valid owner-approved empty category/phrase lists remain deliberate accepted policy.
4. **Incomplete safety seed promoted false version truth:** failed `$wpdb->get_var`, retire UPDATE or INSERT still allowed `mkt_builtin_policy_version` to advance. Added check-before-promotion and bumped managed seed marker to `categories-4` to reattempt the previous unverified seed state.

**Focused tests:** `tests/test-rc17-recall-safety-failclosed.php` and `tests/test-rc17-policy-failclosed.php` use real source classes and negative WordPress stub/DB fault injection. Aggregate QA now has **41 stages**, repeated **40 complete times** each on PHP 8.1/8.3 by workflow. Run conclusions and SHA-256 must be verified at the final exact source HEAD; planned repeats are not proof of completion before GitHub Actions finishes.

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

**Evidence limits:** These are **40 named requirement/owner source checkpoints**, not 40 independent human source-code passes; the CI `40×` is repeated 41-step automated regression, not complete end-to-end acceptance. Plan's 58 central CV acceptance requirements, real commerce/users, staging MySQL, browser/RTL accessibility, File00/10/17/19/20/24/25/26 owner integrations and CF05/CF06 conditional extraction are not proven by this matrix.

## Release blockers (strict, do not bypass)
1. `https://sabrihomeopathy.com` WPVibe has no site registered; previously yielded Cloudflare HTTP 530. HTTP 530 alone is not proof of where origin/routing failed. Obtain Cloudflare Ray ID, 1xxx code, origin logs, DNS records from authorized host. Issue #17.
2. No approved tamper-evident audit-chain checkpoint/archive/legal retention protocol. RC15/16 protect by disallowing raw pruning. Issue #18.
3. Actual deployed File18 plugin and SHA, schema/options/migration/DB error logs, staging WordPress/MySQL concurrency, required companion owner contracts, backup/restore and rollback are unverified.
4. Two final independent reviews, Founder approval, exact release package parity, deploy and live re-test not completed. Never merge or mark production Resolved based on CI alone.

## Mandatory final truth ledger
| Fact | State |
|---|---|
| `main` HEAD | `04a5872e3e2e170ee400e7c607e39abd5484df65` |
| Corrective source HEAD | PR #17 HEAD, verify after all commits |
| Deployed version | UNVERIFIED |
| Live DB version | UNVERIFIED |
| Live migration state | UNVERIFIED |
| Live verification | NOT PERFORMED |

Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
