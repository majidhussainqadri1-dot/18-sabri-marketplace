# RC52 — native File18 REST public owner-read truth
Date 15 November 2026. Exact parent 0d98c561e7ade231081ac862fe5a1a503504ef5e. Plan V12.3 F18-FR-007/008, NFR-003/006.

Root cause: native `MKT_REST::listing()` assumed any null canonical Listing result was a 404 without inspecting `$wpdb->last_error`. Database outages could mislead consumers into treating previously published items as permanently removed. It also let failures inside `public_dto()` (File10 media rights SELECT) propagate as uncaught exceptions. Native `MKT_REST::listings()` previously packaged all structured search errors, including SQL outages, invalid cursors and expired cursors, as HTTP 200.

Fix: exact canonical owner SQL read evidence, exception containment, and explicit HTTP statuses: 503 for SQL/media errors without private diagnostics, 404 only for a successful known-missing listing, 400 invalid parameters, 410 expired cursor. Dedicated actual-class RC52 failure/positive fixture. 76 QA source stages and standalone CI; real MySQL smoke remains independent.

No unverified production claims.