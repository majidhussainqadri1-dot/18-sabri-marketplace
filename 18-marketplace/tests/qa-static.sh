#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

echo '[1/10] PHP syntax'
while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done < <(find "$ROOT" -type f -name '*.php' -print0)

echo '[2/10] JavaScript syntax'
node --check "$ROOT/assets/js/marketplace.js"

echo '[3/10] CSS structural balance'
python3 -c "import pathlib; s=pathlib.Path('$ROOT/assets/css/marketplace.css').read_text(); assert s.count('{')==s.count('}'); print('PASS: CSS braces balanced')"

echo '[4/10] State machines'
php "$ROOT/tests/test-state-machines.php"

echo '[5/10] Contract invariants'
php "$ROOT/tests/test-contracts.php"

echo '[6/10] Source architecture'
php "$ROOT/tests/test-source-architecture.php"

echo '[7/10] Adversarial invariants'
php "$ROOT/tests/test-review-invariants.php"

echo '[8/10] Required documentation and assets'
for f in marketplace.php uninstall.php readme.txt README.md CHANGELOG.md assets/css/marketplace.css assets/js/marketplace.js docs/REQUIREMENTS-TRACEABILITY.md docs/STAGING-ACCEPTANCE.md; do test -s "$ROOT/$f"; done

echo '[9/10] Forbidden artifact scan'
! grep -RInE --exclude-dir=tests --exclude='*.md' --exclude='readme.txt' '(BEGIN (RSA|OPENSSH|EC) PRIVATE KEY|AKIA[0-9A-Z]{16}|sk-[A-Za-z0-9]{20,})' "$ROOT"
! grep -RInE --include='*.php' 'CREATE TABLE.*(conversation|messages|calls|wallet|escrow|payout)' "$ROOT/includes"
! grep -RInE --include='*.php' '\b(eval|exec|shell_exec|passthru|system)[[:space:]]*\(' "$ROOT"

echo '[10/10] Release identity'
grep -q 'Version: 2.0.0' "$ROOT/marketplace.php"
grep -q 'Stable tag: 2.0.0' "$ROOT/readme.txt"
grep -q '0% platform commission' "$ROOT/readme.txt"
echo 'ALL STATIC AND CONTRACT TESTS PASSED'
