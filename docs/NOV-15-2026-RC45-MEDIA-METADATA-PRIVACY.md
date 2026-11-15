# File 18 RC45 — public media metadata minimization
Date: 15 Nov 2026. Source parent 8044638196b233c93d05c9a5ad926cef2f4a93c7. Plan V12.3 F18-FR-004/008, F18-NFR-001/002, CV-205/206.

## Root cause
`MKT_Integrations::media_reference()` accepts arbitrary `metadata` returned by the canonical File10 media provider and persists it in `metadata_json`. The public `MKT_Listings::media(..., true)` path previously returned the **entire decoded metadata object**. Provider payloads can contain EXIF coordinates, patient identifiers, internal scanner records or tokens, and approved media status does not authorize their public disclosure.

## Source correction
Public Listing DTOs now project an explicit four-key allowlist: validated HTTP(S) `url`, `thumbnail_url`, positive bounded `width`, `height`. The raw media JSON is never returned in the public DTO. Private seller view retains existing metadata under the separately enforced owner authorization. Invalid image URLs are omitted.

## Verification
Actual-class RC45 PHP test checks public query rights/scan filters, URL/dimension preservation, private metadata redaction, raw blob deletion, separate seller DTO, and failed SQL denial. Added to dedicated exact-head CI plus the 71-step aggregate gate repeated 40 times per PHP runtime.

## Limits
Source-level protection only. Audit all earlier cached public responses, search caches, screenshots and provider metadata storage during staging, plus retention/erasure. No claim of live deployment. Issue #17/18 and 37 staging gates remain unresolved.
