# File 18 Mandatory Review Report

**Module:** Sabri Marketplace 1.1.0  
**Review basis:** supplied archive, repository state, consolidated platform requirements, local static inspection, PHP syntax lint, and JavaScript syntax check.  
**Decision:** **REJECTED FOR MERGE / NOT PRODUCTION READY** until the release blockers below are corrected and retested.

## Verified passes

- Original archive SHA-256 matches: `7140ec5a0a41d880f2a8609d5c07f0b84b64b4b5674d9e167458eb76519f1b30`.
- ZIP integrity test passes.
- Archive contains one valid plugin folder and 17 files.
- Plugin version is consistently declared as `1.1.0` in the main header and readme.
- All 10 PHP files pass syntax lint under PHP 8.4.
- `assets/js/marketplace.js` passes `node --check`.
- Core direct-deal policy is present: platform commission/payment holding is disabled and buyer–seller contact is supported.
- AJAX nonce checks, participant-only conversation lookup, prepared SQL in principal query paths, output escaping in admin views, and restricted chat attachment MIME checks are present.

## Release blockers

### P0 — Repository baseline is incomplete

The original ZIP is preserved, but the 17 extracted source files are not yet committed as browseable repository files. A ZIP-only baseline prevents normal GitHub code review, blame, per-file diffs, security review, and controlled corrections. The baseline branch must contain the exact extracted source without modification before acceptance.

### P1 — Central platform integrations are bypassed

1. **Notifications are duplicated.** The plugin creates its own `smp_notifications` table, notification screen, unread counter, and delivery logic instead of using the platform-wide File 19 notification service. This risks duplicate bells, inconsistent preferences, separate retention, and lost cross-module events.
2. **Membership Core is not an explicit dependency.** Seller identity, contact, approval, and capabilities are implemented locally. There is no verified binding to the central File 00 identity, verification, role, privacy, and permission model.
3. **Unified Application Shell integration is absent.** The plugin ships its own application header/sidebar/standalone route rather than a documented File 20 integration contract, creating a material risk of duplicate navigation, width conflicts, and inconsistent mobile behavior.

### P1 — Identity, verification, and privacy controls are insufficient

1. Seller `identity_number`, `business_registration`, `tax_number`, and `license_number` are stored as ordinary plaintext database columns.
2. The seller administration table prints full identity and license numbers instead of masked values and a controlled evidence viewer.
3. No encrypted identity-document storage, reviewer-only document access, retention policy, or key-management mechanism is present.
4. No WordPress personal-data exporter or eraser hooks are present for seller records, buyer phone/WhatsApp metadata, messages, attachments, reports, notifications, or audit logs.
5. Administrator approval automatically changes `verification_level` to `identity` and sets `contact_verified=1`, although the plugin does not implement documentary verification or phone/WhatsApp OTP proof. The public interface can therefore display “Contact verified” without a technically verified contact channel.

### P1 — Marketplace safety settings are not fully enforced

1. `smp_health_license_required` is configurable but is not enforced as a mandatory seller/listing condition in the examined save path.
2. `smp_allow_guest_browse` is configurable but the public AJAX bootstrap remains registered and available regardless of the setting.
3. `smp_reveal_contacts_to_logged_in` is configurable but the contact action is structurally login-only; the setting does not form a complete, tested access policy.
4. Prohibited-item detection is a simple substring list. It is easy to evade through spelling variants and can also create false positives. It is not sufficient as the only moderation gate.

### P1 — Nonpublic listing workflow can be reached by direct identifier

`conversation_start` loads a product by ID but does not require that the product status be `published`/`approved` or that the deal status be `available`. A logged-in user who obtains or guesses an ID may start a conversation on a draft, submitted, rejected, suspended, sold, or paused listing. The same visibility boundary must be enforced server-side in every contact, wishlist, chat, and offer operation.

### P1 — Abuse controls are absent

No bounded rate limiting or anti-spam controls were found for:

- guest/public bootstrap and product lookups;
- seller applications;
- listing submissions;
- contact reveals;
- conversation creation;
- message sending and attachment uploads;
- typing events;
- offers, reports, and block toggles.

Nonce validation prevents cross-site request forgery; it does not prevent authenticated spam, enumeration, scraping, storage exhaustion, or application-layer denial of service.

### P1 — Private attachment protection is not sufficiently portable

Private chat attachments are placed under the public uploads tree and protected mainly by a generated `.htaccess` containing legacy `Deny from all`. That protection is server-dependent and may not be honored by every Nginx/LiteSpeed/Apache configuration. Attachments are not encrypted at rest, and no malware scanning/quarantine workflow is present. Storage should be outside the public webroot or protected by a verified server rule, with fail-closed checks and controlled migration.

### P2 — Health and diagnostic endpoints expose internal structure

The public REST health route returns plugin version, table-presence results, direct-deal mode, and page information. Public health output should be minimal; detailed database diagnostics belong behind administrator capability and nonce checks.

### P2 — System Check is incomplete

The database installer creates 10 tables, including `smp_wishlist`, but the administrator System Check omits the wishlist table. A missing wishlist table can therefore be reported as “Ready.” The health route also checks only a subset of tables.

### P2 — Data retention and deletion behavior is inconsistent

- Message deletion removes attachment files immediately and clears the stored path, making the configured “deleted chat file retention days” setting ineffective for ordinary user deletion.
- Read notifications are pruned, but unread notifications, messages, reports, audit logs, seller identity values, and buyer contact metadata have no complete retention/erasure policy.
- No safe uninstall policy or documented data-ownership matrix is included.

### P2 — Performance and lifecycle concerns

- `SMP_Activator::set_defaults()` and capability assignment run on every `init` request.
- `touch_last_seen()` writes user metadata on every logged-in request, which can create unnecessary database write volume.
- No pagination is used in several administration screens; fixed limits of 150–500 records are used instead.
- No database error handling or transaction/rollback strategy is present for multi-step chat, offer, moderation, and migration operations.

## Required correction order

1. Commit the exact 17-file extracted baseline and verify every file against `CHECKSUMS.sha256`.
2. Freeze the original import in a draft pull request; do not edit baseline source in place.
3. Create a separate corrective branch/version.
4. Integrate File 00 Membership Core, File 19 Notifications, and File 20 Unified Application Shell through explicit adapters/contracts.
5. Correct identity encryption, masking, evidence review, verification semantics, privacy export/erasure, retention, and uninstall boundaries.
6. Enforce server-side listing visibility and health-license rules in every operation.
7. Add rate limits, attachment hardening, minimal public REST output, complete System Check, and database error handling.
8. Add automated tests, fresh-install/upgrade/rollback tests, two-account buyer–seller tests, accessibility checks, responsive viewport tests, and Hostinger staging acceptance.

## Acceptance rule

This package may be described as **archive-preserved and syntax-checked**. It may not be described as **complete, secure, staging-accepted, production-ready, or operational** until every blocker is corrected, retested, documented, and accepted on staging.
