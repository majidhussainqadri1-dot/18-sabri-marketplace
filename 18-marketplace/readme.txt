=== Sabri Marketplace ===
Contributors: sabrihomeopathy
Tags: marketplace, homeopathy, zero-commission, listings, direct-deal
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 2.0.0
License: Proprietary — Sabri Homeopathy Platform

Canonical File 18 marketplace implementation for the Sabri Social Homeopathy Platform.

== Description ==

Sabri Marketplace provides public approved listing discovery and protected seller, offer, deal, report and dispute workflows. It is a direct-deal marketplace with 0% platform commission.

Canonical boundaries:
* File 18 owns listings, seller projection, offers, deals, reports, disputes and commerce integrity.
* File 17 owns conversations, messages and calls. This plugin never creates a parallel chat backend.
* File 19 owns notification delivery.
* File 20 owns the application shell and navigation.
* File 24 supplies assurance evidence; native controls remain in File 18.
* File 25 owns global visual tokens.
* File 26 owns global search orchestration.

Protected actions fail closed when mandatory identity or communication contracts are unavailable.

== Installation ==

1. Back up files and database and prove restore in staging.
2. Install the ZIP whose top-level folder is `18-marketplace`.
3. Activate on Hostinger staging first.
4. Open Marketplace > System Status.
5. Confirm File 00, File 17, File 19 and File 20 contracts.
6. Test fresh install and upgrade from 1.1.0 with two real accounts.
7. Complete accessibility, backup/rollback and Founder acceptance before production.

== Frequently Asked Questions ==

= Does the platform charge commission? =
No. The implementation and release tests enforce 0% platform commission.

= Does Marketplace store messages? =
No. Product-linked conversations are created through File 17. Legacy File 18 chat records are handed off through a non-destructive migration ledger.

= Does the platform guarantee payment or delivery? =
No. Deals are structured records. Payment and delivery are direct unless a future approved provider adapter explicitly proves otherwise.

= Can guests browse? =
Yes. Public eligible listings can be browsed without an account. Create, save, chat, offer, report and deal actions require an approved account and current authorization.

== Changelog ==

= 2.0.0 =
* Rebuilt against the final File 18 master plan and latest central directives.
* Added canonical listing, policy, media reference, offer, deal, report, dispute, outbox/inbox, idempotency, audit, metrics and migration handoff domains.
* Removed File 18 ownership of conversations/messages; File 17 adapter is mandatory and fail-closed.
* Added File 00 identity assertions, File 19 notifications, File 20 shell, File 24 assurance, File 25 visual and File 26 search contracts.
* Added atomic versioned state transitions, zero-commission invariant, privacy exporter/eraser, retention, System Check, repair, Safe Mode and deterministic packaging.
* Added public responsive UI, RTL support, keyboard focus, structured data and private no-store participant routes.
