# File 18 — Corrective QA Report

**Date:** 20 October 2026  
**Candidate:** Sabri Marketplace 2.1.0-RC4  
**Status:** Fresh correction/source regression; staging and production validation still required.

## Reviewed and corrected

The new forty-scope source review found six control domains with source defects and additionally hardened failed transaction START/COMMIT return handling. Seven corrective findings cover: composite listing pagination, four internal REST nonce exceptions, transactional external inbox acknowledgement, explicit event delivery acknowledgement, audit-chain lock failure, and safe DB transaction boundaries.

Regression enforcement: `tests/test-october-40-scope-regressions.php` runs within `qa-static.sh` and on both PHP 8.1 and PHP 8.3 in `.github/workflows/quality.yml`.

## Quality evidence boundary

**Read GitHub Actions run for the final exact corrective branch HEAD before marking automated QA green.** The previously successful run on the earlier source commit does not establish that future documentation/source commits passed. Final RC4 ZIP, manifest and SHA-256 must derive from that same build.

Static source checks do not verify WordPress/MySQL behavior, staging install/upgrade, compatibility with real File 00/17/19/20/24/25/26, File 19 notification receipt under explicit `mkt_event_acknowledged` semantics, privacy export/erase, browser/RTL, mobile accessibility, load, real backup restore, rollback or live operation.

**Remaining acceptance:** Staging and Founder approval, followed by controlled production rollout and live re-test. No production-ready assertion.

## Source and cross-file audit extension (20 October 2026)

The new independent File 18 review compared F18-FR-001…020, F18-NFR-001…010, and ten central/companion ownership boundaries and detected six additional implementation gaps. All six have source corrections on the corrective Draft PR. `tests/test-october-40-scope-regressions.php` additionally verifies deal ID, migration/schema and event boundary changes; `tests/test-file19-notification-contract.php` exercises the canonical File 19 producer and envelope through isolated public-function fixtures. The CI workflow now runs 40 sequential aggregate source/regression passes per PHP matrix member, evidence subject to final HEAD confirmation. No browser/WordPress/MySQL/deployed parity evidence is implied.

## Third review / RC5 corrective QA (21 October 2026)

See root `docs/OCT-21-2026-THIRD-40-SCOPE-CORRECTIVE-REVIEW.md`. Five additional source-defect classes corrected; targeted PHP tests (`test-report-atomicity.php`, `test-audit-transaction-lock.php`) simulate failed Outbox/Audit/readback and COMMIT/ROLLBACK/nested transaction/lock ownership. Both are included in the full aggregate QA and direct PHP 8.1/8.3 matrix. The forty scoped inspections find source implementation anchors; the repeated forty complete aggregate QA executions are separate and not staging or functional acceptance. RC5 ZIP/SHA must match final exact HEAD and not the previous RC4. Live WordPress/MySQL, real cross-file workflows, Hostinger-equivalent staging, migration dry-run, backup restore and Founder approval are still unverified.

## Fourth plan/companion review: RC6 static and negative tests (21 October 2026)

`tests/test-outbox-required-notification-ack.php` simulates actual outbox worker handling where domain acknowledgement succeeds but File19 required notification fails, successful receipt, multiple routing cases, delayed retry and dead-letter. `tests/test-money-stock-validation.php` directly executes listing normalization and domain policy against missing/non-numeric, negative/non-finite and valid values. Commerce source assertions cover both offer and counteroffer finite guards. Full aggregate test script and distinct GitHub quality steps include these tests; workflow is configured for 40 consecutive complete runs per PHP matrix entry. See `docs/OCT-21-2026-FOURTH-40-SCOPE-RC6-REVIEW.md` for limitations and blockers. This remains **repository candidate**, not live or staging proof. RC5 hashes cannot be used for RC6.

## Fifth audit negative tests / File17 and idempotency (21 October 2026)

Tests `test-file17-native-context-bridge.php` and `test-idempotency-durable-receipt.php` are independent runtime-shaped PHP fixtures covering cross-owner conversation create/context attachment, current price projection and changed-price freshness, denied/removed actor/seller/listing, partner outage, File19 SUN availability and failed/completed idempotency receipt updates. Both run directly in the PHP 8.1/8.3 quality workflow and in 40 counted aggregate QA rounds. These stubs are **not** real File17/SUN WordPress+MySQL staging acceptance. See root fifth forty-scope report for exact owner/live blockers and source review bounds. Candidate 2.1.0-RC7 requires last-head deterministic ZIP SHA-256.

## Seventh 40-scope review: legal, recall and seller transaction regressions

The RC9 QA bundle now includes `test-governance-recall-hold-atomicity.php`, `test-seller-atomicity.php` and `test-seventh-40-plan-anchors.php`. These execute actual File18 governance and seller methods using a fake WPDB with audit failures, failed inserts, failed CAS update/readback, duplicate active-hold race injected after lock acquisition, and deferred named lock release; a separate register explicitly maps 40 FR/NFR/central owner source anchors. They are NOT real concurrent MySQL/WordPress integration tests. Check final exact source HEAD, both 40-pass CI logs and RC9 SHA before promoting candidate; user-visible production status remains unverified.
