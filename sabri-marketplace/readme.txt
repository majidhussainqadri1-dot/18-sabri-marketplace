=== Sabri Marketplace ===
Contributors: Sabri Homeopathy
Tags: marketplace, direct deal, chat, whatsapp, classifieds, multi-vendor
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.2.0
License: GPLv2 or later

Sabri Marketplace is File 18 of the Sabri Social Homeopathy Platform. It provides a zero-commission direct buyer-seller marketplace with public approved listings, centrally verified seller identity, product-linked internal chat, encrypted private attachments, offers, contact sharing, blocking, reporting, privacy tools, and administrative moderation.

The platform does not receive or hold transaction funds. Buyers and sellers independently agree price, payment, inspection, pickup, shipping, delivery, and handover.

== Mandatory Dependencies ==

* File 00 — Sabri Membership Core 1.0.1 or later: identity, approval, mobile verification, professional credentials, and cryptographic key service.
* File 19 — Sabri Unified Notifications 1.0.0 or later: the single platform notification service and notification center.
* File 20 — Sabri Unified Application Shell 1.0.0 or later: global header, navigation, responsive shell, and Marketplace destination.

The plugin fails closed for seller activation, notification delivery, and private attachment encryption when the relevant mandatory service is unavailable.

== Security and Privacy ==

* Seller identity and professional evidence remain owned by Sabri Membership Core; Marketplace does not collect duplicate identity numbers.
* Existing legacy seller identity fields are encrypted during migration and masked in administration.
* Chat attachments are MIME validated, malware-scanned, AES-256-GCM encrypted, and stored outside the WordPress public webroot by default.
* Private attachments are delivered only through authenticated participant checks.
* Public listing, wishlist, contact, chat, and offer operations enforce listing and seller visibility server-side.
* Bounded per-operation rate limits protect public and authenticated operations.
* WordPress personal-data export and erasure callbacks are included.
* Data is preserved on uninstall unless destructive deletion is explicitly enabled.

== Installation ==

1. Install and activate Files 00, 19, and 20 first.
2. Configure the File 00 production encryption key and verified identity/mobile workflows.
3. Configure an attachment scanner through `SMP_CLAMAV_COMMAND` or the `smp_attachment_scan_result` filter. Uploads fail closed without a scanner.
4. Optionally define `SMP_PRIVATE_STORAGE_DIR` as a writable directory outside the public webroot. The default is a `sabri-private-files` directory beside the WordPress installation directory.
5. Activate Sabri Marketplace and open Marketplace > System Check.
6. Run Repair and Migrations once, then complete fresh-install, upgrade, rollback, buyer-seller, accessibility, responsive, and Hostinger staging tests.

== Upgrade Notice ==

= 1.2.0 =
Security and integration correction release. Centralizes identity and notifications, integrates the Unified Application Shell, enforces listing visibility and verified health-license rules, adds rate limits, privacy export/erasure, encrypted private attachments, minimal public health output, complete System Check coverage, retention-aware deletion, pagination, safer migrations, and zero-commission policy alignment.
