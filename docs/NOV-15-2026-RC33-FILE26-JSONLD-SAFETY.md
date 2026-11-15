# File18 — File26 boot order and JSON-LD safety corrective review
Date: 15 November 2026, source candidate on PR #33. Plan V12.3 F18-FR-008/020, F18-NFR-001/003/005/010.

## 2 additional source defects corrected

1. **File26 registration before schema verification:** the File18 plugin loaded the File26 connector at plugin file-load time, immediately calling an available File26 registry function even though File26's plugin boot/migration occurs on \`plugins_loaded\`. Plugin order could cause an early database write or duplicate registration. The connector now only installs its manifest filter, to be consumed once during File26's schema-checked boot; it remains \`proposed\`. Actual File26 registration and activation remain unverified in staging.
2. **JSON-LD script termination:** Structured product metadata was serialized into an HTML \`<script type="application/ld+json">\` with \`JSON_UNESCAPED_SLASHES\`, leaving raw user-supplied \`</script>\` sequences able to prematurely close the element. WordPress JSON serialization now hex-escapes HTML metacharacters and tolerates invalid UTF-8 replacement while preserving decoded JSON values.

## Regression
- Strengthen RC31 actual connector fixture with eager registry stub and exactly one deferred manifest filter.
- Add RC33 actual PHP routes-class execution with deliberately hostile product title and seller name; assert only one closing script tag, no injection, and valid JSON semantic roundtrip.
- Add dedicated CI step and include the test in 62 full-suite stages, 40 repeated runs per PHP 8.1 and PHP 8.3.

## Release governance
Do not merge/deploy based on source-level tests. Required real WordPress/MySQL, File26 owner contracts and event-to-tombstone proof, signed immutable audit retention approval (#18), live deployed version/schema/migration parity (#17), 37 File18 staging gates, founder approval and controlled release remain pending.
Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
