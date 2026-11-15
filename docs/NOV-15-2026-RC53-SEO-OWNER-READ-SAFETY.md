# RC53 — WordPress metadata head owner projection safety
Date 2026-11-15. Exact parent 9eb4ddb34e13cfc7d56f024053cda2a69a3851cb. Master Plan V12.3 F18-FR-004/008, NFR-001/003.

Root cause: WordPress `wp_head` hook `MKT_Routes::structured_data()` directly accessed `MKT_Listings::get()` and `public_dto()` without catching File10 media rights/owner SQL failures. A source read exception would abort the entire public page; SQL failure returning a data row could generate false canonical/product SEO metadata.

Correction: confirm canonical owner SQL read and fresh public DTO without exception or `wpdb->last_error` before outputting any canonical or JSON-LD metadata. Under failure emit nothing rather than an inaccurate or partial public product. Strengthened RC33 actual-class test with four injected owner/media fault states plus successful recovery.

Release boundary: source hardening only; not an approval of SEO content, user permissions, File26 indexing, staging or live deployment.