# File 18 — Sabri Marketplace

**Runtime:** 2.0.0  
**Schema:** 2.0.0  
**REST:** `marketplace/v1`  
**Canonical package folder:** `18-marketplace`  
**Commission:** **0%**

This is the implementation of **File 18 — Marketplace** for the Sabri Social Homeopathy Platform. It is a canonical domain module, not a separate application shell and not a parallel messaging system.

## Ownership

| Domain | Canonical owner |
|---|---|
| Listings, seller linkage/projection, prices, availability, offers, deal records, listing moderation, reports and disputes | **File 18** |
| Identity, age/guardian status, account approval, suspension and capabilities | File 00 |
| Product-linked conversations, messages and calls | File 17 |
| Notification projection and delivery | File 19 |
| Header, navigation, route layout and Safe Mode coordination | File 20 |
| Security/privacy assurance evidence | File 24 |
| Global visual tokens/components | File 25 |
| Global search orchestration | File 26 |

File 18 does **not** create conversation, message, call, wallet, escrow, payout, courier or notification-delivery tables.

## Implemented capabilities

- Governed category and prohibited-item policies.
- Verified-entry seller eligibility with guardian, suspension and risk rechecks.
- Listing draft, review, active, pause, sold/unavailable, expiry, rejection, removal and appeal states.
- Secure media references through the central media owner; no raw duplicate media ownership.
- Public faceted listing search and canonical listing pages.
- Saves, shares and purpose-limited reports.
- Product-linked File 17 conversation context cards.
- Structured offers, counters, acceptance, decline, withdrawal and expiry.
- Atomic direct-deal snapshots and participant-scoped deal transitions.
- Purpose-limited disputes and moderation decisions with appeals.
- Versioned events, reliable outbox/inbox, idempotency and reconciliation.
- Public/private DTO allowlists, noindex/no-store participant routes and privacy export/erasure.
- Tamper-evident audit chain, retention, metrics, System Check, repair dry-run and safe mode.
- Mobile-first, RTL-ready, keyboard-accessible UI with green central brand accent.
- Deterministic build, manifest, checksum and static/contract QA.

## Truthful completion status

The source, deterministic package and automated QA can be completed in GitHub. **Staging-accepted, live-deployed and operational** are separate statuses and require Hostinger staging, real companion modules, real users, browser/device testing, backup/restore/rollback proof and Founder approval.

See:

- `docs/ARCHITECTURE.md`
- `docs/API-EVENT-CONTRACTS.md`
- `docs/MIGRATION-ROLLBACK.md`
- `docs/STAGING-ACCEPTANCE.md`
- `docs/REQUIREMENTS-TRACEABILITY.md`
- `docs/OPERATIONS-RUNBOOK.md`
- `docs/SECURITY-PRIVACY.md`

## Review and QA evidence

- `docs/CORRECTIVE-REVIEW-ROUND-1.md`
- `docs/ADVERSARIAL-REVIEW-ROUND-2.md`
- `QA-REPORT.md`
- `STATUS.md`
