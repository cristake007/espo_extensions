<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$detailPath = $root . '/files/client/custom/modules/generator-perioade-cursuri/src/views/' .
    'generator-perioade-cursuri-wordpress-updater/record/detail.js';
$recordUiPath = $root . '/files/client/custom/modules/generator-perioade-cursuri/src/views/shared/record-ui.js';
$cssPath = $root . '/files/client/custom/modules/generator-perioade-cursuri/css/generator-perioade-cursuri.css';
$localeRoot = $root . '/files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/i18n';
$detail = file_get_contents($detailPath);
$recordUi = file_get_contents($recordUiPath);
$css = file_get_contents($cssPath);
$checks = 0;

if ($detail === false || $recordUi === false || $css === false) {
    throw new RuntimeException('Phase 5 client assets could not be read.');
}

$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;

    if (!$condition) {
        throw new RuntimeException('Phase 5 contract failed: ' . $message);
    }
};

$assertContains = static function (string $needle, string $haystack, string $message) use ($assert): void {
    $assert(str_contains($haystack, $needle), $message);
};

$assertNotContains = static function (string $needle, string $haystack, string $message) use ($assert): void {
    $assert(!str_contains($haystack, $needle), $message);
};

foreach ([
    "name: 'buildWordPressPreview'" => 'Build Preview detail action is registered',
    'actionBuildWordPressPreview()' => 'Build Preview interaction exists',
    'connectWordPress()' => 'Connect interaction exists',
    'disconnectWordPress()' => 'Disconnect interaction exists',
    'runWordPressRowAction(sourceRow, action)' => 'per-row interaction exists',
    "postWordPressUpdaterRequest('preview', {})" => 'preview calls the local preview endpoint',
    "postWordPressUpdaterRequest('connect'" => 'connect calls its endpoint',
    'await this.refreshVerifiedConnectionDisplay()' => 'successful connection refreshes verified fields',
    'await this.reRender()' => 'verified URL and username are visibly re-rendered',
    "action === 'fetchDates'" => 'Fetch Dates is an allowed row operation',
    "action === 'updateRow'" => 'Update Row is an allowed row operation',
] as $needle => $message) {
    $assertContains($needle, $detail, $message);
}

foreach ([
    'previewSourceFileId: this.wpUpdaterPreview.sourceFileId' => 'row operations echo the preview source ID',
    'sourceRow: sourceRow' => 'row operations send the stable source row',
    'wpAppPassword: this.wpUpdaterPassword' => 'credentials are read from transient view state',
    "this.wpUpdaterRowBusy[sourceRow] = action" => 'a row is locked before its request',
    'delete this.wpUpdaterRowBusy[sourceRow]' => 'the row lock is released after its request',
    'this.wpUpdaterRowBusy[sourceRow]' => 'repeat row clicks are rejected while busy',
    'if (!this.wpUpdaterConnected || this.hasWordPressRowBusy())' => 'disconnect is guarded unless a connection is active',
    "(disabled || !this.wpUpdaterConnected) ? ' disabled' : ''" => 'disconnect is visibly disabled while disconnected',
    'row.canUpdate = false' => 'successful update disables immediate repeat update',
    "this.getErrorStatus(error) === 409" => 'stale previews are invalidated on conflict',
] as $needle => $message) {
    $assertContains($needle, $detail, $message);
}

$assertNotContains('localStorage', $detail, 'workflow state is not placed in local storage');
$assertNotContains('sessionStorage', $detail, 'workflow state is not placed in session storage');
$assertNotContains("model.set('wpAppPassword", $detail, 'the application password is not placed on the model');
$assertNotContains('wpUpdaterPassword:', $detail, 'the transient password is not part of model attribute objects');
$assertNotContains('console.', $detail, 'the workspace does not log responses or secrets to the console');
$assertNotContains('slug:', $detail, 'row requests do not send a browser-provided slug');
$assertNotContains('postId:', $detail, 'row requests do not send a browser-provided post ID');
$assertNotContains('finalDates:', $detail, 'row requests do not send browser-provided final dates');
$assertNotContains('payload:', $detail, 'row requests do not send a browser-provided payload');

foreach ([
    "this.wpUpdaterPreview = null" => 'a new view starts without preview state',
    "this.wpUpdaterConnected = false" => 'a new view starts disconnected',
    "this.wpUpdaterPassword = ''" => 'a new view starts without a password',
    "change:wpScheduleFileId" => 'schedule changes invalidate preview state',
    'change:wpBaseUrl change:wpUsername' => 'saved connection changes invalidate connection state',
    'invalidateWordPressPreview()' => 'preview invalidation is implemented',
    'invalidateWordPressConnection()' => 'connection invalidation is implemented',
    'clearWordPressPassword()' => 'password cleanup is centralized',
    'passwordInput.addEventListener(\'input\'' => 'typed passwords remain only in transient view state',
    'autocomplete="off"' => 'credential inputs do not request browser persistence',
    'remove() {' => 'view teardown is handled',
    'return super.remove();' => 'base view teardown is preserved',
] as $needle => $message) {
    $assertContains($needle, $detail, $message);
}

foreach ([
    'wpUpdaterCourse',
    'wpUpdaterFileDates',
    'wpUpdaterExistingDates',
    'wpUpdaterFinalProgram',
    'wpUpdaterStatus',
    'wpUpdaterActions',
] as $column) {
    $assertContains("translateLabel('{$column}')", $detail, "six-column table includes {$column}");
}

foreach ([
    'target="_blank" rel="noopener noreferrer"' => 'safe permalinks isolate the new page',
    "url.protocol === 'http:' || url.protocol === 'https:'" => 'only HTTP(S) permalinks are clickable',
    'scope="col"' => 'table headers expose column scope',
    'data-role="wp-top-scroll" tabindex="0"' => 'top horizontal scroller is keyboard focusable',
    'data-role="wp-table-scroll" tabindex="0"' => 'native table scroller is keyboard focusable',
    'role="status" aria-live="polite"' => 'global state is announced accessibly',
    'role="alert"' => 'row errors are announced accessibly',
] as $needle => $message) {
    $assertContains($needle, $detail, $message);
}

foreach ([
    'composeWordPressPayload' => 'payload review rendering is removed',
    'wordpress-updater-payload' => 'payload review markup is removed',
    'wpUpdaterReviewPayload' => 'payload review label is unused',
    'JSON.stringify(row.payload' => 'payload review serialization is removed',
    '_localPayload' => 'payload-only client state is removed',
    'cloneWordPressValue' => 'payload-only cloning is removed',
] as $needle => $message) {
    $assertNotContains($needle, $detail, $message);
}

$assertNotContains('.wordpress-updater-payload', $css, 'payload review styling is removed');

$assertContains('RecordUi.synchronizeHorizontalScroll(', $detail, 'the updater delegates horizontal scroll synchronization');
$assertContains('mainScroller.scrollLeft = topScroller.scrollLeft', $recordUi, 'top scroll synchronizes to the table');
$assertContains('topScroller.scrollLeft = mainScroller.scrollLeft', $recordUi, 'table scroll synchronizes to the top control');

$updaterCssPosition = strpos($css, '.wordpress-updater-workspace');
$assert($updaterCssPosition !== false, 'the updater workspace has scoped CSS');
$updaterCss = substr($css, (int) $updaterCssPosition);

foreach ([
    '--tuvtk-primary',
    '--tuvtk-secondary',
    '--tuvtk-hover',
    '--tuvtk-surface',
    '--tuvtk-border',
    '--tuvtk-border-soft',
    '--tuvtk-text',
    '--tuvtk-muted',
    '--tuvtk-label',
] as $token) {
    $assertContains("var({$token})", $updaterCss, "updater CSS uses approved theme token {$token}");
}

$assertNotContains('#', $updaterCss, 'updater CSS contains no literal hex colors');
$assert(!preg_match('/\brgba?\s*\(/i', $updaterCss), 'updater CSS contains no literal RGB colors');
preg_match_all('/border-radius\s*:\s*([^;]+);/i', $updaterCss, $radiusMatches);
$assert(
    $radiusMatches[1] !== [] && array_unique(array_map('trim', $radiusMatches[1])) === ['3px'],
    'updater controls retain the 3px radius'
);
$assertContains('box-shadow: none;', $updaterCss, 'updater controls explicitly suppress decorative shadows');
$assertContains('@media (max-width: 767px)', $updaterCss, 'updater controls have narrow-screen behavior');
$assertContains('min-width: 1120px;', $updaterCss, 'the six-column table remains horizontally usable');

$translations = [];

foreach (['en_US', 'ro_RO'] as $locale) {
    $path = $localeRoot . '/' . $locale . '/GeneratorPerioadeCursuriWordPressUpdater.json';
    $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $translations[$locale] = $data;

    foreach ([
        'wpUpdaterBuildPreview',
        'wpUpdaterConnection',
        'wpUpdaterFetchDates',
        'wpUpdaterUpdateRow',
    ] as $key) {
        $assert(isset($data['labels'][$key]) && $data['labels'][$key] !== '', "{$locale} defines label {$key}");
    }

    $assert(!isset($data['labels']['wpUpdaterReviewPayload']), "{$locale} removes the unused payload review label");

    foreach ([
        'wpUpdaterVpnWarning',
        'wpUpdaterPreviewReady',
        'wpUpdaterCredentialsRequired',
        'wpUpdaterConnectedAs',
        'wpUpdaterDisconnected',
        'wpUpdaterRowUpdated',
        'wpUpdaterRowUnchanged',
    ] as $key) {
        $assert(isset($data['messages'][$key]) && $data['messages'][$key] !== '', "{$locale} defines message {$key}");
    }
}

$assert(
    array_keys($translations['en_US']['labels']) === array_keys($translations['ro_RO']['labels']),
    'English and Romanian updater label keys stay aligned'
);
$assert(
    array_keys($translations['en_US']['messages']) === array_keys($translations['ro_RO']['messages']),
    'English and Romanian updater message keys stay aligned'
);

echo "Phase 5 WordPress updater detail workspace: {$checks} checks passed; no browser or network used.\n";
