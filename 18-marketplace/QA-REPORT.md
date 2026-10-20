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
