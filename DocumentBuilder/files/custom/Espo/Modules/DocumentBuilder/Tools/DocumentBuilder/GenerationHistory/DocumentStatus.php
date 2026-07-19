<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\GenerationHistory;

use InvalidArgumentException;

enum DocumentStatus: string
{
    case Pending = 'Pending';
    case Generating = 'Generating';
    case Completed = 'Completed';
    case CompletedWithWarnings = 'Completed with Warnings';
    case Failed = 'Failed';
    case Cancelled = 'Cancelled';

    public static function fromStored(mixed $value): self
    {
        if (!is_string($value) || ($status = self::tryFrom($value)) === null) {
            throw new InvalidArgumentException('The generated-document status is invalid.');
        }

        return $status;
    }

    public function isSuccessful(): bool
    {
        return $this === self::Completed || $this === self::CompletedWithWarnings;
    }

    public function isTerminal(): bool
    {
        return $this->isSuccessful() || $this === self::Failed || $this === self::Cancelled;
    }
}
