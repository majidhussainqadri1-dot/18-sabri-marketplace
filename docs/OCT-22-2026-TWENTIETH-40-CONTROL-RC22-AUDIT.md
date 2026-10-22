# File 18 — Twentieth 40-Control Governing and Companion-Owner Audit (RC22)

**Date:** 22 October 2026, Karachi time. Governing source documents actually reviewed: the user-uploaded `18-File-18-Marketplace-Complete-Master-Plan-2026-v1.0(1).docx`; Three Central Plans consolidated governing master plan; File17 Communication master plan; cross-owner/conditional catalog from the File18 40-control matrix. **This is a source-repository audit, not a live incident diagnosis.**

## Source reality frozen
- Repository `main` source HEAD: `04a5872e3e2e170ee400e7c607e39abd5484df65`; do not describe as deployed.
- Corrective baseline: RC21 Draft PR #23, exact last-green HEAD `63abc8c5dbde75af4309e83fbad1d4c2c51ba5ea` (CI run `39682478240`, 80/80 ×49).
- RC22 is a stacked **Draft** source change, subject to new exact-head CI and independent staging/release gates. Metadata plugin `2.1.0`, target schema `2.1.0` and owner contracts are not installed-site facts.

## Three new proven source defect groups / root causes / corrections
1. **Ambiguous durable command outcome promoted to retryable false failure** (F18-FR-011 / F18-NFR-003 and central idempotency rule): `MKT_Idempotency::run` caught exceptions from domain callbacks and blindly updated durable receipt to `failed`, even when a handler may have committed then thrown. This falsely authorized replay of a completed transaction. **Correction:** preserve reserved `processing` outcome on uncertain exception, return safe `mkt_idempotency_outcome_uncertain` for reconciliation; no fabricated `failed` marker.
2. **Reused new-offer key accepted a changed command** (F18-FR-011 / F18-NFR-003): `MKT_Commerce::create_offer` returned the prior row for *same buyer* even for a different listing, amount, currency, terms or expiration period. **Correction:** compare exact normalized persisted offer facts and original requested window; mismatch returns `mkt_idempotency_conflict`; genuine replay still returns existing receipt.
3. **Reused counter-offer key accepted changed terms/price/expiry** (F18-FR-012 / F18-NFR-003): `transition_offer(countered)` checked only actor and parent offer, ignoring amount, terms and expiry. **Correction:** reject different amount/terms/duration, retain same-command replay.

These are **3 distinct source defects fixed in RC22 candidate**, not proven live production defects. Explicit date-window verification tolerates only persisted DB UTC time-second precision. New regressions:
- `tests/test-rc22-idempotency-exception-uncertainty.php`: real durable idempotency owner + fake DB, commit-then-throw, no `failed` receipt, duplicate blocked, verified failed receipt may retry.
- `tests/test-rc22-offer-payload-idempotency.php`: real commerce owner + fake DB, same-key matching replay, changed buyer/listing/price/terms/duration and counter-order changes must fail without writes.
- New full `qa-static.sh`: **51 named checks**, planned 40 complete passes × PHP 8.1/8.3, plus deterministic candidate ZIP `18-marketplace-2.1.0-RC22.zip`. Do not call it successful until final-head CI verifies both 40/40 and SHA-256.

## Source-to-plan 40 named control register

| # | Requirement or canonical owner | Implementation boundary | Source signal | Staging/live verification still needed |
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

**Interpretation:** the 40 source-control IDs are 30 File18 FR/NFR plus 10 cross-owner/conditional responsibilities. They are not 40 independent human security reviews or 58/58 central CV release acceptance. Plan paragraph 109 forbids guessing timeout outcome, paragraph 101 requires unique/transactional concurrency, paragraph 106 mandates owner state + outbox atomic persistence; the consolidated central plan requires safe idempotency and replay protection.

## Release/Deployment-Parity Blockers
- #17: WPVibe has no registered WordPress site; previous Cloudflare HTTP 530 attempts to contact `sabrihomeopathy.com` cannot prove installed plugin/hash/schema, active PHP/version, options, runtime logs or host Cloudflare 1xxx/Ray ID/origin records. No bot bypass, unapproved firewall change or guessed deployment. Need authorized hosting diagnostic evidence.
- #18: tamper-evident approved Audit hash-chain checkpoint, retention legal holds and archive governance missing, and no approval for destructive pruning.
- Real MySQL transaction/isolation and concurrent offer replay tests, actual File00/10/17/19/20/24/25/26 owner contracts, CF05/CF06 conditional activation, staging backup/restore and rollback, privacy/RTL/a11y/performance, 58 central CV checks, independent reviews, Founder deploy approval, final parity/live re-test are not established.

**Mandatory truth ledger:** GitHub HEAD / Deployed Version / DB Version / Migration State / Live Verification Status reported separately.
**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
