# Changelog

## 2.0.1 — Four-plan corrective candidate
- Enforced approved + verified high-trust identity for protected Marketplace actions and fail-closed high-risk states.
- Removed default featured-label ranking privilege; kept labels presentation-only.
- Declared single-free-tier, donation-non-privilege and no-paid-ranking invariants in contracts/status.
- Made idempotency retry claims atomic and recoverable after stale processing expiry.
- Made external event inbox detect payload conflicts and safely retry failed events.
- Consumed File 25 primary visual token with green fallback and avoided Back/Home duplication when File 20 owns context navigation.
- Materialized direct canonical source and removed base64 reconstruction artifacts.
- Added four-plan invariant tests and four-round audit record.
- Revalidated dispute-reviewer deal access against current File 00 assertions; made upgrade locking atomic; bounded legacy migration batches; disabled writable promotions; revalidated policy/settings writes.

## 2.0.0 — Final-plan architectural correction

### Added
- Final File 18 canonical entities and state machines.
- File 00 identity assertions and fail-closed protected actions.
- File 17 product-context conversation adapter and legacy chat handoff ledger.
- File 19 notification-event adapter.
- File 20 shell/navigation and File 26 search-provider registration.
- File 24 assurance hooks and System Check evidence.
- Versioned outbox/inbox, idempotency, audit hash chain, retention and reconciliation.
- Public listing UI, seller workspace, private dashboard and private deal surface.
- REST `marketplace/v1`, structured errors and trace IDs.
- Privacy exporter/eraser, noindex/no-store participant routes and non-destructive uninstall.
- Deterministic package tooling and two-round review evidence.

### Corrected
- Removed parallel File 18 conversation/message/reaction/block ownership from the active schema.
- Removed all commission calculations and legacy 10% behavior; 0% is an invariant.
- Replaced self-claimed seller trust with current File 00 assertions and native state checks.
- Added atomic optimistic-version transitions and listing-row locking during offer acceptance.
- Added policy review for regulated products and false-cure/patient-data controls.
- Added current listing status propagation into search, context cards and deals.

### Compatibility
- Legacy `smp_*` seller/product records are imported non-destructively.
- Legacy conversation/message IDs are recorded for File 17 handoff; data is not silently deleted.
- Old routes may be redirected during controlled staging cutover.

### Final corrective review additions
- Added canonical replay protection to every state-changing REST endpoint.
- Added sold-out competing-offer reconciliation and stricter reviewer/deal-transition scope.
- Added participant dispute export and audit-chain verification.
- Added least-privilege moderation queues and state-valid deal actions.
- Added two documented review/fix rounds and expanded adversarial automated tests.
