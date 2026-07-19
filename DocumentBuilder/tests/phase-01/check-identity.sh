#!/bin/sh

set -eu

script_dir=$(dirname "$0")
extension_root=$(CDPATH= cd "$script_dir/../.." && pwd)
repository_root=$(CDPATH= cd "$extension_root/.." && pwd)
manifest_file="$extension_root/manifest.json"
module_file="$extension_root/files/custom/Espo/Modules/DocumentBuilder/Resources/module.json"
english_labels="$extension_root/files/custom/Espo/Modules/DocumentBuilder/Resources/i18n/en_US/Global.json"
romanian_labels="$extension_root/files/custom/Espo/Modules/DocumentBuilder/Resources/i18n/ro_RO/Global.json"

fail() {
    printf 'Phase 01 identity check failed: %s\n' "$1" >&2
    exit 1
}

[ "${extension_root##*/}" = 'DocumentBuilder' ] || fail 'extension root is not DocumentBuilder'
[ ! -e "$repository_root/Documentbuilder" ] || fail 'obsolete Documentbuilder root exists'
[ -d "$extension_root/files/client/custom/modules/document-builder" ] || fail 'frontend module path is missing'
[ -f "$module_file" ] || fail 'Resources/module.json is missing'
[ ! -e "$extension_root/files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/app/module.json" ] ||
    fail 'module descriptor is in metadata/app instead of Resources/module.json'

if find "$extension_root/files" -print | grep -Fq 'Documentbuilder'; then
    fail 'obsolete Documentbuilder casing exists in an application path'
fi

if grep -R -Fq 'Documentbuilder' "$extension_root/README.md" "$extension_root/manifest.json" "$extension_root/files"; then
    fail 'obsolete Documentbuilder casing exists in scaffold content'
fi

command -v php >/dev/null 2>&1 || fail 'php is required for JSON contract checks'

php -r '
    $manifest = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);

    $expected = [
        "name" => "Document Builder",
        "version" => "1.0.0",
        "acceptableVersions" => [">=10.0.0"],
        "php" => [">=8.3.0 <8.6.0"],
    ];

    foreach ($expected as $key => $value) {
        if (($manifest[$key] ?? null) !== $value) {
            fwrite(STDERR, "Unexpected manifest value for {$key}.\n");
            exit(1);
        }
    }
' "$manifest_file"

php -r '
    $module = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);

    if ($module !== ["order" => 100, "jsTranspiled" => false]) {
        fwrite(STDERR, "Unexpected module descriptor.\n");
        exit(1);
    }
' "$module_file"

for labels_file in "$english_labels" "$romanian_labels"; do
    php -r '
        $labels = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);

        if (($labels["labels"]["DocumentBuilder"] ?? null) !== "Document Builder") {
            fwrite(STDERR, "Unexpected DocumentBuilder label.\n");
            exit(1);
        }
    ' "$labels_file"
done

grep -Fq './build.sh --extension ./DocumentBuilder --zip 1.0.0 files scripts' "$extension_root/README.md" ||
    fail 'README build command is not normalized'

printf 'Phase 01 identity checks passed.\n'
