# File 18 — Fourteenth 40-Control Review (RC16, 21 October 2026)

**Scope:** File18 Complete Plan 2026, Three Central Governing Plans, File17 Communication, File00/10/19/20/24/25/26 owner interfaces, conditional CF05 Analytics and CF06 Localization. File18 plan is a specification, not production evidence.

**Frozen repository baseline:** RC15 Draft PR #15 HEAD `a62d5b12805ef1ea1be275ba412445f204ad9a55`; `main` independently `04a5872e3e2e170ee400e7c607e39abd5484df65`. New source branch `fix/file18-fourteenth-40-control-rc16-2026-10-21`, PR #16 stacked on #15. Neither is deployed.

## Findings and applied source corrections

**Four related but individually testable defect groups:** (1) missing UTC expiry from public listing detail, lookup, canonical and enhanced File26 search; (2) missing locked expiry validation in offer/counter-offer/deal and in listing reactivation; (3) null/error deal commitment COUNT cast to zero; (4) two competing-offer decline UPDATE statements accepted false DB result. RC16 modifies File18 listing/finalization/commerce source with fail-closed rules, uses `MKT_Listings::not_expired()` with strict UTC parsing, and adds a runtime-shaped negative regression.

**40-cycle QA:** `.github/workflows/quality.yml` invokes the complete `qa-static.sh` 40 times for PHP 8.1 and 40 times for PHP 8.3 (38 stages per aggregate). This is repeated automated regression execution, **not** 40 manual independent full-system audits or proof of 58/58 central acceptance. Verify latest exact SHA, run conclusions, and deterministic RC16 package checksum after CI.

## Cross-document traceability — 40 named controls

| # | Requirement / owner | Implementation / responsibility | Evidence and acceptance boundary |
|---:|---|---|---|
| 1 | F18-FR-001 Category/policy taxonomy — `MKT_Policy` | `mkt_policies`, seeded category/prohibited rules | policy/source tests + staging categories |
| 2 | F18-FR-002 Seller eligibility — `MKT_Auth`, File 00 adapter | identity assertions, seller reconciliation | approved/pending/suspended/minor/risk matrix |
| 3 | F18-FR-003 Listing draft — `MKT_Listings` | create/update, normalized fields, declarations | REST + real seller journey |
| 4 | F18-FR-004 Listing media — central media adapter | `mkt_media_refs`, rights/scan publish gate | provider contract + malicious upload tests |
| 5 | F18-FR-005 Listing review — `MKT_Policy`, moderators | review state, human holds, reasons | regulated/claim policy tests |
| 6 | F18-FR-006 Listing lifecycle — state machine | versioned atomic transitions | positive/negative/stale-state tests |
| 7 | F18-FR-007 Search/facets — `MKT_Listings::search` | bounded cursor, indexed filters, File 26 provider | query/load/index removal tests |
| 8 | F18-FR-008 Cards/detail — public DTO/templates | exact price/currency/status/seller/update labels | UI/SEO/accessibility matrix |
| 9 | F18-FR-009 Save/share/report — listings/moderation | private saves, public URL, structured report | auth/IDOR/report abuse tests |
| 10 | F18-FR-010 Product-linked chat — File 17 adapter | context card, no chat table | real File 17 one-conversation test |
| 11 | F18-FR-011 Offer — `MKT_Commerce` | amount/currency/terms/expiry/idempotency | duplicate/replay/eligibility tests |
| 12 | F18-FR-012 Negotiation — offer state machine | counter/accept/decline/withdraw/version | actor and stale-offer tests |
| 13 | F18-FR-013 Deal lifecycle — `MKT_Commerce` | atomic accepted snapshot, direct status | concurrent accept + participant tests |
| 14 | F18-FR-014 Zero commission — contracts/code/schema | constant 0, no commission column/calculation | static invariant + UI/terms review |
| 15 | F18-FR-015 Payment/manual proof bridge — deal status + external event | declared/manual/provider statuses, no escrow claim | verified event and outage tests |
| 16 | F18-FR-016 Moderation — `MKT_Moderation` | prohibited rules, restrict/remove/appeal | illegal/fraud/counterfeit/false-claim tests |
| 17 | F18-FR-017 Disputes — dispute state machine | purpose-limited evidence refs/decision | participant/reviewer/access/retention tests |
| 18 | F18-FR-018 Promotions — setting/policy | disabled by default; labeled field only | no hidden ranking test |
| 19 | F18-FR-019 Seller insights — aggregate metrics | daily counts only, no buyer identity leakage | privacy threshold/manual dashboard gate |
| 20 | F18-FR-020 Expiry/reconciliation — maintenance/events | hourly/daily expiry, seller suspension, events | delayed cron and stale index tests |
| 21 | F18-NFR-001 Object/field authorization — `MKT_Auth` + native services | current claims, ownership/state checks | full IDOR matrix |
| 22 | F18-NFR-002 Privacy lifecycle — `MKT_Privacy`, retention | exporter/eraser/no-store/redaction | rights request and deletion propagation |
| 23 | F18-NFR-003 Reliability — DB/outbox/inbox | transactions, retry/dead-letter/reconciliation | failure injection and duplicate delivery |
| 24 | F18-NFR-004 Performance — indexed schema/search | bounded limits/cursors/conditional assets | p75/p95 staging load evidence |
| 25 | F18-NFR-005 Accessibility — templates/CSS/JS | keyboard, focus, semantics, RTL, reduced motion | WCAG 2.2 AA objective review |
| 26 | F18-NFR-006 Observability — audit/status/metrics | trace IDs, System Check, aggregate metrics | alert/runbook drill |
| 27 | F18-NFR-007 Migration/rollback — `MKT_DB`, handoff ledger | idempotent import, no destructive delete | fresh/upgrade/restore/rollback rehearsal |
| 28 | F18-NFR-008 Operability — admin/maintenance | safe mode, system check, repair dry-run | operator runbook acceptance |
| 29 | F18-NFR-009 Compatibility — runtime metadata/CI | PHP 8.1/8.3, WP project baseline | Hostinger staging matrix |
| 30 | F18-NFR-010 Localization — UI/text domain/CSS | American English base, RTL logical layout | Urdu/Arabic/date/currency tests |
| 31 | X-01 File00 — Identity age, guardian, seller/deal scoped approval | MKT_Auth / MKT_Integrations | Deployed File00 SMC assertions and roles unverified |
| 32 | X-02 File10 — Media rights, safety scan, external refs | MKT_Listings::attach_media | Provider sandbox concurrency and attachments unverified |
| 33 | X-03 File17 — Single canonical chat owner & context | MKT_Integrations::open_context_conversation | Deployed File17 bridge and legacy handoff unverified |
| 34 | X-04 File19 — Required notification delivery ACK | MKT_Events::process_outbox | Recipient inbox delivery in staging unverified |
| 35 | X-05 File20 — Unified shell, navigation and Safe Mode | MKT_Integrations / MKT_Routes | Real shell placement and responsive UAT unverified |
| 36 | X-06 File24 — Security/privacy assurance and retention | MKT_Release_Gates | Independent threat model and breach workflow unverified |
| 37 | X-07 File25 — Global visual tokens, RTL and accessibility | Templates/CSS and hooks | Screen-reader and visual regression matrix unverified |
| 38 | X-08 File26 — Global search adapter; no duplicate truth | MKT_Listings::search_provider_query | Provider crawl/index expiry and long-tail search unverified |
| 39 | X-09 CF05 — Conditional analytics projections only | MKT_Maintenance::aggregate_metrics | Do not activate dedicated warehouse without governance |
| 40 | X-10 CF06 — Conditional localization registry; native copy only | MKT_Plan_Completion | Do not claim full translation ops or ship unreviewed translation |

**Interpretation:** Presence in source and an associated planned acceptance test do not independently prove every requirement works end-to-end. RC16 targeted expiration and transaction failure paths; each remaining requirement needs a real role/session/provider/DB verification before delivery sign-off.

## Unresolved gates and ownership

1. Legal/policy approval of tamper-evident hash-chain checkpoint, archive and lawful retention; unsafe audit deletion remains blocked.
2. No authorized live WordPress connection. Exact deployed file/plugin checksum, runtime version, database tables/columns/rows/options/migration state and PHP logs unknown. Cloudflare HTTP 530 seen historically; origin/Ray diagnostic unverified.
3. Real MySQL transaction isolation, concurrency, deadlock retries; migration dry run; privacy export/erasure and hold races; backup/restore/rollback on staging not verified.
4. Actual production File00, File10, File17, File19, File20, File24, File25, File26 contracts and conditional CF05/CF06 activation not verified.
5. Central 58 CV requirements, two independent fresh reviews, founder approval, deployed package parity and post-deployment live smoke still blocked.

**Truth ledger:** Repository main `04a5872e3e2e170ee400e7c607e39abd5484df65`; corrective source `PR #16` (check exact HEAD after all commits); deployed version = unverified; DB version = unverified; migration state = unverified; live verification = not performed. No live defect marked resolved.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
