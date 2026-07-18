#!/usr/bin/env bash
set -euo pipefail

extension_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
repo_root="$(cd "${extension_root}/.." && pwd)"

cd "${repo_root}"

php GeneratorPerioadeCursuri/tests/wordpress-updater/contract.php
php GeneratorPerioadeCursuri/tests/wordpress-updater/phase-1.php
php GeneratorPerioadeCursuri/tests/wordpress-updater/phase-2.php
php GeneratorPerioadeCursuri/tests/wordpress-updater/phase-3.php
php GeneratorPerioadeCursuri/tests/wordpress-updater/phase-4.php
php GeneratorPerioadeCursuri/tests/wordpress-updater/phase-5.php
php GeneratorPerioadeCursuri/tests/offline/parser-merge-boundaries.php
php GeneratorPerioadeCursuri/tests/offline/main-title-pipeline.php
php GeneratorPerioadeCursuri/tests/offline/mec-xml-builder.php
php GeneratorPerioadeCursuri/tests/offline/menu-lifecycle.php
node GeneratorPerioadeCursuri/tests/offline/error-message-quality.mjs
node GeneratorPerioadeCursuri/tests/offline/word-matcher-view-state.mjs
node GeneratorPerioadeCursuri/tests/offline/detail-layout-field-visibility.mjs
node GeneratorPerioadeCursuri/tests/offline/record-ui.mjs
node GeneratorPerioadeCursuri/tests/offline/holiday-import.mjs
node GeneratorPerioadeCursuri/tests/offline/generator-edit-validation.mjs
node GeneratorPerioadeCursuri/tests/offline/generator-detail-export.mjs
bash build.sh --extension GeneratorPerioadeCursuri --zip files scripts >/dev/null
version="$(php -r '$manifest = json_decode(file_get_contents("GeneratorPerioadeCursuri/manifest.json"), true, 512, JSON_THROW_ON_ERROR); echo $manifest["version"];')"
php GeneratorPerioadeCursuri/tests/wordpress-updater/phase-6.php \
    "dist/generator-perioade-cursuri-${version}.zip"

printf '%s\n' 'Safe permitted suite passed. WordPress was not contacted.'
