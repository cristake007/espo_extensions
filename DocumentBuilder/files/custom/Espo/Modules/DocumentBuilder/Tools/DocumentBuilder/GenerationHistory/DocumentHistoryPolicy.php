<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\GenerationHistory;

final class DocumentHistoryPolicy
{
    /** @var list<string> */
    public const IMMUTABLE_AFTER_SUCCESS = [
        'name',
        'status',
        'templateId',
        'templateVersionId',
        'sourceType',
        'sourceRecordType',
        'sourceRecordId',
        'sourceRecordName',
        'sourceDisplayName',
        'spreadsheetImportId',
        'spreadsheetRowNumber',
        'batchId',
        'outputFilename',
        'pdfAttachmentId',
        'dataSnapshot',
        'templateSnapshot',
        'warningSummary',
        'errorSummary',
        'generatedById',
        'createdAt',
        'completedAt',
    ];

    /** @param list<string> $changedFields */
    public function assertUpdate(
        DocumentStatus $before,
        DocumentStatus $after,
        array $changedFields,
    ): void {
        if (!$this->canTransition($before, $after)) {
            throw new InvalidDocumentMutation("Generated-document transition {$before->value} -> {$after->value} is invalid.");
        }

        if (!$before->isSuccessful()) {
            return;
        }

        $protectedChanges = array_intersect(self::IMMUTABLE_AFTER_SUCCESS, $changedFields);

        if ($protectedChanges !== []) {
            throw new InvalidDocumentMutation('Successful generated-document provenance is immutable.');
        }
    }

    public function canTransition(DocumentStatus $before, DocumentStatus $after): bool
    {
        if ($before === $after) {
            return true;
        }

        if ($before->isTerminal()) {
            return false;
        }

        return match ($before) {
            DocumentStatus::Pending => in_array($after, [
                DocumentStatus::Generating,
                DocumentStatus::Failed,
                DocumentStatus::Cancelled,
            ], true),
            DocumentStatus::Generating => in_array($after, [
                DocumentStatus::Completed,
                DocumentStatus::CompletedWithWarnings,
                DocumentStatus::Failed,
                DocumentStatus::Cancelled,
            ], true),
            default => false,
        };
    }
}
