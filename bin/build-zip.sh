#!/usr/bin/env bash
#
# Builds a distributable plugin zip.
#
# The zip always contains a single clean top-level folder named after the
# plugin slug (additional-gallery-for-hivepress/), so a manual install lands
# in the correct folder and WordPress shows no "folder mismatch" warnings,
# and the GitHub updater can match it on future updates.
#
# Usage:
#   bin/build-zip.sh              -> dist/additional-gallery-for-hivepress.zip   (release asset)
#   bin/build-zip.sh --versioned  -> dist/additional-gallery-for-hivepress-<version>.zip (internal testing)
#
# Attach the release-asset zip (unversioned filename) to each GitHub release,
# so the "latest" download link and the in-plugin updater keep working.

set -euo pipefail

SLUG="additional-gallery-for-hivepress"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MAIN_FILE="$ROOT/$SLUG.php"

if [[ ! -f "$MAIN_FILE" ]]; then
	echo "Error: cannot find $SLUG.php in $ROOT" >&2
	exit 1
fi

VERSION="$(grep -m1 -E '^[[:space:]]*\*[[:space:]]*Version:' "$MAIN_FILE" | sed -E 's/.*Version:[[:space:]]*//; s/[[:space:]]*$//')"

if [[ -z "$VERSION" ]]; then
	echo "Error: could not read Version from the plugin header" >&2
	exit 1
fi

VERSIONED=0
if [[ "${1:-}" == "--versioned" ]]; then
	VERSIONED=1
fi

DIST="$ROOT/dist"
STAGE="$DIST/$SLUG"

# Files and directories included in the distributable (everything else -
# dev/QA files, the build script, git metadata - is excluded).
INCLUDE=(
	"$SLUG.php"
	"includes"
	"assets"
	"languages"
	"readme.txt"
	"README.md"
	"LICENSE"
)

rm -rf "$STAGE"
mkdir -p "$STAGE"

for item in "${INCLUDE[@]}"; do
	if [[ -e "$ROOT/$item" ]]; then
		cp -R "$ROOT/$item" "$STAGE/"
	fi
done

# Never ship stray build artefacts or OS cruft.
find "$STAGE" -name '.DS_Store' -delete 2>/dev/null || true
find "$STAGE" -name 'Thumbs.db' -delete 2>/dev/null || true

if [[ $VERSIONED -eq 1 ]]; then
	OUT="$DIST/${SLUG}-${VERSION}.zip"
else
	OUT="$DIST/${SLUG}.zip"
fi

rm -f "$OUT"

( cd "$DIST" && zip -rqX "$(basename "$OUT")" "$SLUG" )

rm -rf "$STAGE"

echo "Built:  $OUT"
echo "Folder: $SLUG/  (inside the zip)"
echo "Version: $VERSION"
