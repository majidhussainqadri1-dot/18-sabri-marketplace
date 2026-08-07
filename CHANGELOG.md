# Changelog

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
- Replaced PHP 8.2-only standalone `true` return types with PHP 8.1-compatible `bool|WP_Error` unions after matrix CI detected the incompatibility.

### Compatibility
- Legacy `smp_*` seller/product records are imported non-destructively.
- Legacy conversation/message IDs are recorded for File 17 handoff; data is not silently deleted.
- Old routes may be redirected during controlled staging cutover.
- PHP 8.1 and PHP 8.3 are both explicit automated QA targets.

### Final corrective review additions
- Added canonical replay protection to every state-changing REST endpoint.
- Added sold-out competing-offer reconciliation and stricter reviewer/deal-transition scope.
- Added participant dispute export and audit-chain verification.
- Added least-privilege moderation queues and state-valid deal actions.
- Added two documented review/fix rounds and expanded adversarial automated tests.
