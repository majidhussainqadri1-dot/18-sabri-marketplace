# File 18 Requirements Traceability Matrix

| Requirement | Implementation owner | Primary code/evidence | Automated/manual acceptance |
|---|---|---|---|
| F18-FR-001 Category/policy taxonomy | `MKT_Policy` | `mkt_policies`, seeded category/prohibited rules | policy/source tests + staging categories |
| F18-FR-002 Seller eligibility | `MKT_Auth`, File 00 adapter | identity assertions, seller reconciliation | approved/pending/suspended/minor/risk matrix |
| F18-FR-003 Listing draft | `MKT_Listings` | create/update, normalized fields, declarations | REST + real seller journey |
| F18-FR-004 Listing media | central media adapter | `mkt_media_refs`, rights/scan publish gate | provider contract + malicious upload tests |
| F18-FR-005 Listing review | `MKT_Policy`, moderators | review state, human holds, reasons | regulated/claim policy tests |
| F18-FR-006 Listing lifecycle | state machine | versioned atomic transitions | positive/negative/stale-state tests |
| F18-FR-007 Search/facets | `MKT_Listings::search` | bounded cursor, indexed filters, File 26 provider | query/load/index removal tests |
| F18-FR-008 Cards/detail | public DTO/templates | exact price/currency/status/seller/update labels | UI/SEO/accessibility matrix |
| F18-FR-009 Save/share/report | listings/moderation | private saves, public URL, structured report | auth/IDOR/report abuse tests |
| F18-FR-010 Product-linked chat | File 17 adapter | context card, no chat table | real File 17 one-conversation test |
| F18-FR-011 Offer | `MKT_Commerce` | amount/currency/terms/expiry/idempotency | duplicate/replay/eligibility tests |
| F18-FR-012 Negotiation | offer state machine | counter/accept/decline/withdraw/version | actor and stale-offer tests |
| F18-FR-013 Deal lifecycle | `MKT_Commerce` | atomic accepted snapshot, direct status | concurrent accept + participant tests |
| F18-FR-014 Zero commission | contracts/code/schema | constant 0, no commission column/calculation | static invariant + UI/terms review |
| F18-FR-015 Payment/manual proof bridge | deal status + external event | declared/manual/provider statuses, no escrow claim | verified event and outage tests |
| F18-FR-016 Moderation | `MKT_Moderation` | prohibited rules, restrict/remove/appeal | illegal/fraud/counterfeit/false-claim tests |
| F18-FR-017 Disputes | dispute state machine | purpose-limited evidence refs/decision | participant/reviewer/access/retention tests |
| F18-FR-018 Promotions | setting/policy | disabled by default; labeled field only | no hidden ranking test |
| F18-FR-019 Seller insights | aggregate metrics | daily counts only, no buyer identity leakage | privacy threshold/manual dashboard gate |
| F18-FR-020 Expiry/reconciliation | maintenance/events | hourly/daily expiry, seller suspension, events | delayed cron and stale index tests |
| F18-NFR-001 Object/field authorization | `MKT_Auth` + native services | current claims, ownership/state checks | full IDOR matrix |
| F18-NFR-002 Privacy lifecycle | `MKT_Privacy`, retention | exporter/eraser/no-store/redaction | rights request and deletion propagation |
| F18-NFR-003 Reliability | DB/outbox/inbox | transactions, retry/dead-letter/reconciliation | failure injection and duplicate delivery |
| F18-NFR-004 Performance | indexed schema/search | bounded limits/cursors/conditional assets | p75/p95 staging load evidence |
| F18-NFR-005 Accessibility | templates/CSS/JS | keyboard, focus, semantics, RTL, reduced motion | WCAG 2.2 AA objective review |
| F18-NFR-006 Observability | audit/status/metrics | trace IDs, System Check, aggregate metrics | alert/runbook drill |
| F18-NFR-007 Migration/rollback | `MKT_DB`, handoff ledger | idempotent import, no destructive delete | fresh/upgrade/restore/rollback rehearsal |
| F18-NFR-008 Operability | admin/maintenance | safe mode, system check, repair dry-run | operator runbook acceptance |
| F18-NFR-009 Compatibility | runtime metadata/CI | PHP 8.1/8.3, WP project baseline | Hostinger staging matrix |
| F18-NFR-010 Localization | UI/text domain/CSS | American English base, RTL logical layout | Urdu/Arabic/date/currency tests |

## DoD evidence states

- **Specified:** complete in the approved plan.
- **Coded:** source present and reviewable.
- **Packaged:** deterministic ZIP, manifest and checksum.
- **Automated-QA Green:** repository test suites pass.
- **Staging-Accepted:** real WordPress/Hostinger roles, integrations, migration, UI and rollback pass.
- **Live-Deployed:** controlled production activation and smoke tests.
- **Operational:** monitoring, moderation, support, backup and incident processes work continuously.
