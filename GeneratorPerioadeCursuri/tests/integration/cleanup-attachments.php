<?php

declare(strict_types=1);

use Espo\Core\Application;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Entities\Attachment;
use Espo\ORM\EntityManager;

require '/var/www/html/bootstrap.php';

$ids = array_values(array_filter(explode(',', (string) getenv('GPC_ATTACHMENT_IDS'))));
$application = new Application();
$application->setupSystemUser();
$entityManager = $application->getContainer()->getByClass(EntityManager::class);
$fileStorage = $application->getInjectableFactory()->create(FileStorageManager::class);
$removed = [];

foreach ($ids as $id) {
    /** @var ?Attachment $attachment */
    $attachment = $entityManager->getEntityById(Attachment::ENTITY_TYPE, $id);

    if (!$attachment) {
        continue;
    }

    $safeName = $attachment->getName() === 'schedule.csv' || str_starts_with((string) $attachment->getName(), 'AUTOTEST-GPC-');
    $safeRelation = $attachment->getRelatedType() === 'GeneratorPerioadeCursuriWordPressUpdater' &&
        $attachment->getTargetField() === 'wpScheduleFile';

    if (!$safeName || !$safeRelation) {
        throw new RuntimeException("Refusing to remove attachment {$id}: it is not a recognized GPC test attachment.");
    }

    if ($fileStorage->exists($attachment)) {
        $fileStorage->unlink($attachment);
    }

    $entityManager->removeEntity($attachment);
    $removed[] = $id;
}

fwrite(STDOUT, json_encode(['removedAttachmentIds' => $removed], JSON_PRETTY_PRINT) . "\n");
