#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

echo '[1/43] PHP syntax'
while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done < <(find "$ROOT" -type f -name '*.php' -print0)

echo '[2/43] JavaScript syntax'
node --check "$ROOT/assets/js/marketplace.js"
node --check "$ROOT/assets/js/marketplace-completion.js"

echo '[3/43] CSS structural balance'
python3 -c "import pathlib; s=pathlib.Path('$ROOT/assets/css/marketplace.css').read_text(); assert s.count('{')==s.count('}'); print('PASS: CSS braces balanced')"

echo '[4/43] State machines'
php "$ROOT/tests/test-state-machines.php"

echo '[5/43] Contract invariants'
php "$ROOT/tests/test-contracts.php"

echo '[6/43] Source architecture'
php "$ROOT/tests/test-source-architecture.php"

echo '[7/43] Adversarial invariants'
php "$ROOT/tests/test-review-invariants.php"

echo '[8/43] October forty-scope corrective regressions'
php "$ROOT/tests/test-october-40-scope-regressions.php"

echo '[9/43] File 19 native notification contract'
php "$ROOT/tests/test-file19-notification-contract.php"

echo '[10/43] Safety report transactional fault-injection'
php "$ROOT/tests/test-report-atomicity.php"

echo '[11/43] Audit transaction and lock lifetime regressions'
php "$ROOT/tests/test-audit-transaction-lock.php"

echo '[12/43] File19 outbox required notification acknowledgement'
php "$ROOT/tests/test-outbox-required-notification-ack.php"

echo '[13/43] Price and stock strict domain validation'
php "$ROOT/tests/test-money-stock-validation.php"

echo '[14/43] File 17 native conversation/context boundary'
php "$ROOT/tests/test-file17-native-context-bridge.php"

echo '[15/43] Idempotency durable receipt fault injection'
php "$ROOT/tests/test-idempotency-durable-receipt.php"

echo '[16/43] Inbox crash/retry regression'
php "$ROOT/tests/test-inbox-crash-recovery.php"

echo '[17/43] File10 media transaction fault injection'
php "$ROOT/tests/test-media-atomicity.php"

echo '[18/43] Recall and legal hold transaction fault injection'
php "$ROOT/tests/test-governance-recall-hold-atomicity.php"

echo '[19/43] Seller identity and audit atomicity regression'
php "$ROOT/tests/test-seller-atomicity.php"

echo '[20/43] Forty plan-owner source anchors'
php "$ROOT/tests/test-seventh-40-plan-anchors.php"

echo '[21/43] Privacy legal-hold and DB-write fail-closed regression'
php "$ROOT/tests/test-privacy-hold-failclosed.php"

echo '[22/43] Governance deployed schema and migration truth'
php "$ROOT/tests/test-governance-schema-truth.php"

echo '[23/43] File18 core schema truth and drift'
php "$ROOT/tests/test-core-schema-truth.php"

echo '[24/43] File17 standalone legacy handoff migration'
php "$ROOT/tests/test-file17-legacy-only-migration.php"

echo '[25/43] Conditional CF05 source-of-truth boundary'
php "$ROOT/tests/test-cf05-conditional-boundary.php"

echo '[26/43] Privacy export SQL source completeness'
php "$ROOT/tests/test-privacy-export-completeness.php"

echo '[27/43] File00 explicit identity denial'
php "$ROOT/tests/test-file00-explicit-identity-denial.php"

echo '[28/43] Native File00 SMC_Contracts marketplace permission bridge'
php "$ROOT/tests/test-file00-native-marketplace-contract.php"

echo '[29/43] SQL failure / media rights / REST 503 regression'
php "$ROOT/tests/test-search-media-sql-failure.php"

echo '[30/43] CF06 conditional localization owner boundary'
php "$ROOT/tests/test-cf06-conditional-boundary.php"

echo '[31/43] Legal holds / immutable audit chain retention negative cases'
php "$ROOT/tests/test-retention-chain-hold-failclosed.php"

echo '[32/43] CF05 safe operational metric aggregate fault injection'
php "$ROOT/tests/test-derived-metrics-failclosed.php"

echo '[33/43] Seller suspension must rollback when listings cannot pause'
php "$ROOT/tests/test-maintenance-seller-pause-rollback.php"

echo '[34/43] Expiry on public, offer and deal flows; SQL fail-closed regression'
php "$ROOT/tests/test-expiry-commerce-integrity.php"

echo '[35/43] Dashboard and Seller Studio SQL failure regression'
php "$ROOT/tests/test-dashboard-studio-sql-failclosed.php"

echo '[36/43] Recall owner SQL and public takedown safety regression'
php "$ROOT/tests/test-rc17-recall-safety-failclosed.php"

echo '[37/43] Canonical prohibited policy and failed seed regression'
php "$ROOT/tests/test-rc17-policy-failclosed.php"

echo '[38/43] Core owner submission and moderator publication invariant regression'
php "$ROOT/tests/test-rc18-core-publication-gates.php"

echo '[39/43] Facet schema version, columns and primary-key truth'
php "$ROOT/tests/test-rc18-facet-schema-truth.php"

echo '[40/43] Required documentation and assets'
for f in marketplace.php uninstall.php readme.txt README.md CHANGELOG.md assets/css/marketplace.css assets/js/marketplace.js assets/js/marketplace-completion.js includes/class-mkt-governance.php includes/class-mkt-plan-completion.php includes/class-mkt-finalization.php includes/class-mkt-release-gates.php docs/REQUIREMENTS-TRACEABILITY.md docs/STAGING-ACCEPTANCE.md docs/FOUR-ROUND-GOVERNING-PLAN-AUDIT.md; do test -s "$ROOT/$f"; done

echo '[41/43] Forbidden artifact scan'
! grep -RInE --exclude-dir=tests --exclude='*.md' --exclude='readme.txt' '(BEGIN (RSA|OPENSSH|EC) PRIVATE KEY|AKIA[0-9A-Z]{16}|sk-[A-Za-z0-9]{20,})' "$ROOT"
! grep -RInE --include='*.php' 'CREATE TABLE.*(conversation|messages|calls|wallet|escrow|payout)' "$ROOT/includes"
! grep -RInE --include='*.php' '\b(eval|exec|shell_exec|passthru|system)[[:space:]]*\(' "$ROOT"

echo '[42/43] Release identity'
grep -q 'Version: 2.1.0' "$ROOT/marketplace.php"
grep -q "define('MKT_VERSION', '2.1.0')" "$ROOT/marketplace.php"
grep -q 'Stable tag: 2.1.0' "$ROOT/readme.txt"
grep -q '0% platform commission' "$ROOT/readme.txt"

echo '[43/43] Four-plan governing invariants'
php "$ROOT/tests/test-four-plan-invariants.php"
echo 'ALL STATIC AND CONTRACT TESTS PASSED'
