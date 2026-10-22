# File18 RC29 — 27th 40-control review: HTTP response correctness at public/private template boundaries

**Date:** 22 October 2026. **Source base:** RC28 candidate HEAD `7ba90c899757fca8ce1f47e350a0b837f2c8517e`, Draft PR #30; GitHub `main` remains independent at `04a5872e3e2e170ee400e7c607e39abd5484df65`. Source candidate only; **deployed version, schema and migration are unverified**.

## Root cause and minimal correction
The Listing and Deal templates both called `templates/_header.php` before evaluating missing/unauthorized records. That shared header calls `get_header()`, which can render and send HTTP response headers. The subsequent `status_header(404)` could therefore run too late, leaving an HTTP 200 for unavailable/private records. This violates the File18 public/private route and object non-disclosure requirements, even though a 404 message was visible on the page.

- `18-marketplace/templates/listing.php`: compute publication/recall eligibility and set HTTP 404, no-cache and X-Robots noindex headers **before** including the shared header; retain the safety-specific recalled Listing exception.
- `18-marketplace/templates/deal.php`: inspect participant-authorized owner result, set HTTP 404 and no-cache **before** rendering the shared header; retain participant checks and noindex/no-store route policy.
- `18-marketplace/tests/test-review-invariants.php`: append regression assertions for both response status/header order and Listing robots/cache behavior.
- Candidate package label RC29 and workflow consistency updated; software/runtime/schema versions are not changed.
- **New scoped source defect groups: 1** (late HTTP status propagation in 2 templates); **source fixes: 1 proposed**; **live fixes: 0**.

## Forty plan/owner checkpoints
These are 40 specifically named control topics; the GitHub Actions `qa-static.sh` execution is separately configured to repeat the complete **58-stage** suite **40 times per PHP 8.1 and 8.3** after opening this PR. Forty automation repetitions do not equal 40 manual staged acceptance runs.

1. F18-FR-001 Category/policy taxonomy
2. F18-FR-002 Seller eligibility
3. F18-FR-003 Listing draft
4. F18-FR-004 Listing media
5. F18-FR-005 Listing review
6. F18-FR-006 Listing lifecycle
7. F18-FR-007 Search/facets
8. F18-FR-008 Cards/detail status/SEO
9. F18-FR-009 Save/share/report — unavailable Listing must return true 404/noindex before theme output
10. F18-FR-010 Product-linked chat
11. F18-FR-011 Offer
12. F18-FR-012 Negotiation
13. F18-FR-013 Deal lifecycle — private unauthorized Deal must return true 404 before theme output
14. F18-FR-014 Zero commission
15. F18-FR-015 Direct payment/status bridge
16. F18-FR-016 Moderation
17. F18-FR-017 Disputes
18. F18-FR-018 Promotions
19. F18-FR-019 Seller insights
20. F18-FR-020 Expiry/reconciliation
21. F18-NFR-001 Object/field authorization
22. F18-NFR-002 Privacy lifecycle
23. F18-NFR-003 Reliability
24. F18-NFR-004 Performance
25. F18-NFR-005 Accessibility
26. F18-NFR-006 Observability
27. F18-NFR-007 Migration/rollback
28. F18-NFR-008 Operability
29. F18-NFR-009 Compatibility
30. F18-NFR-010 Localization
31. File00 role/guardian/risk permissions
32. File10 owned media references
33. File17 single communication owner
34. File19 notification owner
35. File20 shell/routes/navigation
36. File24 security/privacy assurance
37. File25 UX/RTL/visual tokens
38. File26 search owner/index boundary
39. CF05 analytics derivative only
40. CF06 translation conditional owner

## Release blockers that remain independent of source tests
1. Cloudflare HTTP 530: origin/DNS/routing cause still unproven; WPVibe has no connected site. Need authorized live WordPress read of active plugin, installed files/checksum, wp_options, database schema, migration state and logs.
2. Immutable tamper-evident audit retention archive/checkpoint and legal-hold governance need File24/security/legal/Founder approvals.
3. Real MySQL staging, File00/10/17/19/20/24/25/26 owner integrations, conditional CF05/CF06 authorization, the central 58 CV acceptances, browser/RTL/accessibility tests, recovery/rollback, independent review, Founder approval and live parity/retest remain unverified.

**Never merge/deploy from this audit alone. Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
