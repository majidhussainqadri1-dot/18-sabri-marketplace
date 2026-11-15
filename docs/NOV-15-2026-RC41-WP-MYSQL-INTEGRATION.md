# RC41 — Isolated real WordPress 7.0.1 + MySQL 8 smoke (not live)

Plan V12.3 F18-NFR-007/008/009 and File 18 acceptance. On pull requests, a new GitHub Actions workflow installs a disposable MySQL 8 service, the exact WordPress 7.0.1 runtime, and the deterministic File18 RC39 archive. It activates the packaged plugin via WP-CLI and executes actual WordPress database checks.

The live integration smoke verifies canonical DB schema/unique keys, governance recall/evidence/legal-hold tables, language-facet schema, version markers, repeat-idempotent migration check, installed manifest hash parity, public empty search, zero-commission runtime invariant, fail-closed seller identity when File00 is absent, and scheduled owner maintenance. It does not invent or bypass companion identity/communication providers. All credentials in the workflow are disposable test-only values; no Hostinger account or external user data is touched.

**Acceptance boundary:** GitHub disposable WordPress/MySQL is neither Hostinger staging nor live; package deployment, exact deployed files, DB options, migration state and independent release signoffs remain unverified until authorized site access.
