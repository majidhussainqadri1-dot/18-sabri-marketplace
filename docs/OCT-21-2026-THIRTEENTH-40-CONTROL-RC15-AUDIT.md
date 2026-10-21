# File18 Marketplace — Thirteenth Forty-Control Source/Plan Review (21 Oct 2026)

## Truth snapshot and plan basis
- Exact source baseline: `0e1ee9a2425a93eac2792158f77b1c41bba8a3c6` (RC14 Draft PR #14, previously green on both PHP 8.1/8.3 for 40 full-suite passes).
- Corrective candidate: `fix/file18-thirteenth-40-control-2026-10-21`, **Draft PR #15**, stacked on Draft PR #14, not `main`, not deployed.
- Source documents read: supplied `18-File-18-Marketplace-Complete-Master-Plan-2026-v1.0(1).docx`, governing `Sabri-Social-Homeopathy-Platform-Three-Central-Plans-Consolidated-Governing-Master-Plan-2026`, File17 plan, conditional CF05 and CF06 plans. File18 plan explicitly requires legal hold exception and auditable scope-specific retention; tamper-evident logs with retention limits; verified backup/restoration and no live changes without Founder approval. Central governing plan requires immutable audit/retention integrity and production release stages. **These documents are normative requirements, not evidence that live code implements them.**
- The source review distinguishes **40 named source/plan scope checks** (table below) from the **40 whole QA suite executions per PHP version**. Source checks are not 40 independent exhaustive manual end-to-end reviews, nor all 58 related central CV acceptance tests.

## Forty scoped source/owner assessments
| # | ID | Source/plan dimension | Source location/owner | Evidence boundary |
|---:|---|---|---|---|
| 1 | FR-01 | Seller identity approval, adult/guardian and risk | MKT_Auth + File00 | Actual deployed File00 contract/version unverified |
| 2 | FR-02 | Seller create and optimistic version | MKT_Listings::ensure_seller | WP/MySQL tests needed |
| 3 | FR-03 | Create and update listing | MKT_Listings | Actual owner action/replay verification pending |
| 4 | FR-04 | Listing submission policy/rights | MKT_Listings | Real File10 provider state acceptance pending |
| 5 | FR-05 | Approval/rejection/review workflow | MKT_Governance | Human reviewer role acceptance pending |
| 6 | FR-06 | Regulated product evidence | MKT_Governance | Legal/product licensing approval pending |
| 7 | FR-07 | Media owner rights scan and ref | MKT_Listings/File10 | Real File10 concurrency and callback pending |
| 8 | FR-08 | Public search bounds and keyset cursor | MKT_Finalization | Large-data/RTL browser smoke pending |
| 9 | FR-09 | Listing DTO private/public separation | MKT_Listings | Cache/index leakage review pending |
| 10 | FR-10 | Safe bookmarking and counts | MKT_Listings | Actual session replay pending |
| 11 | FR-11 | File17 buyer/seller contextual chat | MKT_Integrations | Live File17 provider bridge not confirmed |
| 12 | FR-12 | Offers and counteroffers CAS | MKT_Commerce | MySQL two-user race pending |
| 13 | FR-13 | Direct deal immutable snapshot | MKT_Commerce | Owner snapshot acceptance pending |
| 14 | FR-14 | Zero commission/no paid promotion | MKT_Contracts | Deployed promotion config unverified |
| 15 | FR-15 | Payment status owner events | MKT_Events | Provider signed sandbox not run |
| 16 | FR-16 | Abuse reports/review taxonomy | MKT_Moderation | Moderator UAT and IDOR pending |
| 17 | FR-17 | Dispute workflow/appeal evidence | MKT_Moderation | CF02 conditional appeals boundary |
| 18 | FR-18 | Recall and takedown safety | MKT_Governance | Live public notice and index invalidation pending |
| 19 | FR-19 | Seller Studio operations | MKT_Governance | Real dashboard accessibility pending |
| 20 | FR-20 | Expiry/maintenance/repair | MKT_Maintenance | New pause SQL rollback tested as simulated |
| 21 | NFR-01 | Object-level auth and CSRF | MKT_Auth | WP browser pen test pending |
| 22 | NFR-02 | Exporter and eraser privacy/legal holds | MKT_Privacy | Live privacy export job/holds pending |
| 23 | NFR-03 | Retention law with active hold evidence | MKT_Maintenance | Fail closed on unavailable retention hold; legal checkpoint acceptance pending |
| 24 | NFR-04 | Tamper evident append-only audit | MKT_Audit | Arbitrary DELETE removed; lawful archival still pending |
| 25 | NFR-05 | Real schema and migration parity | MKT_DB | Installed WP DB/options unverified |
| 26 | NFR-06 | Reliable inbox/outbox worker receipts | MKT_Events | Two-worker crash/recovery staging pending |
| 27 | NFR-07 | Daily metric exact facts | MKT_Maintenance | NULL/SQL errors no longer project false 0; real CF05 future integration pending |
| 28 | NFR-08 | Backup/restore/rollback | MKT_Release_Gates | Founder and real staging restore pending |
| 29 | NFR-09 | WCAG RTL and privacy caching | MKT_Routes | Real browser/accessibility pending |
| 30 | NFR-10 | Versioned owner contracts | MKT_Integrations | Deployed File00/10/17/19 etc compatibility unknown |
| 31 | CEN-00 | File00 identity canonical owner | MKT_Integrations | Repo contract mapped, deployed parity pending |
| 32 | CEN-10 | File10 canonical media | MKT_Listings | No independent media repository |
| 33 | CEN-17 | File17 chat/message owner | MKT_Integrations | No local duplicate message storage |
| 34 | CEN-19 | File19 notification consumer | MKT_Events | Real delivery acknowledgement pending |
| 35 | CEN-20 | File20 shared nav/locale preference | MKT_Integrations | No independent language preference owner |
| 36 | CEN-24 | File24 assurance/logging | MKT_Release_Gates | Release evidence acceptance pending |
| 37 | CEN-25 | File25 RTL/design tokens | MKT_Routes | Actual browser acceptance pending |
| 38 | CEN-26 | File26 global search/index owner | MKT_Integrations | Index refresh / recall projection pending |
| 39 | CF05 | CF05 future derivative analytics-only | MKT_Maintenance | Daily operational owner projections not falsely zero; conditional activation pending |
| 40 | CF06 | CF06 future translation/l10n-only | MKT_Finalization | Original language owner, CF06 not activated |

## Three confirmed source defects corrected in RC15
1. **R13-01 / Unsafe blind retention plus broken immutable audit chain.** Previously `MKT_Maintenance::purge_retention` permanently `DELETE`d old rows directly from `mkt_audit` regardless of hash chain, and when `retention_holds` was missing it omitted the `NOT EXISTS` hold guard and still anonymized report/dispute evidence. Now no uncheckpointed audit records are pruned; the audit policy records `mkt_retention_audit_prune_state=verified_checkpoint_required` as explicit unresolved legal/operations requirement. Missing hold guard prevents report/dispute minimization, visible `mkt_retention_last_error`. Both minimization statements are within one DB transaction and failed SQL rolls back. **Caution: this prioritizes preserving legal/audit integrity over automatic audit-retention pruning. A proper audited chain-checkpoint/archive retention protocol requires separate approved design and staged legal review.**
2. **R13-02 / Seller suspended without pausing active listings.** `MKT_Maintenance::reconcile_sellers` ignored a `false` result from updating active listings to `paused`; seller status and outbox could commit even as prohibited public listings remained active. Now false throws inside the owner transaction and rolls back seller status, with a distinct audit failure fact.
3. **R13-03 / Derived Metrics falsified zero on SQL outage and partial writes.** Daily `metrics_daily` snapshots cast null/error COUNT to integer zero and replaced rows one-by-one without transaction/recovery. Now read all source counts and reject any unknown/SQL error before mutation; all derived metric writes commit atomically. `mkt_metrics_last_error` records delayed/retryable failures. CF05 remains conditional future analytics owner; this fixes File18-native daily operational projections only.

## Targeted source-class fault injection coverage
- `tests/test-retention-chain-hold-failclosed.php`: no audit row DELETE, legal-hold missing blocks sensitive updates, healthy hold `NOT EXISTS`, second SQL failure rolls back first, retryable error.
- `tests/test-maintenance-seller-pause-rollback.php`: seller suspension SQL pause false rolls back version/status and suppresses status-outbox; healthy path transitions once.
- `tests/test-derived-metrics-failclosed.php`: NULL count, error count and failed third projection write never publish partial or false zero; success produces correct values.
- These tests use the **actual File18 maintenance class** and synthetic WordPress/MySQL responses. They do NOT prove real InnoDB concurrency, live migration, legal retention schedule, backups or cross-owner integrations.
- Full QA suite is **37 stages**, and workflow is configured to repeat 40 complete passes per PHP 8.1 and 8.3 and build deterministic `18-marketplace-2.1.0-RC15.zip`. **Final exact HEAD/CI and zip checksum must be verified after the final commit.**

## Explicit blocked gates
1. Installed WordPress File18 plugin/runtime/version/deployed source and artifact SHA; live DB version/tables/columns/rows/options/migration; error logs/dependencies and active File00/10/17/19/20/24/25/26 versions **not frozen**. Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
2. WPVibe inventory previously had no connected WordPress sites and domain `sabrihomeopathy.com` yielded Cloudflare HTTP 530. Cloudflare 1xxx code/Ray ID and authoritative host routing/origin evidence absent. No security bypass or live mutation justified.
3. The prescribed **audit hash-chain-safe retention checkpoint/archive** is not implemented; deliberately prevents unsafe legacy audit pruning but must be resolved through policy owner, legal review, MySQL staging, and Founder approved Change Control.
4. Real File00/10/17/19/20/24/25/26 integration tests, File17 legacy handoff, File19 delivery ACK, conditional CF05/CF06 enablement, 58 related central CV acceptance IDs, real WordPress Privacy exporter/eraser, backup restore/rehearsal, legal hold decisions, two independent final reviews **not completed**.
5. Draft PR #15 is unmerged. Main repo HEAD at review start `04a5872e3e2e170ee400e7c607e39abd5484df65`; **Repository HEAD != Deployed Version** until checksum-parity proven.

## Mandatory incident ledger
| Fact | Reality |
|---|---|
| Repository main | `04a5872e3e2e170ee400e7c607e39abd5484df65` |
| RC14 reviewed source | `0e1ee9a2425a93eac2792158f77b1c41bba8a3c6` |
| RC15 correction HEAD/CI | To pin from latest successful exact-head run |
| Deployed Version | UNKNOWN |
| DB Version | UNKNOWN |
| Migration State | UNKNOWN |
| Live Verification Status | NOT PERFORMED |

**Status:** Repository corrective candidate, not staging-accepted, not live-deployed, not operationally resolved.
