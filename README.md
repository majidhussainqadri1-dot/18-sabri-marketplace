# File 18 — Sabri Marketplace

**Runtime:** 2.1.0  
**Schema:** 2.1.0  
**Contract:** 1.2.0  
**REST:** `marketplace/v1`  
**Canonical package folder:** `18-marketplace`  
**Commission:** **0%**  
**Access tier:** **single free tier; no donor advantage; no paid ranking**

This is the implementation of **File 18 — Marketplace** for the Sabri Social Homeopathy Platform. It is the canonical Marketplace domain module, not a separate application shell and not a parallel messaging, notification, wallet, escrow or payout system.

## Ownership

| Domain | Canonical owner |
|---|---|
| Listings, seller linkage/projection, prices, availability, offers, deal records, listing moderation, reports, disputes, structured listing evidence and recalls | **File 18** |
| Identity, age/guardian status, account approval, suspension and capabilities | File 00 |
| Product-linked conversations, messages and calls | File 17 |
| Notification projection and delivery | File 19 |
| Header, navigation, route layout and Safe Mode coordination | File 20 |
| Security/privacy assurance evidence | File 24 |
| Global visual tokens/components | File 25 |
| Global search/discovery/ranking orchestration | File 26 |

File 18 does **not** create conversation, message, call, wallet, escrow, payout, courier or notification-delivery canonical tables.

## Implemented capabilities

- Governed category and prohibited-item policies.
- Verified-entry seller eligibility with current File 00 guardian, suspension, verification and risk rechecks.
- Listing draft, review, active, pause, sold/unavailable, expiry, rejection, removal and appeal states.
- Structured regulated-product evidence with separate reviewer approval and publication gates.
- Recall/takedown lifecycle with public safety notice, noindex/no-store and disabled offer/contact actions.
- Secure media references through the central media owner; no raw duplicate media ownership.
- Public faceted listing search plus File 26 provider contract; native default ordering has no paid/donor bias.
- Saves, shares and purpose-limited safety reports with the complete governed report taxonomy.
- Product-linked File 17 conversation context cards with current seller revalidation and same-origin handoff enforcement.
- Structured offers, counters, acceptance, decline, withdrawal and expiry with listing row locks and stale-state rejection.
- Atomic direct-deal snapshots with current seller eligibility revalidation and participant-scoped transitions.
- Purpose-limited disputes and moderation decisions with appeals.
- Versioned events, recoverable outbox leases, external inbox conflict detection, bounded retries and reconciliation.
- Public/private DTO allowlists, noindex/no-store participant routes, bounded privacy export/erasure and legal/safety holds.
- Tamper-evident audit chain; required audit persistence fails closed inside governed transactions.
- REST mutation CSRF protection that distinguishes successful WordPress Application Password authentication from arbitrary Authorization-header presence.
- System Check, repair dry-run, safe mode, metrics and migration handoff diagnostics.
- Mobile-first, RTL-ready, keyboard-accessible UI with green File 25 token fallbacks and 44px minimum interactive targets.
- Deterministic build, manifest, checksum, PHP 8.1/8.3 CI and static/contract/adversarial/four-plan QA.

## Forty-round governing-plan audit

A fresh 40-round review/fix cycle was performed against:

1. Definitive Integrated Master Plan v3.0;
2. Consolidated Recovered Directives;
3. Continuous Value / Global Top-20 Superset Master Plan;
4. File 18 — Marketplace Complete Master Plan.

Every discovered defect was corrected before the next round. The detailed round ledger is in `docs/FORTY-ROUND-GOVERNING-PLAN-AUDIT.md`.

## Truthful completion status

The current branch can establish **Specified**, **Coded**, **Packaged** and **Automated-QA Green** only when the exact-head CI is green. **Staging-Accepted, Live-Deployed and Operational** remain separate statuses and require Hostinger staging, real File 00/17/19/20/24/25/26 integrations, real-role buyer/seller/reviewer workflows, browser/device/accessibility testing, backup/restore and rollback proof, security/privacy acceptance and Founder approval.

See also:

- `docs/ARCHITECTURE.md`
- `docs/API-EVENT-CONTRACTS.md`
- `docs/MIGRATION-ROLLBACK.md`
- `docs/STAGING-ACCEPTANCE.md`
- `docs/REQUIREMENTS-TRACEABILITY.md`
- `docs/OPERATIONS-RUNBOOK.md`
- `docs/SECURITY-PRIVACY.md`
- `docs/FORTY-ROUND-GOVERNING-PLAN-AUDIT.md`
- `QA-REPORT.md`
- `STATUS.md`
