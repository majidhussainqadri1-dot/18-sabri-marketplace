# Hostinger Staging Acceptance Checklist

The repository candidate is not production-complete until every required item below has dated evidence.

## Artifact and environment

- [ ] Exact GitHub head, package name, SHA-256, source manifest and ZIP CRC recorded.
- [ ] WordPress 7.0.1/PHP 8.3.x/MySQL/LiteSpeed actual staging versions recorded.
- [ ] Verified database/files/configuration backup restored successfully in isolation.
- [ ] Fresh install succeeds with canonical `18-marketplace` top folder.
- [ ] Upgrade from deployed 1.1.0/1.2.0 candidate succeeds and is idempotent.

## Real integrations

- [ ] File 00 current assertions: approved, pending, suspended, adult, minor+guardian and risk-hold accounts.
- [ ] File 17 product context conversation creates/finds exactly one conversation; no File 18 chat table/output.
- [ ] File 19 generates exactly one deduplicated notification per approved event and respects preferences.
- [ ] File 20 shows one Marketplace destination and correct two-column/private route context.
- [ ] File 24 receives assurance/control status without taking native authorization ownership.
- [ ] File 25 tokens render without duplicate theme/shell.
- [ ] File 26 indexes only active public listings and removes paused/suspended/removed listings within SLA.

## End-to-end roles

- [ ] Guest searches and reads active listing; action returns to intended route after login.
- [ ] Eligible seller creates draft, adds scanned/rights-approved media, submits and receives review state.
- [ ] Moderator publishes/rejects/removes with reason and appeal path.
- [ ] Buyer saves, opens File 17 conversation and creates structured offer.
- [ ] Seller counters/accepts; concurrent acceptance test creates no duplicate deal.
- [ ] Participants arrange/complete/cancel; dispute reviewer follows purpose-limited workflow.
- [ ] Suspended seller immediately loses protected actions and public listings pause.
- [ ] False cure, patient data, counterfeit, prohibited category and malformed currency fail closed.

## Privacy/security

- [ ] IDOR matrix for every object/field/state endpoint.
- [ ] CSRF, replay, duplicate idempotency, stale version and rate-limit tests.
- [ ] Public/private cache, noindex/noarchive/no-store and deep-link authorization tests.
- [ ] Privacy export/erasure and dependent search/cache deletion propagation.
- [ ] Event/webhook signature/replay validation supplied by platform intake.
- [ ] Logs and REST errors contain no SQL/path/secret/identity evidence/private content.

## Accessibility and responsive

- [ ] Keyboard-only completion for browse, create, offer, report and deal.
- [ ] Visible focus, managed modal focus, Escape close and focus restoration.
- [ ] Screen-reader landmarks, names, roles, validation summary and live status.
- [ ] 320px–1920px, 200%/400% zoom, Urdu/Arabic RTL, long labels and slow network.
- [ ] No clipping, overlap or page-level horizontal scrollbar.

## Recovery and approval

- [ ] Queue retry/dead-letter, provider outage, DB/cache failure and repair dry-run tested.
- [ ] Safe Mode preserves public reading and disables high-risk mutations.
- [ ] Rollback rehearsal protects post-cutover new records.
- [ ] Two fresh review/fix rounds after the final code change are green.
- [ ] Founder approves real desktop/mobile workflows, copy, zero-commission behavior and safety limitations.
- [ ] Controlled live plan, rollback window, monitoring and named operational owners approved.
