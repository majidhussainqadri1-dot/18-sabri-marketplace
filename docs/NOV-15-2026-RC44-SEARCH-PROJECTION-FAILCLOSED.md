# File18 RC44 — public search safety projection failure
Date: 2026-11-15. Plan V12.3 F18-FR-007/008/016/020; F18-NFR-001/003/006.

Proven source issue: `MKT_Finalization::search_array()` checked its initial SQL result but allowed `MKT_Listings::public_dto()`, `MKT_Governance::public_evidence()`, and `MKT_Governance::public_recall()` to fail or return null under SQL error inside the row projection loop. This could cause a fatal exception or suppress critical recall safety data while returning a successful page.

Fix: wrap each row projection, detect `$wpdb->last_error` after each canonical data source, and return all-or-nothing `search_unavailable` (HTTP 503 via the existing REST path) on exceptions, invalid DTO, or failed SQL. Do not expose SQL details. Clean and complete results remain unchanged.

Verification: standalone PHP fixture invokes the actual Finalization source with seven fault types, checks safe 503 response for all, and successful recovery; GitHub 70-stage static QA and 40 repeated tests on PHP 8.1/8.3 must pass before treating this as a tested source candidate.

Limitations: live deployed source/schema and Hostinger staging remain unverified; File26 integration, #17/#18, cross-module acceptance and Founder signoff still gate production.
