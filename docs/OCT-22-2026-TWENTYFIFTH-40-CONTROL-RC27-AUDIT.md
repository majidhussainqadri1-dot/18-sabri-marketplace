# File18 RC27 — Twenty-fifth 40-Control Marketplace Source/Owner Plan Audit

Date: **22 October 2026**, Pakistan. **Frozen source baseline:** RC26 Draft PR #28 successful HEAD `e025c5e06a95b82da0183069eb90bf42d2afc706` verified PHP8.1 and PHP8.3 40/40 of 56 named stages (CI run `39735823244`). GitHub repository `main` source HEAD **separately** `04a5872e3e2e170ee400e7c607e39abd5484df65`. Never treat either branch as deployed plugin.

**Attached plan sources:** File18 Marketplace Complete Master Plan 2026 v1.0, Consolidated Three Central Governing Plans; File17 Communication Network; CF05 Analytics; CF06 Localization and other canonical boundaries File00 Identity, File10 Media, File19 Notifications, File20 Shell, File24 Safety, File25 Visual, File26 Search. File18 direct requirements include FR011–013 structured offer/counter/deal transitions, NFR003 concurrency/atomic owner+outbox and dependable API response (document paragraphs 88,90,95,98,104,123). Three Central Plans require canonical owner facts, owner audit/outbox atomicity, no false status claims and staging-first.

## Five newly confirmed SOURCE defect groups / RC27 corrections

1. **New offer readback not verified**: `MKT_Commerce::create_offer()` read a newly inserted offer before mandatory Audit/Outbox but did not check missing/wrong ID, buyer, listing, status or first version, and used mutable `$wpdb->insert_id` without a named captured identity. RC27 captures ID and requires exact canonical row facts before receipt; wrong/missing readback triggers `mkt_offer_readback_failed` within transaction.
2. **New counter-offer readback not verified**: `transition_offer(countered)` read newly inserted child offer without checking matching public_id, parent ID, creator, open status and version 1, then emitted positive audit/outbox; RC27 requires exact row and `mkt_counter_readback_failed` transactional rollback.
3. **Offer status transition readback not verified**: `transition_offer()` returned `offer_dto` of nullable/stale/falsely matching final row after audit/outbox and possibly Deal creation; RC27 checks final public ID, expected version +1 and effective target state, otherwise `mkt_offer_transition_readback_failed` rolls back owner facts.
4. **New accepted Deal readback was identity-only**: `create_deal_from_offer()` verified newly inserted deal's public UUID but not expected offer, listing, parties, accepted status, or version 1 after decrementing stock and sending events. RC27 requires all invariants; `mkt_deal_readback_failed` rolls back offer/deal/stock and outbox atomically.
5. **Deal state transition readback unchecked**: `transition_deal_owner()` directly returned a DTO of nullable/stale final readback after status, payment/completion, Audit and Outbox. RC27 requires exact deal ID and public ID, expected version +1 and `$effective_to` (including first-party completion confirmation), or `mkt_deal_transition_readback_failed` inside transaction.

**New actual-class simulated database regression**: `tests/test-rc27-commerce-final-readback.php` exercises all five direct owner pathways with good receipt, missing, wrong status, wrong version and wrong public identity, plus wrong buyer/deal's offer association, asserts failed owner transaction rolls back offers/deals/listing stock/audit/outbox. Does not substitute for authentic WordPress/MySQL concurrency or 58 governing CV staging tests.

**Target QA:** 57 named static, PHP actual-owner-contract and negative test stages repeated as one COMPLETE suite 40 times on PHP8.1 and 40 times on PHP8.3 (80 repetitions). Validate the exact final corrective HEAD and deterministic `18-marketplace-2.1.0-RC27.zip` SHA-256 in both raw job logs, not predecessor results.

## Exactly 40 File18 and Companion Owner Controls

| # | Requirement or external owner | Canonical boundary | Source evidence | Unverified live/staging acceptance |
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

These controls are a requirement coverage register (20 FR +10 NFR+10 independent owner contracts), **not** 40 independent human audits nor all 58 central CV.

## Unresolved release blockers / separate realities

**#17** — WPVibe zero registered WordPress sites; latest attempts at `https://sabrihomeopathy.com` Cloudflare HTTP 530; no authoritative Cloudflare 1xxx, Ray ID, origin DNS/host log evidence, deployed plugin file hashes/PHP dependencies, actual live DB schema/tables/columns/rows/wp_options/migration. Do not speculate about firewall or deploy blindly.

**#18** — immutable hash-chained audit archive/checkpoint and lawful hold-aware retention File24/legal/security/Founder approval pending. Do not enable destructive audit pruning.

Other acceptance: Actual WordPress/MySQL staging races, File00/10/17/19/20/24/25/26 integration, optional CF05/CF06, 58 central CV, accessibility/RTL/performance, backup/restore and rollback, two independent reviews, Founder consent, parity confirmation, deploy and live retest UNVERIFIED.

**Repository HEAD `main`:** `04a5872e3e2e170ee400e7c607e39abd5484df65` | **New corrective source HEAD**: only final CI-proven SHA qualifies. **Deployed Version** UNVERIFIED | **DB Version** UNVERIFIED | **Migration State** UNVERIFIED | **Live Verification Status** NOT PERFORMED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
