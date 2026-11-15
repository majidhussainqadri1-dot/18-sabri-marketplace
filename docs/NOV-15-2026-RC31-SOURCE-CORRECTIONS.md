# File 18 RC31 — Source fixes, not a production release
Date: 2026-11-15. Exact baseline: PR #33 commit 94455ac6929716c58d5e5682b308bb096b3c37cf.
Plan V12.3: F18-FR-007/020, F18-NFR-003/006/008, CV-202/210.

## Four confirmed source/QA release defects corrected
1. File26 connector denied all public-read source access in Marketplace Safe Mode, although Safe Mode promises safe public browsing. The source now checks owner schema and canonical public eligibility while leaving read-only queries available.
2. File26 incremental/rebuild batches treated canonical owner SQL failures and DTO/media exceptions as ordinary missing records. They now fail the whole batch explicitly rather than falsely succeed with incomplete contents.
3. Forty counted full QA repetitions were skipped on PR #33 because the workflow only enabled them for branches prefixed fix/file18-. They now run on all tested refs.
4. New RC31 source was still emitted under a misleading RC30 ZIP/artifact filename. The deterministic release script, workflow, checksum path and artifact now consistently label the candidate RC31 while the WordPress plugin version remains 2.1.0.

## Regression
New actual-class fake-WordPress fixture tests Safe Mode, pagination, draft/suspended/recall suppression, private data allowlist, fresh version, SQL and media failure, and schema failure. Run as separate CI step and stage 56 of 60 in qa-static.sh.

## Still blocked
File26 event-to-tombstone delivery and activation, cross-module staging, actual WordPress/MySQL, 37 staging-acceptance items, 58 central CV, signed audit retention archive (#18), installed live code/DB/version/migration parity (#17), backup/restore and Founder approval. PR remains draft; no merge or deployment. Existing issues cannot be declared resolved.
Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
