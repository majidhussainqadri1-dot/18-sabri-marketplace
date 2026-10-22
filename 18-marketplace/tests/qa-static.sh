#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

echo '[1/59] PHP syntax'
while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done < <(find "$ROOT" -type f -name '*.php' -print0)

echo '[2/59] JavaScript syntax'
node --check "$ROOT/assets/js/marketplace.js"
node --check "$ROOT/assets/js/marketplace-completion.js"

echo '[3/59] CSS structural balance'
python3 -c "import pathlib; s=pathlib.Path('$ROOT/assets/css/marketplace.css').read_text(); assert s.count('{')==s.count('}'); print('PASS: CSS braces balanced')"

echo '[4/59] State machines'
php "$ROOT/tests/test-state-machines.php"

echo '[5/59] Contract invariants'
php "$ROOT/tests/test-contracts.php"

echo '[6/59] Source architecture'
php "$ROOT/tests/test-source-architecture.php"

echo '[7/59] Adversarial invariants'
php "$ROOT/tests/test-review-invariants.php"

echo '[8/59] October forty-scope corrective regressions'
php "$ROOT/tests/test-october-40-scope-regressions.php"

echo '[9/59] File 19 native notification contract'
php "$ROOT/tests/test-file19-notification-contract.php"

echo '[10/59] Safety report transactional fault-injection'
php "$ROOT/tests/test-report-atomicity.php"

echo '[11/59] Audit transaction and lock lifetime regressions'
php "$ROOT/tests/test-audit-transaction-lock.php"

echo '[12/59] File19 outbox required notification acknowledgement'
php "$ROOT/tests/test-outbox-required-notification-ack.php"

echo '[13/59] Price and stock strict domain validation'
php "$ROOT/tests/test-money-stock-validation.php"

echo '[14/59] File 17 native conversation/context boundary'
php "$ROOT/tests/test-file17-native-context-bridge.php"

echo '[15/59] Idempotency durable receipt fault injection'
php "$ROOT/tests/test-idempotency-durable-receipt.php"

echo '[16/59] Inbox crash/retry regression'
php "$ROOT/tests/test-inbox-crash-recovery.php"

echo '[17/59] File10 media transaction fault injection'
php "$ROOT/tests/test-media-atomicity.php"

echo '[18/59] Recall and legal hold transaction fault injection'
php "$ROOT/tests/test-governance-recall-hold-atomicity.php"

echo '[19/59] Seller identity and audit atomicity regression'
php "$ROOT/tests/test-seller-atomicity.php"

echo '[20/59] Forty plan-owner source anchors'
php "$ROOT/tests/test-seventh-40-plan-anchors.php"

echo '[21/59] Privacy legal-hold and DB-write fail-closed regression'
php "$ROOT/tests/test-privacy-hold-failclosed.php"

echo '[22/59] Governance deployed schema and migration truth'
php "$ROOT/tests/test-governance-schema-truth.php"

echo '[23/59] File18 core schema truth and drift'
php "$ROOT/tests/test-core-schema-truth.php"

echo '[24/59] File17 standalone legacy handoff migration'
php "$ROOT/tests/test-file17-legacy-only-migration.php"

echo '[25/59] Conditional CF05 source-of-truth boundary'
php "$ROOT/tests/test-cf05-conditional-boundary.php"

echo '[26/59] Privacy export SQL source completeness'
php "$ROOT/tests/test-privacy-export-completeness.php"

echo '[27/59] File00 explicit identity denial'
php "$ROOT/tests/test-file00-explicit-identity-denial.php"

echo '[28/59] Native File00 SMC_Contracts marketplace permission bridge'
php "$ROOT/tests/test-file00-native-marketplace-contract.php"

echo '[29/59] SQL failure / media rights / REST 503 regression'
php "$ROOT/tests/test-search-media-sql-failure.php"

echo '[30/59] CF06 conditional localization owner boundary'
php "$ROOT/tests/test-cf06-conditional-boundary.php"

echo '[31/59] Legal holds / immutable audit chain retention negative cases'
php "$ROOT/tests/test-retention-chain-hold-failclosed.php"

echo '[32/59] CF05 safe operational metric aggregate fault injection'
php "$ROOT/tests/test-derived-metrics-failclosed.php"

echo '[33/59] Seller suspension must rollback when listings cannot pause'
php "$ROOT/tests/test-maintenance-seller-pause-rollback.php"

echo '[34/59] Expiry on public, offer and deal flows; SQL fail-closed regression'
php "$ROOT/tests/test-expiry-commerce-integrity.php"

echo '[35/59] Dashboard and Seller Studio SQL failure regression'
php "$ROOT/tests/test-dashboard-studio-sql-failclosed.php"

echo '[36/59] Recall owner SQL and public takedown safety regression'
php "$ROOT/tests/test-rc17-recall-safety-failclosed.php"

echo '[37/59] Canonical prohibited policy and failed seed regression'
php "$ROOT/tests/test-rc17-policy-failclosed.php"

echo '[38/59] Core owner submission and moderator publication invariant regression'
php "$ROOT/tests/test-rc18-core-publication-gates.php"

echo '[39/59] Facet schema version, columns and primary-key truth'
php "$ROOT/tests/test-rc18-facet-schema-truth.php"

echo '[40/59] Atomic policy upgrade failure and newer owner version'
php "$ROOT/tests/test-rc19-policy-seeding-rollback.php"

echo '[41/59] Immutable safety/medical and zero-commission policy floor'
php "$ROOT/tests/test-rc19-immutable-policy-floor.php"

echo '[42/59] Transactional recall guards on new offers, counters, deal acceptance'
php "$ROOT/tests/test-rc20-transactional-recall-commitments.php"

echo '[43/59] Actual staging readiness and DB schema failure negative cases'
php "$ROOT/tests/test-rc20-readiness-schema-truth.php"

echo '[44/59] File00 deal cancel/dispute scope and direct deal audit rollback'
php "$ROOT/tests/test-rc21-deal-auth-audit-atomicity.php"

echo '[45/59] Direct listing/report/dispute owner audit atomicity'
php "$ROOT/tests/test-rc21-direct-owner-atomicity.php"

echo '[46/59] Durable idempotency unknown callback outcome cannot be replayed'
php "$ROOT/tests/test-rc22-idempotency-exception-uncertainty.php"

echo '[47/59] Reused offer/counter key must match exact stored payload'
php "$ROOT/tests/test-rc22-offer-payload-idempotency.php"

echo '[48/59] Authentication actor and private listing ownership, 25 canonical endpoints x 2 spoofed IDs'
php "$ROOT/tests/test-rc23-current-actor-ownership.php"

echo '[49/59] RC24 locked native evidence review rejects seller self-review and pauses invalid medical listings'
php "$ROOT/tests/test-rc24-native-evidence-review-safety.php"

echo '[50/59] RC25 evidence-save fresh owner and version CAS, listing-before-evidence locking'
php "$ROOT/tests/test-rc25-evidence-save-locking.php"

echo '[51/59] RC25 recall activation final-readback must be atomically verified'
php "$ROOT/tests/test-rc25-recall-readback-atomicity.php"

echo '[52/59] RC26 report and dispute canonical final readback rollback invariants'
php "$ROOT/tests/test-rc26-moderation-readback.php"

echo '[53/59] RC27 Commerce five native command receipts and rollbacks'
php "$ROOT/tests/test-rc27-commerce-final-readback.php"

echo '[54/59] RC28 Listing four owner mutation atomicity and final receipt rollback tests'
php "$ROOT/tests/test-rc28-listing-atomic-receipts.php"

echo '[55/59] RC30 actual audit-chain SQL failures and verified genesis'
php "$ROOT/tests/test-rc30-audit-source-failclosed.php"

echo '[56/59] Required documentation and assets'
for f in marketplace.php uninstall.php readme.txt README.md CHANGELOG.md assets/css/marketplace.css assets/js/marketplace.js assets/js/marketplace-completion.js includes/class-mkt-governance.php includes/class-mkt-plan-completion.php includes/class-mkt-finalization.php includes/class-mkt-release-gates.php docs/REQUIREMENTS-TRACEABILITY.md docs/STAGING-ACCEPTANCE.md docs/FOUR-ROUND-GOVERNING-PLAN-AUDIT.md; do test -s "$ROOT/$f"; done

echo '[57/59] Forbidden artifact scan'
! grep -RInE --exclude-dir=tests --exclude='*.md' --exclude='readme.txt' '(BEGIN (RSA|OPENSSH|EC) PRIVATE KEY|AKIA[0-9A-Z]{16}|sk-[A-Za-z0-9]{20,})' "$ROOT"
! grep -RInE --include='*.php' 'CREATE TABLE.*(conversation|messages|calls|wallet|escrow|payout)' "$ROOT/includes"
! grep -RInE --include='*.php' '\b(eval|exec|shell_exec|passthru|system)[[:space:]]*\(' "$ROOT"

echo '[58/59] Release identity'
grep -q 'Version: 2.1.0' "$ROOT/marketplace.php"
grep -q "define('MKT_VERSION', '2.1.0')" "$ROOT/marketplace.php"
grep -q 'Stable tag: 2.1.0' "$ROOT/readme.txt"
grep -q '0% platform commission' "$ROOT/readme.txt"

echo '[59/59] Four-plan governing invariants'
php "$ROOT/tests/test-four-plan-invariants.php"
echo 'ALL STATIC AND CONTRACT TESTS PASSED'
