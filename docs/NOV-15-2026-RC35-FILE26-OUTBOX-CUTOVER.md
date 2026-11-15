# File18 / File26 RC35 outbox-to-index contract — source only
Date: 2026-11-15. V12.3 F18-FR-020, F18-NFR-003, CV-202 and CV-210.
Candidate source: PR #33, not main or deployed.

The previous File26 connector only supported read-only search snapshots. An already-active File26 connector now receives selected canonical Listing/Recall events through File18's real outbox, revalidates current owner row/version/seller/publicity/expiry/recall, and either upserts an allowlisted public document or tombstones it at the owner's current version. Source events are not treated as acknowledged merely because an action hook fired.

Outbox final acknowledgement requires a dedicated `mkt_required_event_consumers_acknowledged` receipt. A positive File19 or platform ACK cannot cover a failed File26 owner receipt. Existing retry/lease/dead-letter mechanisms continue; this change is fully dormant while the File26 owner connector remains `proposed`.

RC35 runs actual connector PHP against fake WordPress/File26/DB fixtures with 17+ negative/positive assertions; existing File19 acknowledgement regression also tests missing owner consumer ACK. QA now contains 64 stages (40 repeats per PHP 8.1 and 8.3).

**Remaining:** Seller-wide bulk suspension fanout has no proven per-listing tombstone events, File26 full reindex/tombstone recovery and staging activation need actual MySQL/WordPress evidence. No live change made. Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
