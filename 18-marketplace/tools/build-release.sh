#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PROJECT="$(dirname "$ROOT")"
OUT="${1:-$PROJECT/release}"
VERSION="2.0.1"
PACKAGE="18-marketplace-${VERSION}-RC2.zip"
SOURCE_DATE_EPOCH="${SOURCE_DATE_EPOCH:-1786089600}"
export SOURCE_DATE_EPOCH
mkdir -p "$OUT"
rm -f "$ROOT/MANIFEST.sha256" "$OUT/$PACKAGE" "$OUT/$PACKAGE.sha256"

"$ROOT/tests/qa-static.sh"

(
  cd "$ROOT"
  find . -type f ! -path './.git/*' ! -path './tests/*' ! -path './tools/*' ! -name 'MANIFEST.sha256' -print0 | sort -z | xargs -0 sha256sum > MANIFEST.sha256
)

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
mkdir -p "$STAGE/18-marketplace"
cp -a "$ROOT/." "$STAGE/18-marketplace/"
rm -rf "$STAGE/18-marketplace/tests" "$STAGE/18-marketplace/tools" "$STAGE/18-marketplace/.git"
find "$STAGE" -exec touch -h -d "@$SOURCE_DATE_EPOCH" {} +
(
  cd "$STAGE"
  find 18-marketplace -type f -print | LC_ALL=C sort | zip -X -q "$OUT/$PACKAGE" -@
)
sha256sum "$OUT/$PACKAGE" > "$OUT/$PACKAGE.sha256"
unzip -t "$OUT/$PACKAGE" >/dev/null
python3 -c "import sys,zipfile; z=zipfile.ZipFile(sys.argv[1]); n=z.namelist(); assert n and all(x.startswith('18-marketplace/') for x in n); assert not any('..' in x.split('/') for x in n); assert '18-marketplace/marketplace.php' in n; assert '18-marketplace/MANIFEST.sha256' in n; print('PASS: archive path safety and canonical top-level folder')" "$OUT/$PACKAGE"
echo "Built $OUT/$PACKAGE"
cat "$OUT/$PACKAGE.sha256"
