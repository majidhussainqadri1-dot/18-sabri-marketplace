# File18 / File26 RC36 — Bounded source reconciliation (source-only)
Date: 2026-11-15. V12.3 F18-FR-020, F18-NFR-003/006/008, CV-210.

Following RC35's versioned outbox delivery, an **already-active** File26 registry connector now receives a scheduled, bounded owner-source reconciliation. A single 50-row batch runs after a MySQL advisory lock, freshly re-reads canonical active/paused/expired/recalled/seller-suspended Listing eligibility and applies versioned index upserts/tombstones. It checkpoints its numeric cursor ONLY after successful delivery of the entire batch. Failures return a typed WP_Error and repeat the same cursor; concurrent workers are fenced by GET_LOCK, and a 5-minute single future WP-Cron event is scheduled. The connector remains inert while `proposed`.

This covers missed events and seller-wide public revocation without an unbounded seller transaction or cross-file ownership of private records. File26 public search additionally revalidates owner can_view; no stale listing may become public merely because its index is stale.

RC36 exercises the actual PHP class against fake DB/WordPress services: source/target errors, refused locks, failed checkpoints, malformed cursor, proposed/active gating, seller/listing state, bounded cursor and recurring schedules. A passing fixture does not certify actual WordPress/MySQL cron reliability or an approved File26 cutover.

Remaining release gates: real File26 active connector lifecycle, Hostinger MySQL/WordPress integration and performance, missed-event SLA measurement, signed audit retention #18, live deployed reality/parity #17 and 37 staging checks. No deployment.
