#!/usr/bin/env bash
set -euo pipefail

extension_root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repository_root="$(cd "${extension_root}/.." && pwd)"
default_dist="${repository_root}/dist"

usage() {
    cat <<'USAGE'
Usage:
  ./build.sh --bootstrap
  ./build.sh --validate
  ./build.sh --zip [OUTPUT_DIRECTORY]

--bootstrap  Installs the locked npm and Composer development dependencies.
--validate   Runs the complete unit-test and static-analysis suites.
--zip        Validates and creates the installable EspoCRM ZIP. The final ZIP
             contains manifest.json, README.md, files/ and scripts/ at root.
USAGE
}

fail() {
    printf 'Error: %s\n' "$*" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "$1 is required. Run ./build.sh --bootstrap only after installing the documented prerequisites."
}

check_versions() {
    local package_version manifest_version

    package_version="$(php -r '$j=json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); echo $j["version"] ?? "";' "${extension_root}/package.json")"
    manifest_version="$(php -r '$j=json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); echo $j["version"] ?? "";' "${extension_root}/manifest.json")"

    [ -n "$package_version" ] || fail 'package.json does not contain a version.'
    [ "$package_version" = "$manifest_version" ] ||
        fail "package.json version ${package_version} differs from manifest.json version ${manifest_version}."
}

bootstrap() {
    require_command node
    require_command npm
    require_command php
    require_command composer

    cd "$extension_root"
    npm ci --no-audit --no-fund
    composer install --no-interaction --prefer-dist
    composer install \
        --working-dir="${extension_root}/src/files/custom/Espo/Modules/ManagementAngajati" \
        --no-dev \
        --no-interaction \
        --prefer-dist
    npm run prepare-test
    printf 'Development dependencies are ready.\n'
}

require_dependencies() {
    [ -d "${extension_root}/node_modules" ] || fail 'node_modules is missing. Run ./build.sh --bootstrap.'
    [ -f "${extension_root}/vendor/autoload.php" ] || fail 'Composer development dependencies are missing. Run ./build.sh --bootstrap.'
    [ -f "${extension_root}/src/files/custom/Espo/Modules/ManagementAngajati/vendor/autoload.php" ] ||
        fail 'The module Composer dependencies are missing. Run ./build.sh --bootstrap.'
    [ -f "${extension_root}/site/vendor/autoload.php" ] || fail 'The EspoCRM 10.0.10 test runtime is missing. Run ./build.sh --bootstrap.'
}

validate() {
    require_command node
    require_command npm
    require_command php
    require_command composer
    require_dependencies
    check_versions

    cd "$extension_root"
    npm run unit-tests
    npm run sa
}

build_zip() {
    local output_dir="${1:-$default_dist}"
    local version source_zip output_zip

    validate

    cd "$extension_root"
    npm run extension

    version="$(php -r '$j=json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); echo $j["version"];' "${extension_root}/manifest.json")"
    source_zip="${extension_root}/build/management-angajati-${version}.zip"
    [ -f "$source_zip" ] || fail "Official EspoCRM build did not create ${source_zip}."

    mkdir -p "$output_dir"
    output_dir="$(cd "$output_dir" && pwd)"
    output_zip="${output_dir}/management-angajati-${version}.zip"

    node "${extension_root}/tools/finalize-package.mjs" \
        "$source_zip" \
        "$output_zip" \
        "${extension_root}/manifest.json" \
        "${extension_root}/README.md"
}

[ "$#" -gt 0 ] || { usage; exit 2; }

case "$1" in
    --bootstrap)
        [ "$#" -eq 1 ] || { usage; exit 2; }
        bootstrap
        ;;
    --validate)
        [ "$#" -eq 1 ] || { usage; exit 2; }
        validate
        ;;
    --zip)
        [ "$#" -le 2 ] || { usage; exit 2; }
        build_zip "${2:-$default_dist}"
        ;;
    -h|--help|help)
        usage
        ;;
    *)
        usage
        exit 2
        ;;
esac
