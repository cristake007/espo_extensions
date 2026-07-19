<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication;

final readonly class PublicationRequest
{
    public function __construct(
        public int $expectedRevision,
        public ?string $changeNote = null,
    ) {}
}
