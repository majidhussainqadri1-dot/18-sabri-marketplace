# File18 RC39 — Deterministic artifact identity / translation packaging

Date: 15 November 2026; candidate source PR #33; underlying WP plugin and schema remain version 2.1.0.

The old RC31 release filename, ZIP checksum and GitHub artifact label no longer represented the source after event-to-tombstone, reconciliation, Urdu/Arabic localization and recalled private-listing redaction were added. The deterministic build and GitHub workflow now name the exact source candidate `18-marketplace-2.1.0-RC39.zip` throughout. The build additionally requires WordPress-format ur, ur_PK and ar PHP translations in the packaged plugin and excludes source-only tests/tools. SHA-256 and source manifest are determined by exact-head CI.

This is a build artifact identity fix, not a new live plugin version or a deployed upgrade. Staging migration parity, real WordPress/MySQL, File26 owner integration and Founder acceptance remain blocked.
