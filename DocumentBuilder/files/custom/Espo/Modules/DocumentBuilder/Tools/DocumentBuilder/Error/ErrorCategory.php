<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Error;

enum ErrorCategory: string
{
    case Validation = 'validation';
    case Permission = 'permission';
    case SourceRecordMissing = 'sourceRecordMissing';
    case VariableMissing = 'variableMissing';
    case RelatedRecordMissing = 'relatedRecordMissing';
    case MediaMissing = 'mediaMissing';
    case Renderer = 'renderer';
    case FileStorage = 'fileStorage';
    case BatchJob = 'batchJob';
    case RevisionConflict = 'revisionConflict';
    case SourceChangeConfirmation = 'sourceChangeConfirmation';

    public function httpStatus(): int
    {
        return match ($this) {
            self::Validation => 400,
            self::Permission => 403,
            self::SourceRecordMissing => 404,
            self::VariableMissing,
            self::RelatedRecordMissing,
            self::MediaMissing => 422,
            self::Renderer,
            self::FileStorage,
            self::BatchJob => 500,
            self::RevisionConflict,
            self::SourceChangeConfirmation => 409,
        };
    }

    public function messageKey(): string
    {
        return 'errors.' . $this->value;
    }

    public function mayRetry(): bool
    {
        return match ($this) {
            self::Renderer,
            self::FileStorage,
            self::BatchJob => true,
            default => false,
        };
    }
}
