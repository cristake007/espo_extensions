#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DIST_DIR="${REPO_ROOT}/dist"

usage() {
    cat <<'USAGE'
Usage:
  ./build.sh
  ./build.sh --new
  ./build.sh --extension /path/to/Extension --validate
  ./build.sh --extension /path/to/Extension --zip [VERSION] [paths...]

Modes:
  No args / --new
      Interactively asks for an extension name and creates a new extension scaffold
      in the repository root.

  --extension PATH --zip [VERSION] [paths...]
      Builds an installable EspoCRM ZIP from a specific extension root folder.
      The manifest version is authoritative. VERSION is optional, but when
      provided it must match the version declared in manifest.json.
      The ZIP root will contain manifest.json, README.md when present, and the
      requested paths.

  --extension PATH --validate
      Validates the manifest and module descriptors, all JSON under files and
      scripts, and PHP syntax where PHP files are present.

Examples:
  ./build.sh
  ./build.sh --extension /opt/DemoExtension --validate
  ./build.sh --extension /opt/DemoExtension --zip files scripts
  ./build.sh --extension ./GeneratorPerioadeCursuri --zip files scripts
USAGE
}

fail() {
    echo "Error: $*" >&2
    exit 1
}

require_zip() {
    if ! command -v zip >/dev/null 2>&1; then
        fail "zip is not installed. Install it first, for example: sudo apt install zip"
    fi
}

require_php() {
    if ! command -v php >/dev/null 2>&1; then
        fail "php is required for JSON and PHP syntax validation"
    fi
}

trim() {
    local value="$*"
    value="${value#"${value%%[![:space:]]*}"}"
    value="${value%"${value##*[![:space:]]}"}"
    printf '%s' "$value"
}

to_pascal_case() {
    local input="$1"
    local normalized word result=""

    normalized="$(printf '%s' "$input" | sed -E 's/[^[:alnum:]]+/ /g')"

    for word in $normalized; do
        result+="$(printf '%s' "${word:0:1}" | tr '[:lower:]' '[:upper:]')"
        result+="$(printf '%s' "${word:1}" | tr '[:upper:]' '[:lower:]')"
    done

    printf '%s' "$result"
}

to_kebab_case() {
    local input="$1"

    printf '%s' "$input" \
        | sed -E 's/([a-z0-9])([A-Z])/\1-\2/g' \
        | sed -E 's/[^[:alnum:]]+/-/g' \
        | sed -E 's/^-+|-+$//g' \
        | tr '[:upper:]' '[:lower:]'
}

json_escape() {
    local input="$1"
    input="${input//\\/\\\\}"
    input="${input//\"/\\\"}"
    printf '%s' "$input"
}

validate_extension() {
    local extension_abs="$1"
    local manifest_path="${extension_abs}/manifest.json"
    local modules_root="${extension_abs}/files/custom/Espo/Modules"
    local json_file php_file validation_output module_dir descriptor legacy_descriptor
    local module_found=false
    local validation_roots=("${manifest_path}")

    [ -f "$manifest_path" ] || fail "manifest.json was not found in ${extension_abs}"
    [ -d "$modules_root" ] || fail "EspoCRM module directory was not found in ${extension_abs}/files"

    require_php

    [ ! -d "${extension_abs}/files" ] || validation_roots+=("${extension_abs}/files")
    [ ! -d "${extension_abs}/scripts" ] || validation_roots+=("${extension_abs}/scripts")

    while IFS= read -r -d '' json_file; do
        # The single-quoted program is intentionally passed verbatim to PHP.
        # shellcheck disable=SC2016
        if ! validation_output="$(php -r '
            $path = $argv[1];

            try {
                json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                fwrite(STDERR, $path . ": " . $e->getMessage() . PHP_EOL);
                exit(1);
            }
        ' "$json_file" 2>&1)"; then
            [ -z "$validation_output" ] || printf '%s\n' "$validation_output" >&2
            fail "invalid JSON: ${json_file}"
        fi
    done < <(find "${validation_roots[@]}" -type f -name '*.json' -print0)

    legacy_descriptor="$(find "$modules_root" -type f -path '*/Resources/metadata/app/module.json' -print -quit)"

    if [ -n "$legacy_descriptor" ]; then
        fail "module descriptor is in the wrong location: ${legacy_descriptor}; use Resources/module.json"
    fi

    while IFS= read -r -d '' module_dir; do
        module_found=true
        descriptor="${module_dir}/Resources/module.json"

        [ -f "$descriptor" ] || fail "module descriptor was not found: ${descriptor}"

        # The single-quoted program is intentionally passed verbatim to PHP.
        # shellcheck disable=SC2016
        if ! validation_output="$(php -r '
            $path = $argv[1];
            $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

            if (!array_key_exists("order", $data) || !is_int($data["order"])) {
                fwrite(STDERR, $path . ": order must be an integer" . PHP_EOL);
                exit(1);
            }
        ' "$descriptor" 2>&1)"; then
            [ -z "$validation_output" ] || printf '%s\n' "$validation_output" >&2
            fail "invalid module descriptor: ${descriptor}"
        fi
    done < <(find "$modules_root" -mindepth 1 -maxdepth 1 -type d -print0)

    [ "$module_found" = true ] || fail "no EspoCRM module was found in ${modules_root}"

    while IFS= read -r -d '' php_file; do
        if ! validation_output="$(php -l "$php_file" 2>&1)"; then
            [ -z "$validation_output" ] || printf '%s\n' "$validation_output" >&2
            fail "invalid PHP syntax: ${php_file}"
        fi
    done < <(find "${validation_roots[@]}" -type f -name '*.php' -print0)

    echo "Validated ${extension_abs}"
}

validate_extension_command() {
    local extension_abs

    [ "$#" -eq 3 ] || fail "validation usage: --extension PATH --validate"
    [ "$1" = "--extension" ] || fail "validation usage: --extension PATH --validate"
    [ "$3" = "--validate" ] || fail "validation usage: --extension PATH --validate"

    extension_abs="$(cd "$2" 2>/dev/null && pwd)" || fail "extension path not found: $2"
    validate_extension "$extension_abs"
}

create_extension() {
    local display_name module_name package_name extension_dir today escaped_name

    read -r -p "Extension display name: " display_name
    display_name="$(trim "$display_name")"

    if [ -z "$display_name" ]; then
        fail "extension name is required"
    fi

    module_name="$(to_pascal_case "$display_name")"
    package_name="$(to_kebab_case "$display_name")"

    if [ -z "$module_name" ] || [ -z "$package_name" ]; then
        fail "extension name must contain at least one letter or number"
    fi

    extension_dir="${REPO_ROOT}/${module_name}"

    if [ -e "$extension_dir" ]; then
        fail "${extension_dir} already exists"
    fi

    today="$(date +%F)"
    escaped_name="$(json_escape "$display_name")"

    mkdir -p \
        "${extension_dir}/files/custom/Espo/Modules/${module_name}/Controllers" \
        "${extension_dir}/files/custom/Espo/Modules/${module_name}/Entities" \
        "${extension_dir}/files/custom/Espo/Modules/${module_name}/Hooks" \
        "${extension_dir}/files/custom/Espo/Modules/${module_name}/Tools" \
        "${extension_dir}/files/custom/Espo/Modules/${module_name}/Classes/FieldValidators" \
        "${extension_dir}/files/custom/Espo/Modules/${module_name}/Resources/metadata/entityDefs" \
        "${extension_dir}/files/custom/Espo/Modules/${module_name}/Resources/metadata/scopes" \
        "${extension_dir}/files/custom/Espo/Modules/${module_name}/Resources/metadata/clientDefs" \
        "${extension_dir}/files/custom/Espo/Modules/${module_name}/Resources/metadata/recordDefs" \
        "${extension_dir}/files/custom/Espo/Modules/${module_name}/Resources/metadata/aclDefs" \
        "${extension_dir}/files/custom/Espo/Modules/${module_name}/Resources/layouts" \
        "${extension_dir}/files/custom/Espo/Modules/${module_name}/Resources/i18n/en_US" \
        "${extension_dir}/files/custom/Espo/Modules/${module_name}/Resources/i18n/ro_RO" \
        "${extension_dir}/files/client/custom/modules/${package_name}/src" \
        "${extension_dir}/scripts"

    cat > "${extension_dir}/manifest.json" <<JSON
{
    "name": "${escaped_name}",
    "version": "1.0.0",
    "acceptableVersions": [
        ">=10.0.0"
    ],
    "php": [
        ">=8.4"
    ],
    "releaseDate": "${today}",
    "author": "Cristian Popa",
    "description": "EspoCRM extension package for ${escaped_name}. Package ID: ${package_name}."
}
JSON

    cat > "${extension_dir}/README.md" <<EOF_README
# ${display_name}

EspoCRM extension package for \`${display_name}\`.

Package ID: \`${package_name}\`

Module code lives under:

\`\`\`text
files/custom/Espo/Modules/${module_name}
\`\`\`

Custom entity metadata belongs under:

\`\`\`text
files/custom/Espo/Modules/${module_name}/Resources/metadata/entityDefs
files/custom/Espo/Modules/${module_name}/Resources/metadata/scopes
files/custom/Espo/Modules/${module_name}/Resources/metadata/clientDefs
files/custom/Espo/Modules/${module_name}/Resources/metadata/recordDefs
files/custom/Espo/Modules/${module_name}/Resources/metadata/aclDefs
\`\`\`

Layouts and translations belong under:

\`\`\`text
files/custom/Espo/Modules/${module_name}/Resources/layouts/<EntityType>
files/custom/Espo/Modules/${module_name}/Resources/i18n/en_US
files/custom/Espo/Modules/${module_name}/Resources/i18n/ro_RO
\`\`\`

Existing EspoCRM entities are extended through this module's metadata. Example:

\`\`\`text
files/custom/Espo/Modules/${module_name}/Resources/metadata/entityDefs/Contact.json
files/custom/Espo/Modules/${module_name}/Resources/i18n/en_US/Contact.json
files/custom/Espo/Modules/${module_name}/Resources/i18n/ro_RO/Contact.json
\`\`\`

Validate the extension locally:

\`\`\`bash
./build.sh --extension ./${module_name} --validate
\`\`\`

Build an installable ZIP from the repository root:

\`\`\`bash
./build.sh --extension ./${module_name} --zip 1.0.0 files scripts
\`\`\`
EOF_README

    cat > "${extension_dir}/files/custom/Espo/Modules/${module_name}/Resources/module.json" <<'JSON'
{
    "order": 100
}
JSON

    cat > "${extension_dir}/files/custom/Espo/Modules/${module_name}/Resources/i18n/en_US/Global.json" <<JSON
{
    "labels": {
        "${module_name}": "${escaped_name}"
    }
}
JSON

    cat > "${extension_dir}/files/custom/Espo/Modules/${module_name}/Resources/i18n/ro_RO/Global.json" <<JSON
{
    "labels": {
        "${module_name}": "${escaped_name}"
    }
}
JSON

    touch "${extension_dir}/files/custom/Espo/Modules/${module_name}/Controllers/.gitkeep"
    touch "${extension_dir}/files/custom/Espo/Modules/${module_name}/Entities/.gitkeep"
    touch "${extension_dir}/files/custom/Espo/Modules/${module_name}/Hooks/.gitkeep"
    touch "${extension_dir}/files/custom/Espo/Modules/${module_name}/Tools/.gitkeep"
    touch "${extension_dir}/files/custom/Espo/Modules/${module_name}/Classes/FieldValidators/.gitkeep"
    touch "${extension_dir}/files/custom/Espo/Modules/${module_name}/Resources/metadata/entityDefs/.gitkeep"
    touch "${extension_dir}/files/custom/Espo/Modules/${module_name}/Resources/metadata/scopes/.gitkeep"
    touch "${extension_dir}/files/custom/Espo/Modules/${module_name}/Resources/metadata/clientDefs/.gitkeep"
    touch "${extension_dir}/files/custom/Espo/Modules/${module_name}/Resources/metadata/recordDefs/.gitkeep"
    touch "${extension_dir}/files/custom/Espo/Modules/${module_name}/Resources/metadata/aclDefs/.gitkeep"
    touch "${extension_dir}/files/custom/Espo/Modules/${module_name}/Resources/layouts/.gitkeep"
    touch "${extension_dir}/files/client/custom/modules/${package_name}/src/.gitkeep"
    touch "${extension_dir}/scripts/.gitkeep"

    echo "Created ${extension_dir}"
    echo "Validate with: ./build.sh --extension ${extension_dir} --validate"
    echo "Build with: ./build.sh --extension ${extension_dir} --zip 1.0.0 files scripts"
}

zip_extension() {
    local extension_path="" version="" requested_version="" zip_requested=false
    local paths=() root_paths=() extension_abs manifest_path package_name output_file

    while [ "$#" -gt 0 ]; do
        case "$1" in
            --extension)
                shift
                [ "$#" -gt 0 ] || fail "--extension requires a path"
                extension_path="$1"
                ;;
            --zip)
                zip_requested=true
                ;;
            -h|--help)
                usage
                exit 0
                ;;
            --*)
                fail "unknown option: $1"
                ;;
            *)
                paths+=("$1")
                ;;
        esac
        shift
    done

    [ -n "$extension_path" ] || fail "--extension is required"
    [ "$zip_requested" = true ] || fail "--zip is required"

    require_zip

    extension_abs="$(cd "$extension_path" 2>/dev/null && pwd)" || fail "extension path not found: ${extension_path}"
    manifest_path="${extension_abs}/manifest.json"

    validate_extension "$extension_abs"

    version="$(sed -nE 's/^[[:space:]]*"version"[[:space:]]*:[[:space:]]*"([^"]+)".*$/\1/p' "$manifest_path" | head -n 1)"

    [ -n "$version" ] || fail "version was not found in ${manifest_path}"

    if [ "${#paths[@]}" -gt 0 ] && [[ "${paths[0]}" =~ ^[0-9]+\.[0-9]+\.[0-9]+([+-][0-9A-Za-z.-]+)?$ ]]; then
        requested_version="${paths[0]}"
        paths=("${paths[@]:1}")

        if [ "$requested_version" != "$version" ]; then
            fail "ZIP version ${requested_version} does not match manifest version ${version}"
        fi
    fi

    if [ "${#paths[@]}" -eq 0 ]; then
        paths=(files)
        [ ! -d "${extension_abs}/scripts" ] || paths+=(scripts)
    fi

    for path in "${paths[@]}"; do
        [ -e "${extension_abs}/${path}" ] || fail "${path} was not found in ${extension_abs}"
    done

    root_paths=(manifest.json)
    [ ! -f "${extension_abs}/README.md" ] || root_paths+=(README.md)

    package_name="$(to_kebab_case "$(basename "$extension_abs")")"
    output_file="${DIST_DIR}/${package_name}-${version}.zip"

    mkdir -p "$DIST_DIR"
    rm -f "$output_file"

    (
        cd "$extension_abs"
        zip -r "$output_file" "${root_paths[@]}" "${paths[@]}" \
            -x "*.git*" \
            -x "*/.DS_Store" \
            -x "__MACOSX/*" \
            -x "dist/*" \
            -x "*.zip"
    )

    echo "Created ${output_file}"
    echo "Upload this ZIP in EspoCRM: Administration > Extensions."
}

main() {
    if [ "$#" -eq 0 ]; then
        create_extension
        exit 0
    fi

    case "$1" in
        --new)
            shift
            [ "$#" -eq 0 ] || fail "--new does not accept extra arguments"
            create_extension
            ;;
        --extension)
            if [ "${3:-}" = "--validate" ]; then
                validate_extension_command "$@"
            else
                zip_extension "$@"
            fi
            ;;
        --zip)
            zip_extension "$@"
            ;;
        -h|--help)
            usage
            ;;
        *)
            fail "unknown command or option: $1"
            ;;
    esac
}

main "$@"
