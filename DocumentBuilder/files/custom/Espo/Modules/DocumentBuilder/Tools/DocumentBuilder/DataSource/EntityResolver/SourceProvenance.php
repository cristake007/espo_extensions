<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver;

final readonly class SourceProvenance
{
    public function __construct(
        public string $entityType,
        public string $recordId,
        public string $field,
    ) {}

    /** @return array{source: string, entityType: string, recordId: string, field: string} */
    public function toArray(): array
    {
        return [
            'source' => 'entity',
            'entityType' => $this->entityType,
            'recordId' => $this->recordId,
            'field' => $this->field,
        ];
    }
}
