<?php

declare(strict_types=1);

use Espo\Core\Application;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Attachment;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\WordPressUpdaterService;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require '/var/www/html/bootstrap.php';

$scope = 'GeneratorPerioadeCursuriWordPressUpdater';
$runTag = 'AUTOTEST-GPC-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(3));
$checks = 0;
$createdRecords = [];
$createdAttachments = [];
$removedRecords = [];
$removedAttachments = [];
$failures = [];

$assert = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;

    if (!$condition) {
        $failures[] = $message;
    }
};

$application = new Application();
$application->setupSystemUser();
$container = $application->getContainer();
$entityManager = $container->getByClass(EntityManager::class);
$metadata = $container->getByClass(Metadata::class);
$injectableFactory = $application->getInjectableFactory();
$fileStorage = $injectableFactory->create(FileStorageManager::class);

/** @var WordPressUpdaterService $service */
$service = $injectableFactory->create(WordPressUpdaterService::class);
$assert($service instanceof WordPressUpdaterService, 'Dependency injection must resolve WordPressUpdaterService.');
$assert($metadata->get(['scopes', $scope, 'entity']) === true, 'Updater entity scope metadata must be loaded.');
$assert($metadata->get(['entityDefs', $scope, 'fields', 'wpScheduleFile', 'type']) === 'file', 'Native file field metadata must be loaded.');
$assert($metadata->get(['entityDefs', $scope, 'fields', 'wpAppPassword']) === null, 'Application passwords must not have entity metadata.');

$routes = json_decode((string) file_get_contents(
    '/var/www/html/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/routes.json'
), true, 512, JSON_THROW_ON_ERROR);
$routeNames = array_column($routes, 'route');
$assert(in_array('/GeneratorPerioadeCursuriWordPressUpdater/:id/preview', $routeNames, true), 'Preview API route must be installed.');
$assert(in_array('/GeneratorPerioadeCursuriWordPressUpdater/:id/updateRow', $routeNames, true), 'Update API route metadata must be installed.');

foreach (['detail', 'edit', 'list', 'search'] as $layout) {
    $path = "/var/www/html/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/layouts/{$scope}/{$layout}.json";
    $assert(is_array(json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)), "{$layout} layout must load as JSON.");
}

foreach (['en_US', 'ro_RO'] as $locale) {
    $path = "/var/www/html/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/i18n/{$locale}/{$scope}.json";
    $translations = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $assert(isset($translations['messages']['wpUpdaterPreviewFailed']), "{$locale} error-message translations must be installed.");
}

$makeAttachment = static function (
    string $name,
    string $mimeType,
    string $contents,
    Entity $record
) use ($entityManager, $fileStorage, $scope, &$createdAttachments): Attachment {
    /** @var Attachment $attachment */
    $attachment = $entityManager->getNewEntity(Attachment::ENTITY_TYPE);
    $attachment->set([
        'name' => $name,
        'type' => $mimeType,
        'role' => Attachment::ROLE_ATTACHMENT,
        'size' => strlen($contents),
        'relatedType' => $scope,
        'relatedId' => $record->getId(),
        'field' => 'wpScheduleFile',
    ]);
    $entityManager->saveEntity($attachment);
    $fileStorage->putContents($attachment, $contents);
    $createdAttachments[$attachment->getId()] = $attachment;

    return $attachment;
};

try {
    $record = $entityManager->getNewEntity($scope);
    $record->set(['name' => $runTag, 'description' => 'Created only by the GeneratorPerioadeCursuri integration test.']);
    $entityManager->saveEntity($record);
    $createdRecords[$record->getId()] = $record;
    $assert($record->getId() !== null, 'A real updater entity must be created.');

    $csv = "Title,Permalink,January\nIntegration course,https://example.test/cursuri/integration-course/,10.01.2030\n";
    $csvAttachment = $makeAttachment("{$runTag}.csv", 'text/csv', $csv, $record);
    $record->set('wpScheduleFileId', $csvAttachment->getId());
    $entityManager->saveEntity($record);

    $reloaded = $entityManager->getEntityById($scope, $record->getId());
    $assert($reloaded !== null && $reloaded->get('name') === $runTag, 'The created updater entity must be readable.');
    $reloaded?->set('description', 'Edited by the integration test.');

    if ($reloaded) {
        $entityManager->saveEntity($reloaded);
    }

    $edited = $entityManager->getEntityById($scope, $record->getId());
    $assert($edited?->get('description') === 'Edited by the integration test.', 'The updater entity must be editable.');
    $assert($fileStorage->getContents($csvAttachment) === $csv, 'Native attachment storage must round-trip CSV bytes.');

    $preview = $service->preview((string) $record->getId(), (object) []);
    $assert(($preview['previewSourceFileId'] ?? null) === $csvAttachment->getId(), 'CSV preview must use the record attachment.');
    $assert(($preview['rows'][0]['slug'] ?? null) === 'integration-course', 'CSV preview must execute in the real EspoCRM runtime.');

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'Title');
    $sheet->setCellValue('B1', 'Permalink');
    $sheet->setCellValue('C1', 'January');
    $sheet->setCellValue('A2', 'Integration XLSX course');
    $sheet->setCellValue('B2', 'https://example.test/cursuri/integration-xlsx/');
    $sheet->setCellValue('C2', '11.01.2030');
    $xlsxPath = tempnam(sys_get_temp_dir(), 'gpc-integration-');

    if ($xlsxPath === false) {
        throw new RuntimeException('Could not create transient XLSX fixture.');
    }

    (new Xlsx($spreadsheet))->save($xlsxPath);
    $xlsx = (string) file_get_contents($xlsxPath);
    @unlink($xlsxPath);
    $spreadsheet->disconnectWorksheets();
    $xlsxAttachment = $makeAttachment("{$runTag}.xlsx", 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $xlsx, $record);
    $record->set('wpScheduleFileId', $xlsxAttachment->getId());
    $entityManager->saveEntity($record);
    $xlsxPreview = $service->preview((string) $record->getId(), (object) []);
    $assert(($xlsxPreview['rows'][0]['slug'] ?? null) === 'integration-xlsx', 'XLSX preview must execute through real PhpSpreadsheet.');

    $invalidAttachment = $makeAttachment("{$runTag}.txt", 'text/plain', 'not a supported schedule', $record);
    $record->set('wpScheduleFileId', $invalidAttachment->getId());
    $entityManager->saveEntity($record);

    try {
        $service->preview((string) $record->getId(), (object) []);
        $assert(false, 'An unsupported attachment must fail preview.');
    } catch (BadRequest $exception) {
        $assert($exception->getMessage() === 'The source file must be CSV or XLSX.', 'An unsupported attachment must return a useful HTTP 400 message.');
        $assert(!str_contains($exception->getMessage(), '/var/www'), 'Runtime errors must not expose server paths.');
    }

    $assert(!$record->has('wpAppPassword'), 'The runtime entity must not persist an application password.');
} catch (Throwable $exception) {
    $failures[] = 'Unexpected integration exception: ' . $exception::class . ': ' . $exception->getMessage();
} finally {
    foreach (array_reverse($createdAttachments, true) as $id => $attachment) {
        try {
            if ($fileStorage->exists($attachment)) {
                $fileStorage->unlink($attachment);
            }

            $entityManager->removeEntity($attachment);
            $removedAttachments[] = $id;
        } catch (Throwable $exception) {
            $failures[] = "Cleanup failed for attachment {$id}: " . $exception->getMessage();
        }
    }

    foreach (array_reverse($createdRecords, true) as $id => $record) {
        try {
            $entityManager->removeEntity($record);
            $removedRecords[] = $id;
        } catch (Throwable $exception) {
            $failures[] = "Cleanup failed for record {$id}: " . $exception->getMessage();
        }
    }
}

$result = [
    'runTag' => $runTag,
    'checks' => $checks,
    'createdRecordIds' => array_keys($createdRecords),
    'removedRecordIds' => $removedRecords,
    'createdAttachmentIds' => array_keys($createdAttachments),
    'removedAttachmentIds' => $removedAttachments,
    'failures' => $failures,
    'wordpressContacted' => false,
];

fwrite($failures === [] ? STDOUT : STDERR, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
exit($failures === [] ? 0 : 1);
