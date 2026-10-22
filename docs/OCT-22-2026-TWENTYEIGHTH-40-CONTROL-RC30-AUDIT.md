# File 18 — RC30 Twenty-eighth distinct 40-control Source and Governing-Plan Audit

**Date:** 22 October 2026. Source baseline RC29 candidate `9fb5a15b61eeb064340185c8ab6275ee2042528c`. Separate GitHub `main` `04a5872e3e2e170ee400e7c607e39abd5484df65`. **Neither is the installed live plugin until deployed-source parity is proven.**

## Sources examined

- File18 Marketplace Complete Master Plan v1.0, original attached Word plan: canonical Listing/Deal/moderation owner, 0% commission, auditable atomic transitions, test/rollback and separate staging/live readiness.
- Three Central Plans Consolidated Governing Master Plan v3.0: canonical owner law, release acceptance, auditability, defect correction with regressions, seven distinct delivery statuses.
- File17 Communication plan: retains message and conversation authority.
- Conditional CF05 analytics and CF06 localization: no premature duplicate canonical owners.
- Related File00/10/19/20/24/25/26 integration contracts are **source-level assertions**, not verified actual live companion interfaces.

## Two newly confirmed source defect groups and RC30 resolution

1. **Audit verifier false-green on failed SQL:** `MKT_Audit::verify_chain()` previously converted a failed or null SELECT to empty [] and returned `valid=true, checked=0`. This can make release/assurance evidence misleading. Now it resets and checks database `last_error`, rejects non-array SELECT results, and returns `valid=false` with a machine-readable `audit_chain_query_unavailable` marker rather than counting failure as empty history.
2. **Unverified new chain genesis on failed predecessor read:** `MKT_Audit::record()` cast a failed/null predecessor hash to `''` and could write an apparently new genesis audit record despite existing chain history. Now it checks SQL errors and permits empty genesis **only** after independent `COUNT(*) = 0` confirmation; failed count, conflicting count, malformed hash, or unavailable predecessor throws before writing and releases connection-owned lock. Valid first-ever and nonempty-chain append paths retained.

**New regression:** `tests/test-rc30-audit-source-failclosed.php` tests SQL error, silent null, false genesis with existing records, unreadable count, invalid predecessor, legitimate genesis/append, verifier fail-closed, valid empty case and tampered row. The test uses a WordPress DB fake; genuine MySQL staging under lock contention remains an external release gate.

**Automated QA target:** 59 distinct static/contract/owner fault-injection stages, **40 whole-suite runs on PHP 8.1 plus 40 on PHP 8.3**. This is not 40 independent human reviews and is not the central plan's separate 58 real acceptance criteria. CI output and reproducible RC30 archive SHA must be verified on exact PR HEAD before green claims.

## Exactly 40 named scope checkpoints

1. F18-FR-001: governed categories and prohibition policies
2. F18-FR-002: identity-owner seller eligibility
3. F18-FR-003: draft Listing creation and ownership
4. F18-FR-004: secure media ownership and rights
5. F18-FR-005: human moderation and review gates
6. F18-FR-006: atomic state/version Listing lifecycle
7. F18-FR-007: index/search facets and stale exposure
8. F18-FR-008: public listing detail and noindex/private responses
9. F18-FR-009: save/share/report and purpose binding
10. F18-FR-010: File17 product-linked communication
11. F18-FR-011: replay-resistant validated offers
12. F18-FR-012: counter-offer/negotiation versions
13. F18-FR-013: atomic zero-fee direct Deal state
14. F18-FR-014: zero commission and donation neutrality
15. F18-FR-015: direct payment/proof boundary
16. F18-FR-016: prohibited-content and fraud moderation
17. F18-FR-017: dispute/reviewer role separation
18. F18-FR-018: paid prominence disabled by governance
19. F18-FR-019: aggregated privacy-preserving seller insight
20. F18-FR-020: expiry/cron/reconciliation behavior
21. F18-NFR-001: field/object authorization
22. F18-NFR-002: privacy exporter, eraser and hold
23. F18-NFR-003: transaction, audit-chain read truth and outbox consistency — RC30 negative SQL fault-injection added
24. F18-NFR-004: indexed search/cursor/load robustness
25. F18-NFR-005: semantic UI, RTL, keyboard/accessibility
26. F18-NFR-006: health check never falsely green when audit SQL unavailable — RC30 negative SQL fault-injection added
27. F18-NFR-007: non-destructive migration and rollback
28. F18-NFR-008: safe mode/system operator
29. F18-NFR-009: PHP 8.1/8.3 and WP minimum versions
30. F18-NFR-010: Urdu/Arabic/English display and fallback
31. File00 canonical identity/guardian/risk
32. File10 canonical rights-scanned media
33. File17 canonical chat and communication
34. File19 canonical notification outbox ACK
35. File20 global shell/routes and navigation
36. File24 audit/privacy/security assurance
37. File25 global visual tokens and RTL
38. File26 global search owner vs Listing source
39. CF05 optional aggregate warehouse, not canonical Marketplace store
40. CF06 optional localization workflow, not source-content owner

## Independent QA-harness defect discovered after the first green run

An exact CI log review discovered a separate test-orchestration bug: the added RC30 stage in `qa-static.sh` contained **literal backslash-n characters** rather than actual line breaks, so the new negative test was executed by its dedicated GitHub Actions step but **not** by each of the forty full-suite repetitions. The first green run is therefore not evidence for forty RC30 audit-test executions. The script now uses actual LF-separated `echo`, `php` and following-stage lines. Only a new exact-head CI run whose logs show `[55/59]`, the RC30 test output and 40 distinct successful full-suite passes on **both** PHP versions can certify RC30. This is one QA harness defect, corrected at source level, not a third Marketplace runtime/audit defect. No live verification is implied.

## Blockers not corrected by a repository-only patch

- **Issue #17:** WPVibe still has no connected site; Cloudflare HTTP 530 on WordPress connection. Need Cloudflare 1xxx/Ray ID/origin/DNS diagnostics, deployed active plugin/version/package hash, DB tables/options/migrations, error logs and actual deployed-vs-source parity. HTTP 530 alone is not a finding of bad firewall rules.
- **Issue #18:** File24/legal/security/Founder approval for immutable audit-chain signed checkpoint, hold-aware archive/retention and verified recovery. Do not re-enable raw audit pruning or fabricate policy approval.
- Real staging MySQL, integration contracts, central 58 acceptance scenarios, backups/restores, independent reviews, Founder approval, package deployment and live re-test remain unverified.

**Defects in this source review:** 2 proven source defect groups; 2 patched in this candidate; 0 live-verified corrections; release remains blocked. **Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
