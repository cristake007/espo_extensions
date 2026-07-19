<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle;

final readonly class TemplateLifecycleResult
{
    public function __construct(
        public string $action,
        public string $templateId,
        public string $status,
        public int $revision,
        public ?string $sourceVersionId = null,
    ) {}

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'templateId' => $this->templateId,
            'status' => $this->status,
            'revision' => $this->revision,
            'sourceVersionId' => $this->sourceVersionId,
        ];
    }
}
