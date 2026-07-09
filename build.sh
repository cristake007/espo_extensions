#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DIST_DIR="${REPO_ROOT}/dist"

usage() {
    cat <<'USAGE'
Usage:
  ./build.sh
  ./build.sh --new
  ./build.sh --extension /path/to/Extension --zip 1.0.1 [paths...]

Modes:
  No args / --new
      Interactively asks for an extension name and creates a new extension scaffold
      in the repository root.

  --extension PATH --zip VERSION [paths...]
      Builds an installable EspoCRM ZIP from a specific extension root folder.
      The ZIP root will contain manifest.json plus the requested paths.

Examples:
  ./build.sh
  ./build.sh --extension /opt/DemoExtension --zip 1.0.1 files scripts
  ./build.sh --extension ./Planificari --zip 1.0.44 files scripts
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
        | sed -E 's/([a-z0-9])([A-Z])/'"\\1-\\2"'/g' \
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
        "${extension_dir}/files/custom/Espo/Modules/${module_name}/Resources/metadata/app" \
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

Build an installable ZIP from the repository root:

\`\`\`bash
./build.sh --extension ./${module_name} --zip 1.0.0 files scripts
\`\`\`
EOF_README

    cat > "${extension_dir}/files/custom/Espo/Modules/${module_name}/Resources/metadata/app/module.json" <<'JSON'
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

    touch "${extension_dir}/files/client/custom/modules/${package_name}/src/.gitkeep"
    touch "${extension_dir}/scripts/.gitkeep"

    echo "Created ${extension_dir}"
    echo "Build with: ./build.sh --extension ${extension_dir} --zip 1.0.0 files scripts"
}

zip_extension() {
    local extension_path="" version="" paths=() extension_abs manifest_path package_name output_file

    while [ "$#" -gt 0 ]; do
        case "$1" in
            --extension)
                shift
                [ "$#" -gt 0 ] || fail "--extension requires a path"
                extension_path="$1"
                ;;
            --zip)
                shift
                [ "$#" -gt 0 ] || fail "--zip requires a version"
                version="$1"
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
    [ -n "$version" ] || fail "--zip is required"

    require_zip

    extension_abs="$(cd "$extension_path" 2>/dev/null && pwd)" || fail "extension path not found: ${extension_path}"
    manifest_path="${extension_abs}/manifest.json"

    [ -f "$manifest_path" ] || fail "manifest.json was not found in ${extension_abs}"

    if [ "${#paths[@]}" -eq 0 ]; then
        paths=(files)
        [ ! -d "${extension_abs}/scripts" ] || paths+=(scripts)
    fi

    for path in "${paths[@]}"; do
        [ -e "${extension_abs}/${path}" ] || fail "${path} was not found in ${extension_abs}"
    done

    package_name="$(basename "$extension_abs" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^[:alnum:]]+/-/g; s/^-+|-+$//g')"
    output_file="${DIST_DIR}/${package_name}-${version}.zip"

    mkdir -p "$DIST_DIR"
    rm -f "$output_file"

    (
        cd "$extension_abs"
        zip -r "$output_file" manifest.json README.md "${paths[@]}" \
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
        --extension|--zip)
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
