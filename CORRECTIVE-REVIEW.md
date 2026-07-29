# File 18 Corrective Review — Sabri Marketplace 1.2.0-RC1

## Decision

**Code corrections complete; automated QA green; staging acceptance pending.**

## Corrected defects

- Added explicit File 00 Membership Core identity and capability integration.
- Added File 19 Unified Notifications adapter and legacy-notification migration.
- Added File 20 Unified Application Shell integration and duplicate-header/bell prevention.
- Removed locally trusted plaintext identity-verification semantics.
- Added masking, encrypted private storage, controlled migration, retention, WordPress privacy export and erasure.
- Enforced seller approval, listing approval, availability, health-license and public visibility on the server for contact, chat, wishlist and offer workflows.
- Added bounded rate limits for public lookups, applications, submissions, contact reveal, chat, messages, attachments, typing, offers, reports and blocks.
- Moved private attachments outside the public webroot, added AES-256-GCM encryption, controlled download and a fail-closed malware-scanner hook.
- Reduced public REST health output and protected detailed diagnostics by capability.
- Completed System Check coverage, including wishlist and all owned structures.
- Added safer lifecycle behavior, database transactions/error handling, throttled presence writes, pagination and preserve-by-default uninstall behavior.
- Added responsive, keyboard and accessibility corrections.

## Automated evidence

- Corrected ZIP SHA-256: `99483d3d33ee62beb005b5a164237fda5f5bd5405e761c8edeb35eb511c8539b`
- ZIP integrity: PASS
- Browseable source checksum verification: PASS
- PHP 8.1 lint: PASS
- PHP 8.3 lint: PASS
- JavaScript syntax: PASS
- Static security and integration QA: PASS

## Remaining acceptance gates

- Hostinger staging fresh installation.
- Upgrade from 1.1.0 and legacy-data migration.
- Backup restoration and rollback proof.
- Two-account buyer–seller end-to-end workflow.
- Real runtime integration with Files 00, 19 and 20.
- Privacy export/erasure and encrypted-attachment migration tests.
- Responsive and keyboard accessibility acceptance.
- Founder acceptance before merge and live deployment.

The candidate is not yet production-ready or live-deployed.
