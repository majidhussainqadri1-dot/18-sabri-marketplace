=== Sabri Marketplace ===
Contributors: sabrihomeopathy
Tags: marketplace, homeopathy, zero-commission, listings, direct-deal
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 2.1.0
License: Proprietary — Sabri Homeopathy Platform

Canonical File 18 marketplace implementation for the Sabri Social Homeopathy Platform.

== Description ==

Sabri Marketplace provides public approved listing discovery and protected seller, evidence, offer, deal, report, dispute, recall and retention-hold workflows. It is a direct-deal marketplace with 0% platform commission, one free access tier, no donor advantage and no paid ranking.

Canonical boundaries:
* File 18 owns listings, seller projection, structured product evidence, recalls/takedowns, offers, deals, reports, disputes and commerce integrity.
* File 17 owns conversations, messages and calls. This plugin never creates a parallel chat backend.
* File 19 owns notification delivery.
* File 20 owns the application shell and navigation.
* File 24 supplies assurance evidence; native controls remain in File 18.
* File 25 owns global visual tokens.
* File 26 owns global search orchestration; File 18 supplies its marketplace provider/facets.

Protected actions fail closed when mandatory identity or communication contracts are unavailable or incompatible.

== Installation ==

1. Back up files and database and prove restore in staging.
2. Install the ZIP whose top-level folder is `18-marketplace`.
3. Activate on Hostinger staging first.
4. Open Marketplace > System Status.
5. Confirm File 00, File 17, File 19 and File 20 contracts.
6. Test fresh install and upgrade from the previous candidate with two real accounts.
7. Test regulated listing evidence/review, recall/takedown, legal holds, search facets and Seller Studio.
8. Complete accessibility, backup/rollback and Founder acceptance before production.

== Frequently Asked Questions ==

= Does the platform charge commission? =
No. The implementation and release tests enforce 0% platform commission.

= Can donation or payment improve ranking? =
No. Donation advantage and paid ranking are disabled constitutional invariants.

= Does Marketplace store messages? =
No. Product-linked conversations are created through File 17. Legacy File 18 chat records are handed off through a non-destructive migration ledger.

= Does the platform guarantee payment, delivery or cure? =
No. Deals are structured records. Payment and delivery are direct unless a future approved provider adapter explicitly proves otherwise. Cure guarantees are prohibited.

= Can guests browse? =
Yes. Public eligible listings can be browsed without an account. Create, save, chat, offer, report and deal actions require an approved and verified account plus current authorization.

== Changelog ==

= 2.1.0 =
* Added structured ingredients/manufacturer/license/batch/expiry evidence and human review for regulated medicine listings.
* Added recall/takedown lifecycle, buyer notification events and regulator references.
* Added privacy-safe Seller Studio inventory, inquiry/SLA adapter, listing-quality and report indicators with no ranking effect.
* Added language, availability, type, seller-status and rights discovery facets.
* Added complete safety-report taxonomy and strict publication gates.
* Added time-bounded retention/legal holds and hold-aware privacy/retention behavior.
* Fixed identity-contract fail-closed compatibility, stale idempotency/outbox/handoff leases and atomic state+outbox maintenance/admin mutations.
* Added the fourth-round finalization controls and 2.1.0 release QA.

= 2.0.0 =
* Rebuilt canonical listing, policy, media reference, offer, deal, report, dispute, outbox/inbox, idempotency, audit, metrics and migration domains.
* Removed File 18 ownership of conversations/messages; File 17 adapter is mandatory and fail-closed.
* Added File 00 identity assertions, File 19 notifications, File 20 shell, File 24 assurance, File 25 visual and File 26 search contracts.
