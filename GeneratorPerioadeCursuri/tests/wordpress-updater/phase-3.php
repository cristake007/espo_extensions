<?php

declare(strict_types=1);

$extensionRoot = dirname(__DIR__, 2);
$moduleRoot = $extensionRoot . '/files/custom/Espo/Modules/GeneratorPerioadeCursuri';
$clientRoot = $extensionRoot . '/files/client/custom/modules/generator-perioade-cursuri';
$entityType = 'GeneratorPerioadeCursuriWordPressUpdater';
$checks = 0;
$failures = [];

$assertSame = static function (mixed $expected, mixed $actual, string $message) use (&$checks, &$failures): void {
    $checks++;

    if ($expected !== $actual) {
        $failures[] = $message . '\n  expected: ' . var_export($expected, true) . '\n  actual:   ' . var_export($actual, true);
    }
};

$assertTrue = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;

    if (!$condition) {
        $failures[] = $message;
    }
};

$readJson = static fn (string $path): array => json_decode(
    (string) file_get_contents($path),
    true,
    512,
    JSON_THROW_ON_ERROR
);

$metadataTypes = ['scopes', 'entityDefs', 'entityAcl', 'aclDefs', 'clientDefs', 'recordDefs'];
$metadata = [];

foreach ($metadataTypes as $type) {
    $path = $moduleRoot . '/Resources/metadata/' . $type . '/' . $entityType . '.json';
    $assertTrue(is_file($path), "Required {$type} metadata must exist.");
    $metadata[$type] = $readJson($path);
}

$scope = $metadata['scopes'];
$assertSame('BasePlus', $scope['type'] ?? null, 'The updater scope must use BasePlus.');
$assertSame(true, $scope['entity'] ?? null, 'The updater scope must define an entity.');
$assertSame(true, $scope['acl'] ?? null, 'The updater scope must have independent ACL.');
$assertSame(true, $scope['assignedUsers'] ?? null, 'The updater scope must support assigned users.');
$assertSame(false, $scope['importable'] ?? null, 'The workflow entity must not expose generic import.');

$fields = $metadata['entityDefs']['fields'] ?? [];
$expectedBusinessFields = ['name', 'description', 'wpScheduleFile', 'wpBaseUrl', 'wpUsername'];

foreach ($expectedBusinessFields as $field) {
    $assertTrue(array_key_exists($field, $fields), "Required entity field must exist: {$field}");
}

foreach (array_keys($fields) as $field) {
    $assertTrue(
        preg_match('/password|post.?id|connection.?status|preview|payload|response/i', $field) !== 1,
        "No persisted workflow/secret field is allowed: {$field}"
    );
}

$assertSame('varchar', $fields['name']['type'] ?? null, 'Name must be a varchar field.');
$assertSame(true, $fields['name']['required'] ?? null, 'Name must be required.');
$assertSame('$noBadCharacters', $fields['name']['pattern'] ?? null, 'Name must use the normal safe pattern.');
$assertSame('file', $fields['wpScheduleFile']['type'] ?? null, 'Schedule input must use Espo native file metadata.');
$assertSame(true, $fields['wpScheduleFile']['required'] ?? null, 'Schedule input must be required.');
$assertSame(['.csv', '.xlsx'], $fields['wpScheduleFile']['accept'] ?? null, 'Schedule input must accept only CSV and XLSX.');
$assertSame(
    'generator-perioade-cursuri:views/fields/source-file',
    $fields['wpScheduleFile']['view'] ?? null,
    'Schedule input must reuse the canonical upload field.'
);
$assertSame(true, $fields['wpBaseUrl']['readOnly'] ?? null, 'The saved base URL must be read-only in CRUD.');
$assertSame(2048, $fields['wpBaseUrl']['maxLength'] ?? null, 'The saved base URL must enforce its boundary.');
$assertSame(true, $fields['wpUsername']['readOnly'] ?? null, 'The saved username must be read-only in CRUD.');
$assertSame(150, $fields['wpUsername']['maxLength'] ?? null, 'The saved username must enforce its boundary.');
$assertSame('linkMultiple', $fields['assignedUsers']['type'] ?? null, 'Assigned users must follow the extension convention.');
$assertSame('linkMultiple', $fields['teams']['type'] ?? null, 'Teams must follow the extension convention.');

$assertSame(['read' => true, 'edit' => true, 'delete' => true, 'stream' => true], $metadata['aclDefs'], 'Scope ACL must expose standard role-controlled operations.');
$assertSame(['fields' => []], $metadata['entityAcl'], 'Field ACL metadata must be explicit.');

$clientDefs = $metadata['clientDefs'];
$assertSame(
    'generator-perioade-cursuri:views/generator-perioade-cursuri-wordpress-updater/record/edit',
    $clientDefs['recordViews']['edit'] ?? null,
    'Client metadata must register the updater edit view.'
);
$assertSame(
    'generator-perioade-cursuri:views/generator-perioade-cursuri-wordpress-updater/record/detail',
    $clientDefs['recordViews']['detail'] ?? null,
    'Client metadata must register the updater detail view.'
);
$assertSame('fas fa-sync-alt', $clientDefs['iconClass'] ?? null, 'The updater nav item must use the supported solid sync icon.');

$layouts = [];

foreach (['edit', 'detail', 'list', 'search'] as $layoutName) {
    $path = $moduleRoot . '/Resources/layouts/' . $entityType . '/' . $layoutName . '.json';
    $assertTrue(is_file($path), "Required {$layoutName} layout must exist.");
    $layouts[$layoutName] = $readJson($path);
}

$editRows = $layouts['edit'][0]['rows'] ?? [];
$fileRows = array_values(array_filter(
    $editRows,
    static fn (array $row): bool => ($row[0]['name'] ?? null) === 'wpScheduleFile'
));
$assertSame(1, count($fileRows), 'The edit layout must contain one schedule upload row.');
$assertSame(false, $fileRows[0][1] ?? null, 'The upload field must occupy a full-width edit row.');
$assertTrue(!str_contains(json_encode($layouts['edit'], JSON_THROW_ON_ERROR), 'wpBaseUrl'), 'Verified connection values must not be editable in the standard form.');
$assertTrue(!str_contains(json_encode($layouts['edit'], JSON_THROW_ON_ERROR), 'wpUsername'), 'Verified username must not be editable in the standard form.');

$detailJson = json_encode($layouts['detail'], JSON_THROW_ON_ERROR);
$assertTrue(str_contains($detailJson, 'wpBaseUrl'), 'Detail layout must show the last verified base URL.');
$assertTrue(str_contains($detailJson, 'wpUsername'), 'Detail layout must show the last verified username.');
$assertTrue(str_contains($detailJson, 'wpScheduleFile'), 'Detail layout must show the saved native attachment.');

$controller = (string) file_get_contents($moduleRoot . '/Controllers/' . $entityType . '.php');
$assertTrue(str_contains($controller, 'Templates\\Controllers\\BasePlus'), 'The entity controller must extend the BasePlus controller.');

$editViewPath = $clientRoot . '/src/views/generator-perioade-cursuri-wordpress-updater/record/edit.js';
$detailViewPath = $clientRoot . '/src/views/generator-perioade-cursuri-wordpress-updater/record/detail.js';
$editView = (string) file_get_contents($editViewPath);
$detailView = (string) file_get_contents($detailViewPath);
$assertTrue(str_contains($editView, 'generator-perioade-cursuri/record/edit'), 'Updater edit must extend the canonical wide create view.');
$assertTrue(str_contains($editView, 'this.isWide = true;'), 'Updater edit must remain full width for existing records.');
$assertTrue(str_contains($editView, 'this.sideDisabled = true;'), 'Updater edit must suppress the narrow side column.');
$assertTrue(str_contains($editView, "classList.add('generator-perioade-cursuri-create')"), 'Updater edit must reuse the canonical upload presentation.');
$assertTrue(str_contains($detailView, "'views/record/detail'"), 'Updater detail shell must extend the native detail view.');
$assertTrue(
    str_contains($detailView, "'generator-perioade-cursuri:views/shared/record-ui'"),
    'Updater detail shell must depend on the shared record UI module.'
);
$assertTrue(str_contains($detailView, 'this.isWide = true;'), 'Updater detail must use the full record width.');
$assertTrue(str_contains($detailView, 'this.sideDisabled = true;'), 'Updater detail must suppress the narrow side column.');
$assertTrue(!preg_match('/localStorage|sessionStorage/', $detailView), 'Updater workflow state must not use browser storage.');
$assertTrue(!preg_match('/model\.set\([^;]*wpAppPassword/s', $detailView), 'Updater credentials must never be placed on the Espo model.');

$sourceField = (string) file_get_contents($clientRoot . '/src/views/fields/source-file.js');
$assertTrue(str_contains($sourceField, "['views/fields/file']"), 'Canonical upload must extend Espo native file behavior.');
$assertTrue(str_contains($sourceField, "wpScheduleFile: 'uploadWpScheduleTitle'"), 'Canonical upload must map the updater title.');
$assertTrue(str_contains($sourceField, 'tabindex="0"'), 'Upload drop zone must remain keyboard focusable.');
$assertTrue(str_contains($sourceField, "event.key !== 'Enter' && event.key !== ' '"), 'Upload drop zone must retain keyboard activation.');
$assertTrue(str_contains($sourceField, 'acceptAttribute'), 'Upload input must retain metadata-driven accept handling.');
$assertTrue(!str_contains($sourceField, '!this.model.isNew()'), 'Existing record edits must retain the canonical drop zone.');

$css = (string) file_get_contents($clientRoot . '/css/generator-perioade-cursuri.css');

foreach (['--tuvtk-primary', '--tuvtk-secondary', '--tuvtk-hover', '--tuvtk-surface', '--tuvtk-border', '--tuvtk-text', '--tuvtk-muted'] as $token) {
    $assertTrue(str_contains($css, $token), "Canonical upload CSS must retain theme token {$token}.");
}

$afterInstall = (string) file_get_contents($extensionRoot . '/scripts/AfterInstall.php');
$assertTrue(
    str_contains($afterInstall, "/Resources/metadata/scopes/{$entityType}.json'"),
    'AfterInstall must verify that the updater scope file is packaged.'
);

foreach (['en_US', 'ro_RO'] as $locale) {
    $entityI18nPath = $moduleRoot . '/Resources/i18n/' . $locale . '/' . $entityType . '.json';
    $globalI18nPath = $moduleRoot . '/Resources/i18n/' . $locale . '/Global.json';
    $entityI18n = $readJson($entityI18nPath);
    $globalI18n = $readJson($globalI18nPath);
    $assertTrue(isset($entityI18n['labels']['uploadWpScheduleTitle']), "{$locale} must translate the updater upload title.");
    $assertTrue(isset($entityI18n['fields']['wpScheduleFile']), "{$locale} must translate the schedule field.");
    $assertTrue(isset($globalI18n['scopeNames'][$entityType]), "{$locale} must translate the singular scope name.");
    $assertTrue(isset($globalI18n['scopeNamesPlural'][$entityType]), "{$locale} must translate the plural scope name.");
}

$englishGlobal = $readJson($moduleRoot . '/Resources/i18n/en_US/Global.json');
$assertSame(
    'Generator Perioade Cursuri WordPress Updater',
    $englishGlobal['scopeNames'][$entityType] ?? null,
    'Entity Manager must use the extension naming convention for the updater.'
);
$assertSame(
    'Generator Perioade Cursuri WordPress Updaters',
    $englishGlobal['scopeNamesPlural'][$entityType] ?? null,
    'The updater plural scope name must use the extension naming convention.'
);

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }

    fwrite(STDERR, sprintf("%d of %d Phase 3 checks failed.\n", count($failures), $checks));
    exit(1);
}

fwrite(STDOUT, "Phase 3 WordPress updater entity and native input UI: {$checks} checks passed.\n");
