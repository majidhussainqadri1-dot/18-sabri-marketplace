# File18 RC40 — Read-only exact installed artifact and schema-state freeze

Plan V12.3 F18-NFR-006/007/008/009. Date: 15 November 2026.

The existing System Status reports core/governance/facet table shape and various dependencies, but did not verify the installed package's source-manifest hashes, extra files or relevant migration markers in one safe snapshot. New MKT_Deployment_Reality::snapshot() reads only allowlisted WordPress options and PHP/WP/MySQL versions, then verifies each canonical expected deployed plugin file against MANIFEST.sha256, rejects path traversal, checks for absent/tampered/undeclared files and caps traversal size. It exposes no WordPress credentials, private patient records, absolute server paths or other options. Admin System Status shows the safe manifest summary; failures are warnings (never falsely "verified").

A local manifest hash is NOT the actual deployment ZIP SHA-256, and is NOT proof of GitHub HEAD parity; both remain explicitly unverified until independent operator evidence. The source-level actual-class regression tests tampering, unknown files, missing/invalid manifest, traversal, expected WP/DB markers, and refusal to invent live verification. Included in CI and stage 66 of 68.

Pending: real deployed artifact/DB/migration inspection, authorized admin connection, Hostinger staging, recovery/rollback proof, File26 activation. Issues #17/#18 remain blocked; not a live fix.
