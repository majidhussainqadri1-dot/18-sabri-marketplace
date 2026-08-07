# File 18 — Fresh Adversarial Review and Fix Round 2

**Date:** 7 August 2026  
**Candidate:** 2.0.0-RC1

## Attack and failure perspectives

The corrected candidate was re-reviewed for CSRF, replay, stale state, concurrent offer acceptance, IDOR, seller suspension, final-unit inventory, reviewer overreach, duplicate backends, provider outage, private-route caching, privacy-rights coverage, audit tampering, migration loss and misleading completion claims.

## New defect found and corrected

- **Repair REST mutation bypassed the common mutation gate.** Although the route required an administrator, it did not pass through the module’s explicit nonce, rate-limit and idempotency wrapper. The endpoint now uses the same protected mutation pipeline as all other state-changing REST operations.

## Adversarial confirmations

- Mutations reject absent/invalid security tokens under cookie authentication and require bounded rate limits.
- Reused idempotency keys with altered payloads fail with conflict; simultaneous duplicates do not execute twice.
- Offer acceptance locks the listing row, rechecks seller approval and current listing status, and uses optimistic versions.
- File 18 contains no conversation, message, call, wallet, escrow, payout or notification-delivery database.
- Private dashboard/deal routes are noindex and no-store; public DTOs are allowlisted.
- Reports and disputes have structured, versioned and appeal-aware state machines.
- Outbox records are not marked completed without an explicit platform, notification or local-consumer acknowledgement.
- Uninstall is non-destructive by default; legacy import and File 17 handoff are resumable and preserve source data.
- Zero platform commission is enforced in code, schema, copy, contracts and tests.

## Result

The complete automated regression suite passes after the final correction. No known unresolved blocker or critical code defect remains within the locally testable scope. Production completion is not claimed before Hostinger staging and the final acceptance gates.
