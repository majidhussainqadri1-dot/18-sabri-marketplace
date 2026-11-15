# File 18 — Safe client listing-card rendering (15 November 2026)

Plan V12.3: F18-FR-008, F18-NFR-001/005/010, CV-202.
Source baseline: RC31 Draft PR #33, not live.

## Confirmed source weaknesses corrected

1. The public "Load more" renderer interpolated the REST-provided \`item.url\` into clickable \`href\` attributes after HTML escaping but without checking URL scheme, host or listing route. This is unsafe if an upstream response is malformed or compromised. Now the client accepts only HTTP(S) URLs that resolve to the current origin and canonical marketplace-listing base path.
2. The renderer dereferenced \`item.category.replaceAll\` and \`item.seller.store_name\` unconditionally. Missing fields could prevent the remaining results from rendering. Null/malformed items are skipped; category and seller have safe fallbacks.
3. English "View listing" was hardcoded. PHP now supplies WordPress-gettext-localizable \`viewListing\` and \`otherCategory\` strings through \`MKT_APP\`. Full Urdu/Arabic catalog and RTL browser acceptance remain to be proved.

## Automated regression

New \`tests/test-rc32-client-listing-card.mjs\` exercises the actual JavaScript through a controlled Node VM/document stub. Test includes javascript: URL, offsite URL, wrong same-site route, null item/category/seller, escaped HTML/title/money, correct same-origin canonical links, and dynamic localizable labels. CI executes the test directly and inside the 61-stage full suite repeated 40 times per PHP version.

## Limitations

No real-browser or screen-reader certification; no installed WordPress/Hostinger, no integration with File26 events, and no live deployment. PR remains Draft; core blockers #17/#18 remain.
Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
