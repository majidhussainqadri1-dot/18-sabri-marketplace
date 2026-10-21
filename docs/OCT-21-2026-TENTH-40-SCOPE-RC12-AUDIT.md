# File 18 Marketplace — Tenth 40-Scope Corrective Source Review (2026-10-21)

**Starting exact-source baseline:** `9fd93c024f5568f8a5fa9f6cdabbcbf4f72c486e` (RC11's exact green repository HEAD). **Branch/PR:** `fix/file18-tenth-forty-source-review-2026-10-21`, Draft PR #12 stacked over Draft PR #11, **not** `main` or any proven deployed build. **Candidate:** `18-marketplace-2.1.0-RC12.zip`. Actual final checksum and fresh QA require exact-final-HEAD log verification after all code/documentation changes.

## Governing basis and precedence
The uploaded **File18 Marketplace Complete Master Plan**, **Three Central Plans Consolidated Governing Master Plan**, **File17 Communication Network Master Plan**, **CF03 conditional finance plan** and **CF05 conditional analytics/Future40 master plan** govern this source review. Marketplace remains canonical seller/listing/offer/deal/reports/disputes owner, File00 owns verified identity, File10 owns media, File17 communications, File19 notifications, File20 shell, File24 security/privacy assurance, File25 visuals and File26 search projection. No marketplace commission/paid or donor privilege. CF05 is **conditional, not activated** and may only use approved minimized/aggregate derivative events when separately approved.

## 40 distinct requirement-owner inspections
These are 40 independently identified source/owner scopes, **not forty separate exhaustive manual codebase reviews**. Automated GH CI additionally runs the **full source/contract/fault-injection QA suite 40 consecutive times for each PHP 8.1 and 8.3 job** (80 passes when exact-final-HEAD CI succeeds). Source presence is not a substitute for all clauses' runtime acceptance or all 58 related central CV IDs.

| # | Scope | Examined requirement | File18 source | Current finding and remaining live evidence |
|---:|---|---|---|---|
| 1 | FR-01 | Category safety & prohibited listings | `18-marketplace/includes/class-mkt-policy.php` | reviewer/regulated listings need real-role staging |
| 2 | FR-02 | Seller proof from File00 | `18-marketplace/includes/class-mkt-auth.php` | explicit File00 negative flags now preserved in adapter |
| 3 | FR-03 | Listings draft/validation | `18-marketplace/includes/class-mkt-listings.php` | real WP/MySQL smoke not performed |
| 4 | FR-04 | File10 media rights/scan | `18-marketplace/includes/class-mkt-listings.php` | actual File10 provider acknowledgement pending |
| 5 | FR-05 | Evidence/moderation | `18-marketplace/includes/class-mkt-governance.php` | mandatory governance evidence in export now verified |
| 6 | FR-06 | Listing transitions/CAS | `18-marketplace/includes/class-mkt-listings.php` | two-worker MySQL acceptance pending |
| 7 | FR-07 | Search and keyset pagination | `18-marketplace/includes/class-mkt-finalization.php` | large-content/RTL load pending |
| 8 | FR-08 | Public/private DTO separation | `18-marketplace/includes/class-mkt-listings.php` | cache/index leakage acceptance pending |
| 9 | FR-09 | Save/share/report | `18-marketplace/includes/class-mkt-listings.php` | real abuse throttling pending |
| 10 | FR-10 | File17 context conversation | `18-marketplace/includes/class-mkt-integrations.php` | real cross-owner URL and authorization pending |
| 11 | FR-11 | Offer validation and expiry | `18-marketplace/includes/class-mkt-commerce.php` | real multi-actor replay acceptance pending |
| 12 | FR-12 | Counteroffer concurrency | `18-marketplace/includes/class-mkt-commerce.php` | real stale CAS tests pending |
| 13 | FR-13 | Deal truth/snapshot | `18-marketplace/includes/class-mkt-commerce.php` | real snapshot/rollback and idempotency pending |
| 14 | FR-14 | Zero commission | `18-marketplace/includes/class-mkt-commerce.php` | production finance/donation parity pending |
| 15 | FR-15 | Payment event projection | `18-marketplace/includes/class-mkt-events.php` | provider verification and sandbox pending |
| 16 | FR-16 | Safety reports/moderation | `18-marketplace/includes/class-mkt-moderation.php` | moderator IDOR and appeal pending |
| 17 | FR-17 | Dispute evidence/lifecycle | `18-marketplace/includes/class-mkt-moderation.php` | shared retention/human policy pending |
| 18 | FR-18 | Disabled ranking promotions | `18-marketplace/includes/class-mkt-db.php` | deployed settings/donor parity unknown |
| 19 | FR-19 | Seller studio | `18-marketplace/includes/class-mkt-governance.php` | live File17/19 metrics projection unknown |
| 20 | FR-20 | Expiry/reconciliation | `18-marketplace/includes/class-mkt-maintenance.php` | WP Cron/actual consumer ACK pending |
| 21 | NFR-01 | Authenticated role/object/capability authorization | `18-marketplace/includes/class-mkt-auth.php` | File00 false approval/verification test added |
| 22 | NFR-02 | Privacy export/erase/hold | `18-marketplace/includes/class-mkt-privacy.php` | mandatory exporter query/hold completeness checks added |
| 23 | NFR-03 | Inbox/outbox idempotency | `18-marketplace/includes/class-mkt-events.php` | two-worker/process outage acceptance pending |
| 24 | NFR-04 | Bounded query performance | `18-marketplace/includes/class-mkt-finalization.php` | real query EXPLAIN/SLO pending |
| 25 | NFR-05 | WCAG/RTL/mobile | `18-marketplace/includes/class-mkt-routes.php` | real browser/screen-reader pending |
| 26 | NFR-06 | Audit hash and lock lifetime | `18-marketplace/includes/class-mkt-audit.php` | real MySQL audit parallelism pending |
| 27 | NFR-07 | Deployed schema/migration truth | `18-marketplace/includes/class-mkt-db.php` | reality freeze/staging upgrade pending |
| 28 | NFR-08 | Maintenance and recovery | `18-marketplace/includes/class-mkt-maintenance.php` | backup/restore/reconcile pending |
| 29 | NFR-09 | Versioned partner/owner contracts | `18-marketplace/includes/class-mkt-integrations.php` | deployed partner versions unknown |
| 30 | NFR-10 | Urdu/English localization/currency | `18-marketplace/includes/class-mkt-finalization.php` | real RTL/content UI review pending |
| 31 | CENTRAL-00 | File00 owns membership and identity | `18-marketplace/includes/class-mkt-integrations.php` | explicit false identity precedence fixed, real File00 unavailable |
| 32 | CENTRAL-10 | File10 owns media and rights | `18-marketplace/includes/class-mkt-listings.php` | no second media authority, staging pending |
| 33 | CENTRAL-17 | File17 owns communication | `18-marketplace/includes/class-mkt-integrations.php` | handoff URL & permission integration pending |
| 34 | CENTRAL-19 | File19 owns notification delivery | `18-marketplace/includes/class-mkt-integrations.php` | provider ACK and preferences pending |
| 35 | CENTRAL-20 | File20 owns global shell/nav | `18-marketplace/includes/class-mkt-integrations.php` | browser shell integration pending |
| 36 | CENTRAL-24 | File24 owns assurance policy | `18-marketplace/includes/class-mkt-integrations.php` | privacy/retention legal review pending |
| 37 | CENTRAL-25 | File25 owns design/a11y | `18-marketplace/includes/class-mkt-routes.php` | source tokens and zoom acceptance pending |
| 38 | CENTRAL-26 | File26 owns global search/index | `18-marketplace/includes/class-mkt-integrations.php` | index revocation/recall propagation pending |
| 39 | CENTRAL-CF03 | CF03 remains conditional finance owner | `18-marketplace/includes/class-mkt-commerce.php` | zero-fee direct marketplace/no duplicate financial ledger |
| 40 | CENTRAL-CF05 | CF05 remains conditional aggregate-only analytics | `18-marketplace/includes/class-mkt-events.php` | no parallel metric warehouse/no CF05 activation |

## Three newly proven repository-source defects corrected this round
- **R10-01 / Incomplete privacy export silently treated as complete.** `MKT_Privacy::export` fetched multiple WordPress `get_results` arrays without validating the results. Failed/null result could trigger `count(null)` or falsely exclude relevant personal records. The mandatory `MKT_Governance` listing evidence source was optional: absent table was silently skipped and `done=true` could be returned. All required SQL sets now have explicit NULL/SQL-error checks; mandatory governance schema is verified; an unavailable source returns `data=[]`, `done=false`, `error=mkt_export_source_unavailable` rather than a false completed bundle. `test-privacy-export-completeness.php` executes the actual exporter and injects failure separately in all seven source families plus schema unavailability.
- **R10-02 / Legal-retention-hold SQL read failures treated as absence.** The preliminary and in-named-lock duplicate hold checks did not reject `$wpdb->last_error`. On failed SELECT, an INSERT could proceed without knowing whether a legal hold was already active. Hold release similarly reported 404 on SQL failure. Added explicit fail-closed `mkt_hold_lookup_unavailable` 503 branches, and extended actual-class `test-governance-recall-hold-atomicity.php` to prove no INSERT, rollback and deferred lock release under both failures, and no UPDATE on release read outage.
- **R10-03 / File00 explicit identity denial overridden by coarse status.** In `MKT_Integrations::identity_assertions`, provider `approved=false` could be promoted to true simply because account `status=active`; `verified=false` could be overridden if status says verified. Now **provider explicit negative assertions take precedence**, including boolean-like textual `'false'` values that PHP would otherwise miscast as truthy; `guardian_verified`, `available` and contract compatibility use checked boolean parsing, while legacy missing fields retain supported status fallback. The real `MKT_Auth::can` capability/guardian gate is exercised under explicit File00 denial. The actual integration projection regression is `test-file00-explicit-identity-denial.php`.

**Test safety boundary:** the regressions use actual source classes but stubbed WordPress/provider/database responses. No real MySQL transactions, File00 account provider, WordPress Privacy Tools browser job, or production lawfulness has been verified. WordPress privacy-export repeat/recovery when source unavailable requires real acceptance.

## Remaining blockers / mandatory live ledger
| Mandatory item | Verified fact |
|---|---|
| `main` HEAD at start | `04a5872e3e2e170ee400e7c607e39abd5484df65` |
| Source reviewed/RC11 | `9fd93c024f5568f8a5fa9f6cdabbcbf4f72c486e` |
| Final RC12 Draft HEAD and ZIP | Must come from latest exact-head CI; older green RC11 is not RC12 proof |
| Deployed Version / exact checksum | **UNVERIFIED** |
| DB Version / indexes / relevant rows | **UNVERIFIED** |
| Migration State and options | **UNVERIFIED** |
| Active WordPress/config/providers/logs | **UNVERIFIED** |
| Live Verification Status | **NOT PERFORMED** |

1. Last connector inventory showed **no registered WPVibe WordPress site**, and `https://sabrihomeopathy.com` failed via **Cloudflare HTTP 530**. This is a symptom only; root cause (1xxx diagnostics/Ray ID, DNS/origin/security/relay) has **not** been established. Do not invent or bypass it.
2. Need read-only Live Reality Freeze: active plugin/version and deployed ZIP/checksum, actual DB tables/options/rows, migration status and runtime logs. Compare to source **after** freeze. If parity differs, perform Deployment-Parity Audit before debugging code.
3. Staging backup/restore/migration/rollback, real File00/10/17/19/20/24/25/26 and conditional CF03/CF05 integrations, 58 central mapped CV requirement acceptance, real access/privacy UI/RTL/load/concurrency tests and two independent final reviews **not complete**.
4. Founder dated deployment approval and exact source→package→deploy→live retest evidence **not present**. Draft PRs stacked, unmerged; no production modifications authorized on a guessed baseline.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

**Disposition:** Corrective source candidate only; repository tests ≠ staging acceptance, live deployment or operational resolution.
