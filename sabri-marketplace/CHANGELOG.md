# Changelog

## 1.2.0 — Corrective Release Candidate

### Central platform integration
- File 00 Sabri Membership Core is mandatory for seller eligibility, mobile verification, professional license verification, and cryptographic keys.
- File 19 Sabri Unified Notifications is the sole notification backend; the legacy Marketplace notification table is migrated and decommissioned.
- File 20 Unified Application Shell receives the Marketplace destination and two-column layout; duplicate global navigation and notification output are removed.

### Security and privacy
- Seller identity evidence is no longer collected by Marketplace; legacy sensitive values are encrypted and admin output is masked.
- WordPress personal-data exporter and eraser callbacks cover seller profiles, buyer preferences, listings, messages, reports, wishlists, and blocks.
- Private chat files are malware-scanned, encrypted with AES-256-GCM, stored outside the public webroot by default, and streamed only after participant authorization.
- Server-side visibility checks prevent draft, rejected, suspended, paused, sold, or unavailable listings from being contacted, wishlisted, or used to create chats.
- Per-operation rate limits cover browsing, applications, listing submission, contact reveal, chats, messages, typing, offers, reports, and blocks.
- Public health endpoint now exposes only minimal service status; detailed diagnostics require Marketplace administration capability.

### Data lifecycle and reliability
- Deleted messages retain encrypted attachments only until the configured retention period, rather than deleting inconsistently.
- System Check includes every owned table, including wishlist, integrations, privacy hooks, private storage, crypto, and scanner state.
- Defaults and capabilities no longer run on every request; version-aware migrations run only when needed.
- Admin seller/listing/report screens are paginated.
- Multi-step listing and chat writes use checked database operations and transactions where required.
- Uninstall preserves data by default and deletes only after explicit administrator opt-in.
