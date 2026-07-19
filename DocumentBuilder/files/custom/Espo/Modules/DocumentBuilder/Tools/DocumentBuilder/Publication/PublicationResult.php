<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication;

final readonly class PublicationResult
{
    public function __construct(
        public string $templateId,
        public string $versionId,
        public int $versionNumber,
        public string $checksum,
        public string $publishedAt,
    ) {}

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'templateId' => $this->templateId,
            'versionId' => $this->versionId,
            'versionNumber' => $this->versionNumber,
            'checksum' => $this->checksum,
            'publishedAt' => $this->publishedAt,
        ];
    }
}
