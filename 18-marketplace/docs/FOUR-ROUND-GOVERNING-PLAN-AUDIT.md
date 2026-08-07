# File 18 — Four-Round Governing-Plan Audit

Corrective runtime: **2.1.0**; schema: **2.1.0**; contract: **1.2.0**; release candidate: **RC3**.

## Governing sources

1. Definitive Integrated Master Plan v3.0.
2. Consolidated All-Chats Recovered Directives.
3. Continuous Value / Global Top-20 Superset Master Plan v1.0.
4. File 18 Marketplace Complete Master Plan v1.0.

The previous 2.0.1 candidate was re-opened rather than accepted on its earlier completion claim. Each round below reviewed the corrected source produced by the preceding round. A defect remained a release blocker until corrected and re-tested.

## Round 1 — Top-20 value inventory, marketplace completeness and user surfaces

New defects found in the prior candidate:

- CV-206 structured regulated-product evidence was not implemented end-to-end: ingredients, manufacturer, licensing/registration, batch/expiry where applicable, claim/source evidence and reviewer approval were missing.
- CV-210 recall/takedown lifecycle was missing: item/batch suspension, buyer notice event, regulator/reference path and public warning were absent.
- CV-208 Seller Studio was incomplete: inventory, privacy-safe inquiry/SLA bridge, listing-quality signal and report count were not exposed as one seller workspace.
- Discovery lacked explicit language, availability, product/service/digital type, seller-status and rights-safe facets.
- Safety-report taxonomy was narrower than the Top-20/global moderation constitution.

Corrections:

- Added File-18-owned `mkt_listing_evidence`, `mkt_recalls` and `mkt_retention_holds` governance domains.
- Added seller evidence capture, human review, public approved evidence, strict regulated-category publication gate and cure-claim controls.
- Added recall activation/resolution, automatic active-listing pause, buyer/seller notification events, public recall warning and regulator reference.
- Added Seller Studio inventory/quality/reports plus a File 17 privacy-safe inquiry/SLA adapter; unavailable companion metrics are shown as unavailable rather than invented.
- Added complete local search facets and public filter UI while retaining File 26 as global search owner.
- Expanded reporting to fraud/scam/counterfeit/non-delivery/unsafe or false claims/impersonation/privacy/harassment/abuse/copyright/child safety/illegal item/misleading price/other.

## Round 2 — trust, identity, privileged operations and fail-closed dependencies

New defects found after Round 1 correction:

- the identity adapter could overwrite an authoritative provider `available=false` signal with local optimism;
- malformed/unsupported identity status was not rejected explicitly;
- high-trust operational routes such as System Check/Repair needed fresh File 00 revalidation in addition to WordPress capability checks;
- regulated medicine selling needed verified-professional evidence rather than any verified account.

Corrections:

- authoritative identity unavailability is preserved and incompatible/malformed assertions fail closed;
- roles, capabilities, risk and status are normalized and compatibility is explicit;
- high-trust operational, policy and moderation paths revalidate fresh File 00 capability/risk/suspension state;
- regulated medicine policy requires a currently verified professional assertion plus structured evidence and human review.

## Round 3 — concurrency, replay, reliable events, migration and transaction integrity

New defects found after Round 2 correction:

- stale `processing` idempotency records could be reclaimed by competing retries;
- outbox records could remain permanently stuck in `processing` after a worker crash;
- external event IDs could be replayed with changed payloads unless explicitly rejected;
- failed inbox events needed safe reclaim rather than silent permanent loss;
- legacy File 17 handoff processing lacked a recoverable concurrency lease;
- scheduled listing/offer/seller state changes could commit even if the corresponding outbox fact failed to persist;
- admin moderation paths needed the same atomic owner-state + outbox discipline as REST mutations.

Corrections:

- idempotency stale/failed reclaim now uses compare-and-swap conditions;
- outbox has a bounded processing lease, stale recovery, retry and dead-letter behavior;
- inbox rejects event-ID/payload conflicts and can safely reclaim failed work;
- File 17 handoff ledger uses conditional processing claims and stale-lease recovery;
- REST owner mutations, scheduled expiry/reconciliation and admin moderation now commit state plus outbox inside native File 18 database transactions;
- optimistic state updates are checked before success/audit/event emission.

## Round 4 — privacy, retention, publication bypasses, UI completion, source/release truth

Fresh final audit after the first three correction rounds found and corrected:

- runtime legal/safety holds were absent, so retention/privacy erasure could not preserve a documented held record;
- privacy export was unbounded;
- old/draft records could still reach publication with unknown (`und`) language through a non-UI API path;
- an active recall needed an explicit final publication/reactivation gate;
- regulated evidence needed an actual seller/reviewer UI, not API-only implementation;
- public listing/archive/dashboard surfaces needed evidence, recall and Seller Studio presentation;
- prior 2.0.1/RC2 release documentation and QA assertions became stale once these corrections were made.

Corrections:

- added documented, time-bounded retention/legal/safety holds with authority reference, audited release and hold-aware retention/privacy behavior;
- privacy export is batched;
- final publication gates reject missing/unknown language, missing approved regulated evidence and active recall;
- seller evidence and moderator-review forms are accessible from the seller workspace; approved evidence and active recall are shown publicly;
- archive filters and Seller Studio are exposed in the user experience;
- direct-source architecture remains mandatory; no encoded/base64 reconstruction is accepted;
- release identity advanced to 2.1.0 RC3 and static/four-plan QA was expanded to test the new controls.

## Post-correction fresh review A

After the final runtime changes, the corrected source was re-read specifically for canonical ownership, duplicate backend risk, paid/donor ranking influence, identity fail-closed behavior, publication bypass, state/outbox atomicity, legal-hold behavior and privacy exposure. No additional known blocker was accepted without correction.

## Post-correction fresh review B

The exact corrected head must pass direct-source supply-chain checks, PHP syntax on the supported matrix, JavaScript syntax, state-machine tests, contract tests, source-architecture tests, adversarial tests, four-plan invariant tests and two deterministic release builds. Green CI evidence is required before this document may support an Automated-QA Green claim.

## Status boundary

This audit can establish only **Specified**, **Coded**, **Packaged** and **Automated-QA Green** when exact-head CI is green. It does not establish **Staging-Accepted**, **Live-Deployed** or **Operational** status. Hostinger staging, real companion integrations, two-account workflows, browser/device/accessibility evidence, backup/restore, rollback rehearsal, Founder acceptance, controlled live deployment and operational monitoring remain separate gates.
