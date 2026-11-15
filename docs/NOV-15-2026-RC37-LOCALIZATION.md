# File18 RC37 — Urdu / Arabic / RTL source catalogs (15 November 2026)
Plan V12.3: F18-NFR-005 and F18-NFR-010, project global RTL.

WordPress performant PHP translation catalogs now exist for ur, ur_PK and ar, following the WordPress 6.5+ PHP l10n message map. 218 UI/action keys are translated in each locale, including Marketplace browse, seller forms, deals, safety/recall/report labels and common JavaScript notices. JavaScript action responses are now read from MKT_APP.strings produced by the canonical WordPress gettext domain. Structural CSS includes explicit RTL direction, safe long-label wrapping and 360px containment. No File20 shell/theme duplication.

Automated RC37 validates catalog shape/headers, placeholder preservation, critical UI vocabulary, localized JavaScript hooks and RTL style anchors, both PHP matrix members. Translations are a source-level initial catalog: full Arabic/Urdu native-speaker proofreading, actual WordPress locale loading, mixed-language and 320px/400% zoom/screen-reader acceptance remain staging gates.

No live package deployed; File18 PR #33 remains draft. Exact deployed source, database and migration are unverified.
