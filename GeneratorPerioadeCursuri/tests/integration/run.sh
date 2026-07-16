#!/usr/bin/env bash
set -euo pipefail

extension_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
runtime_root="${ESPO_RUNTIME_ROOT:-/opt/crm.cursurituv.ro}"

cd "${runtime_root}"
docker compose ps --status running >/dev/null
docker compose exec -T espocrm php < "${extension_root}/tests/integration/runtime.php"
