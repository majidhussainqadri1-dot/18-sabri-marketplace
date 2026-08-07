# Operations Runbook

## System Check

Open **Marketplace → System Status** or call `GET marketplace/v1/system-check` as an authorized operator. Blockers include missing schema, File 00, File 17 or zero-commission invariant. Warning states include File 19/File 20 availability, cron and dead-letter events.

## Safe Mode

Enable `safe_mode` in Marketplace settings when integrity, identity, communication or payment status is uncertain. Safe Mode blocks new listings, offers and deal mutations while safe public reading remains available. It is not a substitute for fixing the incident.

## Outbox/dead letter

- Normal: `pending → processing → processed`.
- Failure: `retry` with bounded exponential delay.
- Eight failed attempts: `dead` and operator alert.
- Repair must diagnose consumer/version/privacy failure before requeueing.
- Never delete dead events without recording disposition and reconciliation.

## Common incidents

### File 00 unavailable
Protected actions fail closed. Confirm contract version/functions, identity assertion payload and cache invalidation. Do not grant local fallback seller roles to bypass identity.

### File 17 unavailable
Keep public listing pages available; disable Message seller with a clear unavailable state. Do not activate legacy File 18 chat tables.

### File 19 unavailable
Owner state remains authoritative. Outbox retries and in-app UI remains truthful. Do not duplicate a notification database.

### Listing remains searchable after removal
Confirm `MarketplaceListingStatusChanged.v1`, File 26 consumer health, cache purge and index reconciliation. Public detail must still fail closed from canonical File 18 state.

### Concurrent offer acceptance
Inspect listing-row lock, offer expected versions and deal unique accepted-offer key. Reconcile duplicate external effects, but never fabricate transaction success from a timeout.

## Backup/restore targets

Initial objective: RPO ≤24h and RTO ≤8h until staging/load evidence supports tighter values. Restore proof must include record counts, privacy classes, File 17 references, cache/index rebuild and at least one seller/buyer/deal journey.

## Named responsibilities

Before production, assign product owner, release operator, marketplace moderator lead, dispute reviewer lead, security/privacy escalation, support owner and incident on-call. Empty staffing/policy configuration is not production readiness.
