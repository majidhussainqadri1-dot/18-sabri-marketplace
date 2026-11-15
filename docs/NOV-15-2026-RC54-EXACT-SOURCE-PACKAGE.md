# File 18 — RC54 deterministic artifact identity, 15 November 2026

After the RC43–RC53 changes the package name still advertised RC39 despite different source bytes. This commits a coherent exact-head release candidate identity for both GitHub Actions workflows, the deterministic ZIP builder and the release QA identity assertion: `18-marketplace-2.1.0-RC54.zip`. The candidate still carries WordPress plugin version `2.1.0` and should not be installed until required staging approvals.

Evidence required:
- both PHP 8.1/8.3 exact-head 40-pass QA matrices green;
- clean WordPress 7.0.1/MySQL 8.0 isolated integration smoke;
- identical SHA-256 from independent deterministic ZIP builds;
- actual source manifest verifies on a separate staging install.

This is not a live deployment or an approved File26 cutover. Issues #17/#18 and all 37 Hostinger staging acceptance items remain unverified until tested in a connected environment.