#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
runner_image='espo-extensions/php-runner:8.4.27-alpine3.23-v1'
runner_context="${repo_root}/docker/php-runner"

usage() {
    cat <<'USAGE'
Usage:
  ./test-php.sh validate EXTENSION|--all
  ./test-php.sh build EXTENSION
  ./test-php.sh php PHP_ARGUMENTS...

Examples:
  ./test-php.sh validate EmployeeManagement
  ./test-php.sh validate ZileSarbatoare
  ./test-php.sh validate --all
  ./test-php.sh build EmployeeManagement
  ./test-php.sh php -v

The repository is mounted read-only for validation and PHP commands. Builds
can write only to dist/. All runner containers are ephemeral and have no
network access. The local runner image is built once and reused by Docker.
USAGE
}

fail() {
    printf 'Error: %s\n' "$*" >&2
    exit 1
}

ensure_runner_image() {
    command -v docker >/dev/null 2>&1 || fail 'Docker is required.'

    if docker image inspect "$runner_image" >/dev/null 2>&1; then
        return
    fi

    printf 'Building PHP runner image %s\n' "$runner_image"
    docker build --tag "$runner_image" "$runner_context"
}

require_extension() {
    local extension="$1"

    [[ "$extension" =~ ^[A-Za-z][A-Za-z0-9_-]*$ ]] || fail "invalid extension name: $extension"
    [ -d "${repo_root}/${extension}" ] || fail "extension directory not found: $extension"
    [ -f "${repo_root}/${extension}/manifest.json" ] || fail "extension manifest not found: $extension"
}

run_read_only() {
    docker run --rm --network none \
        --user "$(id -u):$(id -g)" \
        --mount "type=bind,src=${repo_root},dst=/repo,readonly" \
        --workdir /repo \
        "$runner_image" "$@"
}

validate_extension() {
    local extension="$1"

    require_extension "$extension"
    run_read_only ./build.sh --extension "./${extension}" --validate
}

collect_extensions() {
    local manifest extension

    extensions=()
    for manifest in "${repo_root}"/*/manifest.json; do
        [ -f "$manifest" ] || continue
        extension="$(basename "$(dirname "$manifest")")"

        # This directory is a template whose own manifest says it must be
        # renamed before it becomes a real extension.
        [ "$extension" = 'espocrm-extension-scaffold' ] && continue
        extensions+=("$extension")
    done
}

validate_all() {
    local extension failed=0

    collect_extensions
    [ "${#extensions[@]}" -gt 0 ] || fail 'No extension manifests found.'

    printf 'Validating %s extensions with %s\n' "${#extensions[@]}" "$runner_image"
    for extension in "${extensions[@]}"; do
        printf '\n[%s] VALIDATE\n' "$extension"
        if validate_extension "$extension"; then
            printf '[%s] PASS\n' "$extension"
        else
            printf '[%s] FAIL\n' "$extension" >&2
            failed=$((failed + 1))
        fi
    done

    printf '\nValidation summary: %s passed, %s failed.\n' \
        "$((${#extensions[@]} - failed))" "$failed"
    [ "$failed" -eq 0 ]
}

build_extension() {
    local extension="$1"
    local build_args=(./build.sh --extension "./${extension}" --zip)

    require_extension "$extension"

    if [ -f "${repo_root}/${extension}/extension.json" ] &&
        [ -d "${repo_root}/${extension}/src/files" ]; then
        [ -x "${repo_root}/${extension}/build.sh" ] ||
            fail "modern extension build script is missing or not executable: $extension"
        mkdir -p "${repo_root}/dist"
        "${repo_root}/${extension}/build.sh" --zip "${repo_root}/dist"
        return
    fi

    [ -d "${repo_root}/${extension}/files" ] || fail "extension files directory not found: $extension"
    mkdir -p "${repo_root}/dist"

    if [ -d "${repo_root}/${extension}/scripts" ]; then
        build_args+=(files scripts)
    fi

    docker run --rm --network none \
        --user "$(id -u):$(id -g)" \
        --mount "type=bind,src=${repo_root},dst=/repo,readonly" \
        --mount "type=bind,src=${repo_root}/dist,dst=/repo/dist" \
        --workdir /repo \
        "$runner_image" "${build_args[@]}"
}

[ "$#" -gt 0 ] || { usage; exit 2; }

mode="$1"
shift

case "$mode" in
    validate)
        [ "$#" -eq 1 ] || { usage; exit 2; }
        ensure_runner_image
        if [ "$1" = '--all' ]; then
            validate_all
        else
            validate_extension "$1"
        fi
        ;;
    build)
        [ "$#" -eq 1 ] || { usage; exit 2; }
        ensure_runner_image
        build_extension "$1"
        ;;
    php)
        [ "$#" -gt 0 ] || { usage; exit 2; }
        ensure_runner_image
        run_read_only php "$@"
        ;;
    -h|--help|help)
        usage
        ;;
    *)
        usage
        exit 2
        ;;
esac
