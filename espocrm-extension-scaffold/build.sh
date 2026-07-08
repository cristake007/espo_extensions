#!/usr/bin/env bash
set -euo pipefail

PACKAGE_NAME="${1:-example-extension-scaffold}"
PACKAGE_VERSION="${2:-1.0.0}"
OUTPUT_DIR="../dist"
OUTPUT_FILE="${OUTPUT_DIR}/${PACKAGE_NAME}-${PACKAGE_VERSION}.zip"

if ! command -v zip >/dev/null 2>&1; then
    echo "Error: zip is not installed. Install it first, for example: sudo apt install zip" >&2
    exit 1
fi

if [ ! -f "manifest.json" ]; then
    echo "Error: manifest.json was not found. Run this script from the extension folder." >&2
    exit 1
fi

if [ ! -d "files" ]; then
    echo "Error: files/ directory was not found. Run this script from the extension folder." >&2
    exit 1
fi

mkdir -p "${OUTPUT_DIR}"
rm -f "${OUTPUT_FILE}"

paths=(manifest.json README.md files)
if [ -d scripts ]; then
    paths+=(scripts)
fi

zip -r "${OUTPUT_FILE}" "${paths[@]}" \
    -x "*.git*" \
    -x "*/.DS_Store" \
    -x "__MACOSX/*" \
    -x "dist/*"

echo "Created ${OUTPUT_FILE}"
echo "Upload this ZIP in EspoCRM: Administration > Extensions."
