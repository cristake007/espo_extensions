#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
node_image='node:24.22.1-bookworm-slim@sha256:55fb5f5a527ea2c5c4a95f963e48ef90aa38c7b346054fdc8ee9957a2b55d205'
playwright_image='mcr.microsoft.com/playwright:v1.61.1-noble@sha256:5b8f294aff9041b7191c34a4bab3ac270157a28774d4b0660e9743297b697e48'

usage() {
    cat <<'USAGE'
Usage:
  ./test-node.sh test EXTENSION|--all
  ./test-node.sh node NODE_ARGUMENTS...
  ./test-node.sh playwright EXTENSION [PLAYWRIGHT_ARGUMENTS...]

Examples:
  ./test-node.sh test DocumentBuilder
  ./test-node.sh test --all
  ./test-node.sh node --test AttendanceManagement/tests/contract.test.mjs
  ./test-node.sh playwright AttendanceManagement
  ./test-node.sh playwright GeneratorPerioadeCursuri --list

The repository is mounted read-only. Playwright dependencies are installed from
the existing package-lock.json into a temporary Docker volume for each run.
USAGE
}

fail() {
    printf 'Error: %s\n' "$*" >&2
    exit 1
}

require_extension() {
    local extension="$1"
    [[ "$extension" =~ ^[A-Za-z][A-Za-z0-9_-]*$ ]] || fail "invalid extension name: $extension"
    [ -d "${repo_root}/${extension}/tests" ] || fail "extension tests not found: $extension"
}

collect_tests() {
    local extension="$1" tests_root path
    tests_root="${repo_root}/${extension}/tests"

    while IFS= read -r -d '' path; do
        test_files+=("/repo/${path#"${repo_root}/"}")
    done < <(find "$tests_root" \
        \( -name browser -o -name node_modules \) -prune -o \
        -type f \( -name '*.test.js' -o -name '*.mjs' \) -print0 | sort -z)
}

command -v docker >/dev/null 2>&1 || fail 'Docker is required.'
[ "$#" -gt 0 ] || { usage; exit 2; }
mode="$1"
shift

case "$mode" in
    test)
        [ "$#" -eq 1 ] || { usage; exit 2; }
        test_files=()

        if [ "$1" = '--all' ]; then
            for extension_dir in "${repo_root}"/*; do
                [ -d "${extension_dir}/tests" ] || continue
                collect_tests "${extension_dir##*/}"
            done
        else
            require_extension "$1"
            collect_tests "$1"
        fi

        [ "${#test_files[@]}" -gt 0 ] || fail 'No Node tests found.'
        printf 'Running %s Node test files with %s\n' "${#test_files[@]}" "$node_image"
        docker run --rm --network none \
            --mount "type=bind,src=${repo_root},dst=/repo,readonly" \
            --workdir /repo "$node_image" \
            node --test --test-concurrency=1 "${test_files[@]}"
        ;;
    node)
        [ "$#" -gt 0 ] || { usage; exit 2; }
        docker run --rm --network none \
            --mount "type=bind,src=${repo_root},dst=/repo,readonly" \
            --workdir /repo "$node_image" node "$@"
        ;;
    playwright)
        [ "$#" -gt 0 ] || { usage; exit 2; }
        extension="$1"
        shift
        require_extension "$extension"
        browser_dir="${repo_root}/${extension}/tests/browser"
        [ -f "${browser_dir}/playwright.config.mjs" ] || fail "Playwright config not found for $extension"

        if [ -f "${browser_dir}/package-lock.json" ]; then
            dependency_dir="${browser_dir}"
        else
            dependency_dir="${repo_root}/AttendanceManagement/tests/browser"
        fi

        [ -f "${dependency_dir}/package-lock.json" ] || fail 'Shared Playwright lockfile is missing.'
        # Docker needs the nested volume mountpoint to exist beneath the
        # read-only repository bind. The empty directory is ignored by Git.
        mkdir -p "${dependency_dir}/node_modules"
        dependency_rel="${dependency_dir#"${repo_root}/"}"
        browser_rel="${browser_dir#"${repo_root}/"}"

        docker run --rm --init --ipc=host \
            --mount "type=bind,src=${repo_root},dst=/repo,readonly" \
            --mount "type=volume,dst=/repo/${dependency_rel}/node_modules" \
            --env ESPO_BASE_URL --env ESPO_ADMIN_USERNAME --env ESPO_ADMIN_PASSWORD \
            --workdir "/repo/${browser_rel}" "$playwright_image" \
            bash -c 'set -euo pipefail; npm ci --prefix "$1" --ignore-scripts --no-audit --no-fund; exec "$1/node_modules/.bin/playwright" test --config="$2" "${@:3}"' \
            bash "/repo/${dependency_rel}" "/repo/${browser_rel}/playwright.config.mjs" "$@"
        ;;
    -h|--help|help)
        usage
        ;;
    *)
        usage
        exit 2
        ;;
esac
