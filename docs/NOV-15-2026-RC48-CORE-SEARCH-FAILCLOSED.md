# RC48 — canonical core search atomic media projection
Date: 2026-11-15; parent dc2ed19b9f0da5608333863e42bdbb9f871c4a65. Plan V12.3 F18-FR-004/007/008; NFR-003.

The lower-level `MKT_Listings::search()` (used by File26 provider and owner-specific searches) checked the initial SQL query but used `array_map(public_dto/private_dto)` without handling exceptions or SQL errors from `MKT_Listings::media()`. A failed rights/scan read could abort with a fatal error or create unsafe partial results. This is separate from the previously corrected enhanced REST search in RC44.

Now every result DTO is individually checked; failures abort the entire result set with structured `search_unavailable` and no private SQL details. Both public and authorized private DTOs remain isolated. New actual-class regression checks successful read, zero-commission, public/private media failure, exception containment, initial SQL failure and recovery. Scheduled QA now comprises 73 stages repeated 40× on PHP 8.1 and 8.3.

Staging, real-browser, File26 live index activation and deployed source/DB parity not yet verified.