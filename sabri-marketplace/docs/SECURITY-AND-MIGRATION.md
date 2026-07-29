# Security, Ownership, and Migration Contract

## Data ownership

| Data | Owner | Marketplace behavior |
|---|---|---|
| Account identity, approval, phone verification, ID/passport evidence | File 00 Membership Core | Read through adapter; never duplicate evidence |
| Professional license and registration | File 00 Membership Core | Required for regulated categories when enabled |
| Marketplace seller display profile | File 18 | Store name, location, visibility preferences, direct-deal settings |
| Listings, wishlists, product-linked conversations, offers, reports | File 18 | Owned and privacy-audited |
| Platform notifications and preferences | File 19 Unified Notifications | Marketplace publishes events through `sabri_notify_user()` |
| Global header, navigation, responsive layout | File 20 Unified Application Shell | Marketplace registers destination/layout only |

## Upgrade migrations

Version 1.2.0 runs idempotent migrations:

1. Creates or updates the nine File 18 owned tables.
2. Encrypts legacy plaintext seller identity/business/tax/license columns with File 00 cryptography.
3. Copies legacy local notifications to File 19 using stable deduplication keys; the legacy table is retained only for rollback/history and receives no new writes.
4. Migrates legacy public-upload chat attachments to encrypted private storage.
5. Preserves existing listings, sellers, conversations, messages, reports, URLs, WordPress users, and companion-plugin data.

## Private attachments

- Default path: outside the WordPress installation directory, under `sabri-private-files/marketplace`.
- Override: define `SMP_PRIVATE_STORAGE_DIR` to a writable private root.
- Scanner: define `SMP_CLAMAV_COMMAND` or implement `smp_attachment_scan_result`.
- Upload behavior is fail-closed when scanner, crypto, MIME verification, or private storage is unavailable.
- File content is AES-256-GCM encrypted with a purpose-specific File 00 key.
- Download requires a per-message nonce and verified conversation participation.

## Verification semantics

Marketplace `approved` means the Marketplace administrator accepted the store/listing after the central File 00 identity state was already approved. It never independently asserts that a phone, ID, license, or document is verified.

## Rollback

Before staging upgrade, take a full files/database backup. The plugin does not automatically drop legacy notification data or original attachments until their encrypted replacement has been written. Rollback must restore both database and private-file storage from the same backup point.
