# File 18 RC23 — Twenty-first 40-control Marketplace and owner-boundary audit

Date **22 October 2026**, Pakistan. Authoritative user files: File18 Marketplace Complete Master Plan 2026, Three Central Consolidated Governing Plans; File17 Communication Network, CF05 Analytics Metrics, CF06 Localization. Companion ownership: Files 00, 10, 17, 19, 20, 24, 25, 26, CF05, CF06. This register reproduces the canonical 30 File18 FR/NFR and 10 dependent-owner checkpoints; it **does not** certify independent real-world acceptance.

## Exact source baseline and deployment distinction

- GitHub source `main`: `04a5872e3e2e170ee400e7c607e39abd5484df65`. Distinct from corrective baseline RC22 Draft PR #24 exact last-green SHA `7deed8ed5874b73461acd20499ad7768f28a3f53`.
- RC23 is a subsequent stacked Draft candidate. No production/staging package parity can be established. Source metadata plugin `2.1.0`, target schema `2.1.0` and File18 contract `1.2.0` are **not** verified installed versions.

## Source evidence for five newly confirmed defect groups, corrected in source

1. **Private Commerce dashboard IDOR** (F18-NFR-001, FR-019): public PHP `MKT_Commerce::dashboard($user_id)` read private offers, deals, reports and dispute records for an arbitrary caller-provided user ID. Canonical owner now rejects a principal different from authenticated WordPress `get_current_user_id()` *before DB read*.
2. **Private deal-detail actor spoof** (FR-013, F18-NFR-001): `MKT_Commerce::get_deal(...,$user_id)` granted participant access when a PHP caller supplied the participant's ID rather than their own. Mandatory current-principal equality now precedes participant/reviewer authorization.
3. **Commerce mutation actor spoof** (FR-011, FR-012, FR-013, File00): `create_offer`, `transition_offer` and `transition_deal` invoked File00/WordPress capability checks for the authenticated caller but accepted an independently supplied `buyer_id` or `actor_id` when mutating another user's commerce facts. Actor identity is now bound to authenticated principal before state and SQL work.
4. **Listing, moderation, and governance mutation actor spoof** (FR-003/004/006/009/010/016/017, Files 00/10/17/24): direct listing/seller create/edit/submit/status/media/save/chat, report/dispute create/review, regulated evidence reviews, recall, legal holds and Seller Studio were also accepting arbitrary actor IDs. 20 canonical owner entrypoints now fail closed on mismatched or unauthenticated IDs. Moderators must act as their own authenticated principal, not impersonate sellers/reporters.
5. **Unscoped private listing search** (FR-007/008, F18-NFR-001, File26): `MKT_Listings::search($filters,true)` skipped the public `active` filter and permitted omitted or other-user `seller_user_id` constraints, allowing drafts and private DTO enumeration via native PHP. Now requires the explicit current seller ID before any private SQL lookup, otherwise `mkt_private_search_forbidden`.

**Automated regression:** `tests/test-rc23-current-actor-ownership.php` invokes **25 real owner entrypoints with two illegal identities** (50 denied calls), and 3 private-search scopes, asserting stable IDOR denials and zero DB access. Existing positive and negative source contract tests continue. 52 named QA stages, entire suite repeated 40× for PHP 8.1 and 40× for PHP 8.3. Must verify new exact-final-head CI, not predecessor's logs, before declaring PASS.

**Legitimate moderator/owner access:** this fix binds the supplied actor to the currently authenticated principal; it does not prevent authorized actors from passing their *own* ID to reviewer-specific actions. Private owner search remains intentionally own-seller-scoped; cross-account moderation belongs to purpose-scoped admin queues.

## Forty named source and cross-owner checkpoints

| # | Requirement/owner | Canonical boundary | Source evidence | Independent live/staging gate |
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

## Deferred release evidence — blocked, not silently resolved

- #17 live WPVibe not connected; latest authorized attempt returned Cloudflare HTTP 530, with no precise 1xxx error, Ray ID, origin DNS/host health, deployed package hash/version, active plugin list, actual live DB table/columns/rows/options/migration state or error logs. Never infer issue is bot block or GitHub fix is deployed.
- #18 tamper-evident immutable audit-chain archive/checkpoint and hold-aware legal retention need File24/legal/security/Founder signoff; destructive pruning forbidden.
- WordPress/MySQL real staging races, actual File00/10/17/19/20/24/25/26 external owner contracts, CF05/CF06 conditional activation, 58 central CV staging acceptance, RTL/a11y/privacy/load, backups/restore/rollback, independent reviews and Founder authorization pending.

**Live incident truth ledger:** Repository HEAD / Deployed Version / DB Version / Migration State / Live Verification Status all separately reported.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
