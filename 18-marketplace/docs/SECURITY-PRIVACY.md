# Security, Privacy, Safety and Ethical Controls

## Authorization

- Authentication never substitutes for authorization.
- Every protected action rechecks File 00 status, suspension, guardian context and risk state.
- Roles are coarse context; capability plus object ownership/participation and state are mandatory.
- Moderator and dispute-review powers are separate.
- Object IDs are opaque UUIDs; sequential internal IDs are not public identifiers.

## Mutation protection

- REST nonce/CSRF checks for browser requests.
- Application-password/Bearer authentication support through WordPress.
- Idempotency keys for offer creation/counter chains.
- Expected-version optimistic concurrency on every state change.
- Listing-row database lock during deal acceptance.
- Bounded rate limits for search, creation, offers, chat, reports and operations.

## Upload/media boundary

File 18 stores only central media references. The media owner must provide signature/MIME validation, malware scan, rights status and authorized delivery. A listing cannot be published while an active media reference is not `rights_status=approved` and `scan_status=clean`.

## Marketplace safety

Prohibited controls cover illegal goods, weapons/explosives, gambling, adult services, illegal drugs, stolen/counterfeit goods, patient-data trade, unauthorized prescription items and false guaranteed-cure claims. Regulated homeopathic medicine categories require professional verification and human review.

## Privacy

- Public/private DTO allowlists; no entire model or user-meta serialization.
- Offers, deals, reports and disputes are participant/reviewer scoped.
- Private routes are noindex, noarchive and no-store.
- Seller contact modes are consent-controlled; File 17 is the default safe contact route.
- Minor seller/buyer contact requires verified guardian context and current policy.
- Privacy exporter covers seller/listing/offer/deal/report records.
- Erasure deletes saves, minimizes public seller identity and redacts report details; legally/policy-required transaction records may be retained with a clear explanation.
- Audit and safety evidence have bounded retention; legal hold is scope-specific and must be implemented by an approved policy extension.

## Audit

Audit rows contain trace ID, actor, action, object public ID, purpose, outcome and redacted details. Each row includes the previous hash and an HMAC entry hash, enabling tamper-evidence without claiming immutable external notarization.
