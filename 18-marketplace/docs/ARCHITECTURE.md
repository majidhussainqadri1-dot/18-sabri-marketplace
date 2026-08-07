# Architecture and Canonical Ownership

## 1. Layering

1. **Experience layer** — route templates, accessible forms, cards and private dashboards.
2. **Application layer** — listing, offer, deal, report, dispute and policy services.
3. **Contract layer** — File 00/17/19/20/24/25/26 adapters, REST DTOs and versioned events.
4. **Data layer** — normalized File 18 tables, outbox/inbox, audit and migration handoff ledger.
5. **Operations layer** — System Check, maintenance, reconciliation, repair, retention and metrics.

## 2. Canonical data domains

| Table | Purpose | Privacy class |
|---|---|---|
| `mkt_sellers` | Seller linkage and public-safe eligibility projection | Controlled |
| `mkt_policies` | Versioned category, prohibited-item and business rules | Public/internal |
| `mkt_listings` | Listing truth, price, currency, availability and lifecycle | Public/restricted |
| `mkt_media_refs` | References to central media objects and safety status | Public/controlled |
| `mkt_saves` | Private user saves | Private |
| `mkt_offers` | Structured negotiation records | Participants |
| `mkt_deals` | Accepted snapshot and direct-deal lifecycle | Restricted |
| `mkt_reports` | Safety reports and decisions | Restricted |
| `mkt_disputes` | Deal disputes and purpose-limited evidence references | Restricted |
| `mkt_outbox` / `mkt_inbox` | Reliable versioned integration events | Internal |
| `mkt_idempotency` | Mutation replay protection | Internal |
| `mkt_audit` | Tamper-evident action record | Restricted |
| `mkt_metrics_daily` | Aggregated privacy-safe operational metrics | Aggregate |
| `mkt_migration_handoffs` | Non-destructive legacy owner-transfer ledger | Internal |

There are deliberately **no** active File 18 conversation, message, call, wallet, escrow, payout or notification-delivery tables.

## 3. State machines

- Listing: `draft → review → active → paused/sold_unavailable/expired → rejected/removed/appealed`.
- Offer: `open → countered → accepted/declined/withdrawn/expired`.
- Deal: `accepted → arranging → completed/cancelled/disputed → resolved/closed`.
- Report: `submitted → triaged → restricted/no_action/decided → appealed/closed`.
- Dispute: `opened → evidence/review → more_info/decided → appealed/closed`.

Every mutable transition checks current state, expected version, actor capability, ownership/participation and current File 00 assertions. Offer acceptance locks the listing row, enforces the accepted-offer uniqueness invariant and updates quantity/availability atomically.

## 4. Failure model

- Mandatory owner unavailable: protected action fails closed with a safe 503 response.
- Duplicate request: idempotency/unique key returns the authoritative existing record.
- Stale client: optimistic version returns 409 without side effect.
- Event consumer failure: owner transaction remains committed; outbox retries with bounded exponential delay and dead-letter state.
- Delayed cron: request-time state checks still deny expired/unavailable objects.
- File 17 unavailable: listing remains readable; product conversation action is unavailable rather than creating a fallback chat database.

## 5. Free access and business integrity

Public eligible listings are readable without an account. Protected actions require an approved verified-entry account. The marketplace has one free feature tier and **0% platform commission**. Donation status never changes ranking, eligibility, visibility or capability.
