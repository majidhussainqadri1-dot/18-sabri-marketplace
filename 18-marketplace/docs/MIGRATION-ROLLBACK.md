# Migration, Upgrade, Rollback and Legacy Cutover

## Supported source

The controlled upgrade path is from the historical File 18 `smp_*` 1.1.0/1.2.0 candidate to canonical runtime 2.0.0.

## Non-destructive migration

1. Inventory `smp_sellers`, `smp_products`, `smp_conversations`, `smp_messages` and related tables.
2. Create canonical `mkt_*` schema idempotently.
3. Import seller linkage and product/listing fields into `mkt_sellers` and `mkt_listings`.
4. Legacy public listings enter `review`, not `active`, because final policy/media assertions must be revalidated.
5. Record legacy conversation/message IDs in `mkt_migration_handoffs` for File 17.
6. Do not delete or overwrite legacy tables during migration.
7. After File 17 confirms import references, mark handoff rows completed.
8. Reconcile seller eligibility, search projections, routes, caches and events.

## Required staging evidence

- Before/after counts for each source and target table.
- Sampled field-level diffs and quarantine reasons.
- Idempotent second migration run with zero duplicate records.
- Concurrent activation lock test.
- File 17 handoff success and failure/retry tests.
- Search and public URL reconciliation.
- Privacy export/erasure before and after migration.

## Backup and rollback

A valid backup includes database, plugin files, relevant object/media references, keys/configuration and release artifacts. “Backup succeeded” is not accepted until isolated restore proves record counts, key use, route repair, cache/search rebuild and a real buyer–seller journey.

Rollback procedure:

1. Pause high-risk mutations through Marketplace Safe Mode.
2. Capture post-cutover records created after the migration checkpoint.
3. Restore the pre-cutover database/files in an isolated staging copy.
4. Apply a documented compensating export of post-cutover records; do not silently discard them.
5. Restore route and shell registry state, clear LiteSpeed/object caches and rebuild search projections.
6. Reactivate the previous package only if its schema compatibility is confirmed.
7. Run public browse, seller draft, offer, deal, report and privacy smoke tests.
8. Record the incident, cause, data delta, recovery time and Founder decision.

Destructive uninstall is disabled unless both `mkt_purge_on_uninstall` and `MKT_ALLOW_DESTRUCTIVE_PURGE=true` are explicitly set by an authorized operator.
