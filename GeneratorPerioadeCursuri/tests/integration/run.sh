#!/usr/bin/env bash
set -euo pipefail

extension_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
compose_root='/var/www/espocrm'
compose_file="${compose_root}/docker-compose.yml"
approved_url='http://178.104.183.29'

[ -f "$compose_file" ] || { echo 'Approved test Compose file is missing.' >&2; exit 1; }
container_compose_identity="$(docker inspect espocrm --format '{{index .Config.Labels "com.docker.compose.project.working_dir"}}|{{index .Config.Labels "com.docker.compose.project.config_files"}}' 2>/dev/null)"
[ "$container_compose_identity" = "${compose_root}|${compose_file}" ] || {
    echo 'The espocrm container does not belong to the approved test Compose project.' >&2
    exit 1
}
docker compose -f "$compose_file" ps --status running --services | rg -qx 'espocrm' || {
    echo 'The approved EspoCRM service is not running.' >&2
    exit 1
}
identity="$(docker compose -f "$compose_file" exec -T espocrm php -r '$config=require "data/config.php"; $state=require "data/state.php"; echo ($config["siteUrl"] ?? ""), "|", ($state["version"] ?? "");')"
[ "$identity" = "${approved_url}|10.0.10" ] || {
    echo "Approved test instance identity mismatch: ${identity}" >&2
    exit 1
}
docker compose -f "$compose_file" exec -T espocrm test -f custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/module.json || {
    echo 'GeneratorPerioadeCursuri is not installed; install a validated compatible ZIP first.' >&2
    exit 1
}
docker compose -f "$compose_file" exec -T espocrm php < "${extension_root}/tests/integration/runtime.php"
