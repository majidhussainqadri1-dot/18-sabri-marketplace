# Changelog

## 2.1.0 — Four-plan complete corrective candidate

### Added
- Structured regulated-product evidence: ingredients/constituents, manufacturer, license/registration, batch, expiry or justified non-applicability, claims and source references.
- Human evidence review and strict publication gating for regulated homeopathic medicine listings.
- Recall/takedown records, batch/reference scope, regulator references, listing suspension, buyer/seller notification facts and public warning.
- Time-bounded marketplace legal/safety retention holds with authority reference and audited release.
- Seller Studio inventory, privacy-safe inquiry/SLA adapter, listing-quality score and report count; these signals never affect paid/donor ranking.
- Language, availability, product/service/digital type, seller-status and rights-safe marketplace discovery facets.
- Expanded report taxonomy for scam, counterfeit, non-delivery, unsafe/false claims, impersonation, privacy, harassment/abuse, copyright, child safety, illegal items and misleading pricing.
- Seller evidence/reviewer UI, public evidence/recall presentation and complete archive filters.

### Corrected
- Preserved authoritative File 00 `available=false` and rejected malformed/incompatible identity assertions instead of recovering optimistically.
- Required currently verified-professional assertions for regulated medicine selling.
- Revalidated high-trust operational/admin actions against current File 00 state.
- Closed stale idempotency reclaim races using compare-and-swap conditions.
- Added recoverable outbox processing leases and safe failed-inbox reclaim with payload-conflict rejection.
- Added stale/retry leasing for File 17 legacy handoffs.
- Made REST, scheduled reconciliation/expiry and admin moderation owner-state + outbox changes atomic.
- Made privacy export bounded and privacy/retention behavior aware of active legal/safety holds.
- Closed API publication bypasses for unknown language, missing approved evidence and active recall.
- Retained zero commission, single free tier, no donor advantage, no paid ranking and no parallel File 17 chat backend as machine-enforced invariants.

### Release truth
- Runtime/schema `2.1.0`; contract `1.2.0`; REST namespace `marketplace/v1`.
- Candidate package `18-marketplace-2.1.0-RC3.zip`.
- Staging, live deployment and operational acceptance remain separate release gates.

## 2.0.1 — Superseded corrective candidate
- Enforced approved + verified high-trust identity and high-risk fail-closed behavior.
- Removed featured-label ranking privilege and declared single-free/no-donor/no-paid-ranking contracts.
- Added direct-source supply-chain gate, bounded legacy migration and initial four-round QA.
- Superseded after fresh four-plan audit found additional Top-20 evidence/recall/Seller-Studio, privacy/hold and reliability gaps.

## 2.0.0 — Final-plan architectural correction
- Added canonical listing, seller, policy, media-reference, offer, deal, report, dispute, outbox/inbox, idempotency, audit, metrics and migration domains.
- Kept File 17 as canonical conversations/messages owner and File 19 as notification owner.
- Removed platform commission and legacy parallel communication ownership.
