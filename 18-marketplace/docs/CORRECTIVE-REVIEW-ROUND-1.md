# File 18 — Corrective Review and Fix Round 1

**Date:** 7 August 2026  
**Candidate:** 2.0.0-RC1

## Scope

Requirements-to-code audit against the final File 18 plan, canonical ownership, security, privacy, structured commerce, integration boundaries, migration, operability and release truth.

## Defects found and corrected

1. **Historical duplicate communication ownership** — the former candidate owned conversations/messages/reactions/blocks. The 2.0.0 schema now owns no communication tables; product chat is a File 17 contract and legacy communication data uses a non-destructive handoff ledger.
2. **Incomplete replay protection** — offer-only keys were insufficient. Every protected REST mutation now uses the canonical `mkt_idempotency` ledger with request hashes, in-progress detection, cached success and conflicting-payload rejection.
3. **Competing offers after sale** — accepting a final-unit offer could leave unrelated open offers. Sold/unavailable acceptance now atomically declines every competing live offer for that listing.
4. **Overbroad dispute-reviewer transitions** — reviewers could attempt ordinary participant transitions. Reviewer access is now limited to disputed/resolved deals; resolution and closure follow purpose-limited rules.
5. **Unstructured disputed state** — all transitions to `disputed`, including reviewer actions, now require a valid structured dispute record.
6. **Moderation UI least privilege** — dispute-only reviewers no longer receive listing/report decision controls; marketplace moderators and dispute reviewers see only their queues.
7. **Privacy export gap** — participant disputes are now included in WordPress personal-data export; retained transaction identifiers are described honestly in erasure results.
8. **Audit-chain concurrency and verification** — audit writes use a named database lock, lock gaps are explicit, and System Check verifies the latest chain segment.
9. **Ambiguous deal actions** — the deal page now renders only state-valid participant actions and directs purpose-limited reviewers to the moderation workspace.

## Retest

PHP syntax, JavaScript syntax, CSS structural check, state-machine tests, contract invariants, source architecture, adversarial invariants, forbidden-artifact scans and release-identity checks all pass after correction.

## Result

No known unresolved static or contract defect remains from this review. Staging, real companion integrations, browser/device acceptance, backup/restore/rollback and Founder acceptance remain external acceptance gates rather than hidden code-completion claims.
