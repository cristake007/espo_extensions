<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\GenerationHistory\DocumentHistoryPolicy;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\GenerationHistory\DocumentStatus;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\GenerationHistory\InvalidDocumentMutation;

require dirname(__DIR__) . '/bootstrap.php';
$root = dirname(__DIR__, 2) . '/files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/GenerationHistory';
foreach (['DocumentStatus.php', 'InvalidDocumentMutation.php', 'DocumentHistoryPolicy.php'] as $file) require "$root/$file";

$policy = new DocumentHistoryPolicy();
Assert::isTrue($policy->canTransition(DocumentStatus::Pending, DocumentStatus::Generating), 'Pending cannot start generation.');
Assert::isTrue($policy->canTransition(DocumentStatus::Pending, DocumentStatus::Failed), 'Pending cannot record setup failure.');
Assert::isTrue($policy->canTransition(DocumentStatus::Generating, DocumentStatus::Completed), 'Generating cannot complete.');
Assert::isTrue($policy->canTransition(DocumentStatus::Generating, DocumentStatus::CompletedWithWarnings), 'Warning completion is unavailable.');
Assert::isTrue($policy->canTransition(DocumentStatus::Generating, DocumentStatus::Cancelled), 'Generating cannot be cancelled.');
Assert::isFalse($policy->canTransition(DocumentStatus::Pending, DocumentStatus::Completed), 'Pending skipped the generating boundary.');
Assert::isFalse($policy->canTransition(DocumentStatus::Completed, DocumentStatus::Generating), 'Successful history was reopened.');
Assert::isFalse($policy->canTransition(DocumentStatus::Failed, DocumentStatus::Pending), 'Failed history was reused for retry.');

$policy->assertUpdate(DocumentStatus::Generating, DocumentStatus::Completed, [
    'pdfAttachmentId', 'dataSnapshot', 'completedAt',
]);
$policy->assertUpdate(DocumentStatus::Completed, DocumentStatus::Completed, ['description', 'assignedUserId', 'teamsIds']);
Assert::throws(
    fn () => $policy->assertUpdate(DocumentStatus::Completed, DocumentStatus::Completed, ['pdfAttachmentId']),
    InvalidDocumentMutation::class,
    'A successful PDF attachment was mutable.',
);
Assert::throws(
    fn () => $policy->assertUpdate(DocumentStatus::CompletedWithWarnings, DocumentStatus::CompletedWithWarnings, ['sourceRecordId']),
    InvalidDocumentMutation::class,
    'Successful source provenance was mutable.',
);
Assert::throws(
    fn () => $policy->assertUpdate(DocumentStatus::Cancelled, DocumentStatus::Generating, []),
    InvalidDocumentMutation::class,
    'A terminal status was reopened.',
);
Assert::same(DocumentStatus::CompletedWithWarnings, DocumentStatus::fromStored('Completed with Warnings'), 'Stored status mapping changed.');
Assert::throws(fn () => DocumentStatus::fromStored('Complete'), InvalidArgumentException::class, 'Unknown status was accepted.');

echo "Phase 36 status transition and successful-history immutability tests passed.\n";
