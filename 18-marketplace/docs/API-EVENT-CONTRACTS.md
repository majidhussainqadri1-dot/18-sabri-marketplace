# API and Event Contracts

## REST namespace

`marketplace/v1`

### Public reads

- `GET /status`
- `GET /policies/categories`
- `GET /listings`
- `GET /listings/{public_id}`

Public results use explicit allowlists and expose only active listings from approved sellers.

### Protected commands

- `POST /listings`
- `PATCH /listings/{public_id}`
- `POST /listings/{public_id}/submit`
- `POST /listings/{public_id}/transition`
- `POST /listings/{public_id}/media`
- `POST /listings/{public_id}/save`
- `POST /listings/{public_id}/chat`
- `POST /listings/{public_id}/offers`
- `POST /offers/{public_id}/transition`
- `GET /deals/{public_id}`
- `POST /deals/{public_id}/transition`
- `POST /deals/{public_id}/disputes`
- `POST /reports`
- `POST /reports/{public_id}/transition`
- `POST /disputes/{public_id}/transition`
- `GET /dashboard`
- `GET /system-check`
- `POST /repair`

Browser mutations require the WordPress REST nonce. Application-password/Bearer requests use the normal WordPress authentication chain. Every command applies current native object/state authorization and a rate-limit bucket.

## Error envelope

```json
{
  "code": "mkt_stale_version",
  "message": "The record changed. Reload and try again.",
  "data": {"current_version": 4},
  "trace_id": "opaque-uuid"
}
```

No SQL, paths, secrets, identity evidence or internal stack traces are returned.

## Published events

- `MarketplaceListingCreated.v1`
- `MarketplaceListingSubmitted.v1`
- `MarketplaceListingPublished.v1`
- `MarketplaceListingStatusChanged.v1`
- `MarketplaceOfferCreated.v1`
- `MarketplaceOfferCountered.v1`
- `MarketplaceOfferAccepted.v1`
- `MarketplaceOfferStatusChanged.v1`
- `MarketplaceDealAccepted.v1`
- `MarketplaceDealStatusChanged.v1`
- `MarketplaceDealDisputed.v1`
- `MarketplaceReportSubmitted.v1`
- `MarketplaceSellerStatusChanged.v1`

Event envelope fields: event ID, type, version, occurrence time, producer/version, aggregate type/public ID, actor, trace ID and privacy-classed payload. Delivery assumes at-least-once semantics; consumers must deduplicate.

## Consumed events

- `SellerSuspended.v1` — suspends seller projection and pauses public listings.
- `PaymentStatusChanged.v1` — updates a verified direct/provider status without claiming escrow.
- `MessageConversationReported.v1` — creates a purpose-limited marketplace report reference without copying message content.
