# RC51 — HTML Marketplace archive search errors and localized copy
Date: 2026-11-15, parent 7b0e5ad46d6b7f18df0ecd9e5b7faedc1ac14e5f. Master Plan V12.3 F18-FR-007/008, F18-NFR-005/010.

The HTML marketplace archive used `(float) $_GET['price_min/max']`, destroying the strict validation previously added at the REST and canonical search-owner layers. Malformed price inputs would silently turn into 0. A search SQL outage or invalid filter was displayed as `No matching listings`, which falsely implied an authoritative successful search.

The archive now preserves sanitized scalar price input until the owner validates its numeric bounds; arrays are rejected via an invalid sentinel rather than coercion. The result presentation explicitly distinguishes invalid filters from temporary search/SQL outages with a visible role=alert, and never shows a misleading 0-listings count under failure. Urdu, Urdu-Pakistan and Arabic WordPress performant-translation catalogs add all three new messages, with strengthened localization contract tests.

Source test does not prove browser screen-reader focus/zoom, File26 index, actual Hostinger deployed parity, or all staging acceptance gates.