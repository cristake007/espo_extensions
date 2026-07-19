<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview;

use InvalidArgumentException;

final readonly class PreviewRequest
{
    public function __construct(
        public int $expectedRevision,
        public PreviewMode $mode,
        public ?string $recordId = null,
    ) {
        if ($expectedRevision < 0 ||
            ($mode === PreviewMode::Sample && $recordId !== null) ||
            ($mode === PreviewMode::Record && ($recordId === null ||
                preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,63}\z/D', $recordId) !== 1))) {
            throw new InvalidArgumentException('The preview request is invalid.');
        }
    }
}
