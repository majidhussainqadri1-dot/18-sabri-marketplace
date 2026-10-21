# File18 Marketplace — Eleventh 40-Control Source Review / RC13 (21 Oct 2026)

## Exact repository source and plan precedence
- Audited starting **RC12 green source HEAD** `ed5c769955970fa1cb7abf345116e492501ec425`, without treating that HEAD as deployed WordPress truth.
- Governing documents actually compared: uploaded **File18 Marketplace Complete Master Plan** (including its central harmonization appendix); **Three Central Plans Consolidated Governing Master Plan** (no paid/donor visibility, zero commission, single canonical owner, AJ-23 and AJ-40); **File17 Communication Network**, **CF05 conditional Analytics**, and freshly supplied **CF06 v1.1 Localization Translation Operations, Future40**.
- The newly reviewed CF06 plan explicitly reserves File20 for language preferences/shell, File25 for visual RTL components, File26 for transliteration/search, domain files for original meaning/publication, and CF06 for optional future translation workflows/bundles. **File18 owns original listing language as source metadata only.** Do not turn CF06 source work into a File18 translation engine or activate its conditional Future40. CF06 plan specification ≠ code/deployment/approval.
- Correction branch `fix/file18-eleventh-forty-source-review-2026-10-21` → **Draft PR #13** stacked upon unmerged Draft PR #12. Runtime remains version 2.1.0; distinct release candidate filename `18-marketplace-2.1.0-RC13.zip`.

## Forty plan/source controls and required separate cross-file boundary
There are **40 individually keyed static/source owner checks** in `tests/test-seventh-40-plan-anchors.php`: 20 functional (`F18-FR-001..020`), 10 nonfunctional (`F18-NFR-001..010`), 8 canonical partner owner checks (`CEN-OWN-00,10,17,19,20,24,25,26`), and 2 central safety/retention checks (`CEN-LEGAL-HOLD, CEN-RECALL`). Each control checks its specific actual source markers, but presence is **not** proof of end-to-end behavior or completeness of all **58 mapped CV** requirements.

Specific cross-file comparison:
1. **File00**: seller/actor claims, status and guardian verification are asserted through provider; no duplicate identity owner.
2. **File10**: media references/rights/scan condition and remote release, not Marketplace-owned media storage.
3. **File17**: owns conversations/messages; File18 only context handoffs.
4. **File19**: owns notification preferences/intake, consumer acknowledgement rather than webhook/action presence.
5. **File20**: owns global shell and language preference; File18 only search listing/route projection.
6. **File24**: global assurance evidence registry, not File18 authorization bypass.
7. **File25**: owns design system/RTL; actual mobile/keyboard/zoom/browser acceptance still missing.
8. **File26**: owns global search/index; File18 source provider queries remain separately versioned.
9. **CF05**: conditional aggregate derivative analytics; no direct independent canonical marketplace warehouse.
10. **CF06**: conditional translation/bundle owner, no File18 editing/publishing external translations or seizing locale preferences (new direct `test-cf06-conditional-boundary.php`).

The actual CI workflow additionally runs the **complete 33-stage static/contract and fault-injection QA suite 40 times per PHP 8.1 and PHP 8.3**. These are 80 repeated automated checks only **once proven by the final exact-HEAD run**, not 40 independent full manual audits, WordPress staging tests or 58/58 central acceptance.

## Two newly confirmed source defect categories corrected
**R11-01 — Fail-open media rights/scan approval on SQL failure.**
- Exact previous code: `MKT_Listings::media_ready()` cast both SQL `COUNT(*)` reads to integer zero. A failed/null unsafe-media count became zero and could allow listing publication without verifying rights and clean scan. `MKT_Listings::media()` also called `array_map()` on a possible NULL failed result.
- Corrected: verify both media SQL reads were successful and non-NULL before any approval, **return false** on failure; fail explicitly when a media projection query fails, never a fictional approved empty list.
- Actual class fault fixtures cover missing total/unsafe query, legitimate zero/approved/unsafe media and broken media rows.

**R11-02 — Search DB errors misclassified as empty/expired / PHP count(null).**
- Both `MKT_Listings::search()` and `MKT_Finalization::search_array()` counted unchecked `$wpdb->get_results()`; a SQL error or NULL could break public search. A failed anchor query was returned as `cursor_expired`; the expanded REST endpoint risked HTTP 200 while underlying search failed.
- Corrected: check query arrays **and** WordPress `last_error`, distinguish `search_unavailable` from genuine empty or expired cursor, make enhanced public REST endpoint return **HTTP 503** for temporary SQL unavailability.
- Actual class mock-DB and REST tests in `tests/test-search-media-sql-failure.php` prove no false-empty/expired response, 503, successful empty results, verified safe/unsafe media and SQL failures.

## Release blockers / mandatory truth ledger
| Evidence | Current status |
|---|---|
| Repo main HEAD (review baseline) | `04a5872e3e2e170ee400e7c607e39abd5484df65` |
| Starting prior green RC12 source | `ed5c769955970fa1cb7abf345116e492501ec425` |
| RC13 final source HEAD, matrix CI, package SHA | **To verify from exact last commit's CI after documentation updates** |
| Deployed Version + active plugin package/checksum | **UNVERIFIED** |
| Live DB Version/schema/rows, wp_options, Migration State | **UNVERIFIED** |
| Live runtime logs and exact partner versions | **UNVERIFIED** |
| Live Verification Status | **NOT PERFORMED** |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

Known external blockers are WPVibe WordPress inventory showing no registered site and repeat Cloudflare **HTTP 530** when contacting `https://sabrihomeopathy.com`. This status by itself does **not** establish DNS, origin, Cloudflare 1xxx error, hostname settings, hosting permissions or WordPress root cause. Need a diagnostic Ray ID/1xxx plus actual Hostinger DNS/origin evidence or independent verified staging host to fix connectivity. Never remove security settings based only on 530.

Additional unremoved release gates: live deployed/DB parity freeze; real isolated WP/MySQL tests of simultaneous publishing/rights and search outage; companion File00/10/17/19/20/24/25/26 contracts in installed environments; conditional CF05/CF06 status/change-control; all relevant 58 CV acceptance journeys; two fresh post-change independent code reviews; staging backup/restore/rollback; documented Founder approval; signed/verified package→deployment parity; live re-test.

**Status: source-level corrective Draft PR only; no production patches/deploys, no live defect marked resolved.**
