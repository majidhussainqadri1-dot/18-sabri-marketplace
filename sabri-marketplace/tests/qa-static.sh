#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

find "$ROOT" -type f -name '*.php' -not -path '*/vendor/*' -print0 | while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done
node --check "$ROOT/assets/js/marketplace.js"

grep -q "Version: 1.2.0" "$ROOT/sabri-marketplace.php"
grep -q "sabri_notify_user" "$ROOT/includes/class-smp-integrations.php"
grep -q "wp_privacy_personal_data_exporters" "$ROOT/includes/class-smp-privacy.php"
grep -q "wp_privacy_personal_data_erasers" "$ROOT/includes/class-smp-privacy.php"
grep -q "visible_product_row" "$ROOT/includes/class-smp-ajax.php"
grep -q "SMP_Rate_Limiter::enforce" "$ROOT/includes/class-smp-ajax.php"
grep -q "aes-256-gcm" "$ROOT/includes/class-smp-utils.php"
grep -q "SMP_PRIVATE_STORAGE_DIR" "$ROOT/includes/class-smp-utils.php"
grep -q "health/details" "$ROOT/includes/class-smp-rest.php"
grep -q "SMP_DB::tables()" "$ROOT/includes/class-smp-admin.php"

if grep -q "CREATE TABLE .*smp_.*notifications" "$ROOT/includes/class-smp-db.php"; then
  echo "Local notification table creation detected" >&2; exit 1
fi
if grep -q "table('notifications').*INSERT\|insert(.*notifications" "$ROOT/includes"/*.php; then
  echo "New local notification write detected" >&2; exit 1
fi
if grep -q "identityNumber.*name=\|name=\"identityNumber\"" "$ROOT/assets/js/marketplace.js"; then
  echo "Duplicate seller identity collection detected" >&2; exit 1
fi
if grep -q "readfile(\$path)" "$ROOT/includes/class-smp-ajax.php"; then
  echo "Raw encrypted file streaming detected" >&2; exit 1
fi

echo "Sabri Marketplace static QA passed."
