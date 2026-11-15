#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

echo '[1/68] PHP syntax'
while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done < <(find "$ROOT" -type f -name '*.php' -print0)

echo '[2/68] JavaScript syntax'
node --check "$ROOT/assets/js/marketplace.js"
node --check "$ROOT/assets/js/marketplace-completion.js"

echo '[3/68] CSS structural balance'
python3 -c "import pathlib; s=pathlib.Path('$ROOT/assets/css/marketplace.css').read_text(); assert s.count('{')==s.count('}'); print('PASS: CSS braces balanced')"

echo '[4/68] State machines'
php "$ROOT/tests/test-state-machines.php"

echo '[5/68] Contract invariants'
php "$ROOT/tests/test-contracts.php"

echo '[6/68] Source architecture'
php "$ROOT/tests/test-source-architecture.php"

echo '[7/68] Adversarial invariants'
php "$ROOT/tests/test-review-invariants.php"

echo '[8/68] October forty-scope corrective regressions'
php "$ROOT/tests/test-october-40-scope-regressions.php"

echo '[9/68] File 19 native notification contract'
php "$ROOT/tests/test-file19-notification-contract.php"

echo '[10/68] Safety report transactional fault-injection'
php "$ROOT/tests/test-report-atomicity.php"

echo '[11/68] Audit transaction and lock lifetime regressions'
php "$ROOT/tests/test-audit-transaction-lock.php"

echo '[12/68] File19 outbox required notification acknowledgement'
php "$ROOT/tests/test-outbox-required-notification-ack.php"

echo '[13/68] Price and stock strict domain validation'
php "$ROOT/tests/test-money-stock-validation.php"

echo '[14/68] File 17 native conversation/context boundary'
php "$ROOT/tests/test-file17-native-context-bridge.php"

echo '[15/68] Idempotency durable receipt fault injection'
php "$ROOT/tests/test-idempotency-durable-receipt.php"

echo '[16/68] Inbox crash/retry regression'
php "$ROOT/tests/test-inbox-crash-recovery.php"

echo '[17/68] File10 media transaction fault injection'
php "$ROOT/tests/test-media-atomicity.php"

echo '[18/68] Recall and legal hold transaction fault injection'
php "$ROOT/tests/test-governance-recall-hold-atomicity.php"

echo '[19/68] Seller identity and audit atomicity regression'
php "$ROOT/tests/test-seller-atomicity.php"

echo '[20/68] Forty plan-owner source anchors'
php "$ROOT/tests/test-seventh-40-plan-anchors.php"

echo '[21/68] Privacy legal-hold and DB-write fail-closed regression'
php "$ROOT/tests/test-privacy-hold-failclosed.php"

echo '[22/68] Governance deployed schema and migration truth'
php "$ROOT/tests/test-governance-schema-truth.php"

echo '[23/68] File18 core schema truth and drift'
php "$ROOT/tests/test-core-schema-truth.php"

echo '[24/68] File17 standalone legacy handoff migration'
php "$ROOT/tests/test-file17-legacy-only-migration.php"

echo '[25/68] Conditional CF05 source-of-truth boundary'
php "$ROOT/tests/test-cf05-conditional-boundary.php"

echo '[26/68] Privacy export SQL source completeness'
php "$ROOT/tests/test-privacy-export-completeness.php"

echo '[27/68] File00 explicit identity denial'
php "$ROOT/tests/test-file00-explicit-identity-denial.php"

echo '[28/68] Native File00 SMC_Contracts marketplace permission bridge'
php "$ROOT/tests/test-file00-native-marketplace-contract.php"

echo '[29/68] SQL failure / media rights / REST 503 regression'
php "$ROOT/tests/test-search-media-sql-failure.php"

echo '[30/68] CF06 conditional localization owner boundary'
php "$ROOT/tests/test-cf06-conditional-boundary.php"

echo '[31/68] Legal holds / immutable audit chain retention negative cases'
php "$ROOT/tests/test-retention-chain-hold-failclosed.php"

echo '[32/68] CF05 safe operational metric aggregate fault injection'
php "$ROOT/tests/test-derived-metrics-failclosed.php"

echo '[33/68] Seller suspension must rollback when listings cannot pause'
php "$ROOT/tests/test-maintenance-seller-pause-rollback.php"

echo '[34/68] Expiry on public, offer and deal flows; SQL fail-closed regression'
php "$ROOT/tests/test-expiry-commerce-integrity.php"

echo '[35/68] Dashboard and Seller Studio SQL failure regression'
php "$ROOT/tests/test-dashboard-studio-sql-failclosed.php"

echo '[36/68] Recall owner SQL and public takedown safety regression'
php "$ROOT/tests/test-rc17-recall-safety-failclosed.php"

echo '[37/68] Canonical prohibited policy and failed seed regression'
php "$ROOT/tests/test-rc17-policy-failclosed.php"

echo '[38/68] Core owner submission and moderator publication invariant regression'
php "$ROOT/tests/test-rc18-core-publication-gates.php"

echo '[39/68] Facet schema version, columns and primary-key truth'
php "$ROOT/tests/test-rc18-facet-schema-truth.php"

echo '[40/68] Atomic policy upgrade failure and newer owner version'
php "$ROOT/tests/test-rc19-policy-seeding-rollback.php"

echo '[41/68] Immutable safety/medical and zero-commission policy floor'
php "$ROOT/tests/test-rc19-immutable-policy-floor.php"

echo '[42/68] Transactional recall guards on new offers, counters, deal acceptance'
php "$ROOT/tests/test-rc20-transactional-recall-commitments.php"

echo '[43/68] Actual staging readiness and DB schema failure negative cases'
php "$ROOT/tests/test-rc20-readiness-schema-truth.php"

echo '[44/68] File00 deal cancel/dispute scope and direct deal audit rollback'
php "$ROOT/tests/test-rc21-deal-auth-audit-atomicity.php"

echo '[45/68] Direct listing/report/dispute owner audit atomicity'
php "$ROOT/tests/test-rc21-direct-owner-atomicity.php"

echo '[46/68] Durable idempotency unknown callback outcome cannot be replayed'
php "$ROOT/tests/test-rc22-idempotency-exception-uncertainty.php"

echo '[47/68] Reused offer/counter key must match exact stored payload'
php "$ROOT/tests/test-rc22-offer-payload-idempotency.php"

echo '[48/68] Authentication actor and private listing ownership, 25 canonical endpoints x 2 spoofed IDs'
php "$ROOT/tests/test-rc23-current-actor-ownership.php"

echo '[49/68] RC24 locked native evidence review rejects seller self-review and pauses invalid medical listings'
php "$ROOT/tests/test-rc24-native-evidence-review-safety.php"

echo '[50/68] RC25 evidence-save fresh owner and version CAS, listing-before-evidence locking'
php "$ROOT/tests/test-rc25-evidence-save-locking.php"

echo '[51/68] RC25 recall activation final-readback must be atomically verified'
php "$ROOT/tests/test-rc25-recall-readback-atomicity.php"

echo '[52/68] RC26 report and dispute canonical final readback rollback invariants'
php "$ROOT/tests/test-rc26-moderation-readback.php"

echo '[53/68] RC27 Commerce five native command receipts and rollbacks'
php "$ROOT/tests/test-rc27-commerce-final-readback.php"

echo '[54/68] RC28 Listing four owner mutation atomicity and final receipt rollback tests'
php "$ROOT/tests/test-rc28-listing-atomic-receipts.php"

echo '[55/68] RC30 actual audit-chain SQL failures and verified genesis'
php "$ROOT/tests/test-rc30-audit-source-failclosed.php"

echo '[56/68] File26 owner public-read, Safe Mode, SQL failure regression'
php "$ROOT/tests/test-rc31-file26-public-source.php"

echo '[57/68] Required documentation and assets'
for f in marketplace.php uninstall.php readme.txt README.md CHANGELOG.md assets/css/marketplace.css assets/js/marketplace.js assets/js/marketplace-completion.js includes/class-mkt-governance.php includes/class-mkt-plan-completion.php includes/class-mkt-finalization.php includes/class-mkt-release-gates.php docs/REQUIREMENTS-TRACEABILITY.md docs/STAGING-ACCEPTANCE.md docs/FOUR-ROUND-GOVERNING-PLAN-AUDIT.md; do test -s "$ROOT/$f"; done

echo '[58/68] Client listing card same-origin and malformed-API regression'
node "$ROOT/tests/test-rc32-client-listing-card.mjs"

echo '[59/68] Structured JSON-LD closing-script safety regression'
php "$ROOT/tests/test-rc33-structured-data-boundary.php"

echo '[60/68] Offer idempotency retry and saved-button state regression'
node "$ROOT/tests/test-rc34-client-offer-retry.mjs"

echo '[61/68] File26 owner event receipt, upsert and tombstone regression'
php "$ROOT/tests/test-rc35-file26-event-receipt.php"

echo '[62/68] File26 bounded persistent owner reconciliation regression'
php "$ROOT/tests/test-rc36-file26-owner-reconcile.php"

echo '[63/68] WordPress Urdu/Arabic translations and RTL regression'
php "$ROOT/tests/test-rc37-localization.php"

echo '[64/68] Recall-only privacy boundary regression'
php "$ROOT/tests/test-rc38-recall-privacy-boundary.php"

echo '[65/68] Forbidden artifact scan'
! grep -RInE --exclude-dir=tests --exclude='*.md' --exclude='readme.txt' '(BEGIN (RSA|OPENSSH|EC) PRIVATE KEY|AKIA[0-9A-Z]{16}|sk-[A-Za-z0-9]{20,})' "$ROOT"
! grep -RInE --include='*.php' 'CREATE TABLE.*(conversation|messages|calls|wallet|escrow|payout)' "$ROOT/includes"
! grep -RInE --include='*.php' '\b(eval|exec|shell_exec|passthru|system)[[:space:]]*\(' "$ROOT"

echo '[66/68] Read-only deployed manifest and migration truth regression'
php "$ROOT/tests/test-rc40-deployed-reality.php"

echo '[67/68] Release identity'
grep -q 'Version: 2.1.0' "$ROOT/marketplace.php"
grep -q "define('MKT_VERSION', '2.1.0')" "$ROOT/marketplace.php"
grep -q 'Stable tag: 2.1.0' "$ROOT/readme.txt"
grep -q '0% platform commission' "$ROOT/readme.txt"
# Candidate identity must match the build script and the exact-head CI artifact.
grep -Fq 'PACKAGE="18-marketplace-${VERSION}-RC39.zip"' "$ROOT/tools/build-release.sh"

echo '[68/68] Four-plan governing invariants'
php "$ROOT/tests/test-four-plan-invariants.php"
echo 'ALL STATIC AND CONTRACT TESTS PASSED'
