#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
TMP_B64="$(mktemp)"
TMP_ZIP="$(mktemp --suffix=.zip)"
trap 'rm -f "$TMP_B64" "$TMP_ZIP"' EXIT

cat "$ROOT"/bundles/source.b64.part-* > "$TMP_B64"
base64 --decode "$TMP_B64" > "$TMP_ZIP"

echo "94c71d70f406b8c583b9137b376a1ec887fda0fb5bb4218b4abb0f93245c48e4  $TMP_ZIP" | sha256sum --check --status

rm -rf "$ROOT/18-marketplace"
unzip -q "$TMP_ZIP" -d "$ROOT"
patch --batch --forward -d "$ROOT" -p1 < "$ROOT/patches/php81-compat.patch"

test -f "$ROOT/18-marketplace/marketplace.php"
test -x "$ROOT/18-marketplace/tests/qa-static.sh"
test -x "$ROOT/18-marketplace/tools/build-release.sh"
! grep -RIn --include='*.php' ': true|WP_Error' "$ROOT/18-marketplace"

echo "Canonical File 18 source reconstructed successfully."
