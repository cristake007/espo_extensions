#!/usr/bin/env bash
set -euo pipefail
extension_root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# Use the repository harness for validation and packaging, with explicit runtime paths.
exec bash "${extension_root}/../build.sh" --extension "$extension_root" --zip files scripts
