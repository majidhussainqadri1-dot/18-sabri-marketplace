# File18 RC43: one-shot File26 reconciliation retry on pre-lock exits
Date: 2026-11-15; exact parent b00fb1fd569d1d6b41359a6d1bbd090d0db30f8b; source only, not deployed.

## Confirmed source defect
`MKT_File26_Connector::reconcile_batch()` was registered as a one-shot WordPress cron callback. Its early returns on unavailable schema or contended SQL named lock bypassed the `finally` block which normally schedules the next reconciliation. When WordPress had already dequeued the current event, this could stop automatic owner/search convergence until another front-end request re-registered it.

## Fix
If File26 connector remains active, use the existing duplicate-safe `maybe_schedule_reconcile()` before either early denial. Fail closed, do not skip owner policy, do not advance the persisted cursor. Inactive/unapproved connector still does not schedule jobs.

## Regression
Strengthened the actual-class RC36 harness to simulate a dequeued one-shot event before lock contention and schema failure, assert that exactly one follow-up event is scheduled, and verify the cursor is unchanged. The existing full 69-stage exact-head QA invokes this regression and runs 40 repeats on both supported PHP versions.

## Release limitations
This does not prove actual WP-Cron delivery reliability, File26 indexing SLA, staging, live database/migration parity, or an authorized cutover. Open release blockers #17 and #18 persist.
