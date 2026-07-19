<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft;

final readonly class DraftSaveResult
{
    /**
     * @param array<string, mixed> $layout
     * @param array<string, mixed> $source
     */
    public function __construct(
        public string $templateId,
        public int $revision,
        public array $layout,
        public array $source,
        public ?string $changeNote,
        public ?SourceChangeImpactReport $sourceChangeImpact = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'id' => $this->templateId,
            'revision' => $this->revision,
            'layout' => $this->layout,
            'source' => $this->source,
            'changeNote' => $this->changeNote,
        ];

        if ($this->sourceChangeImpact !== null) {
            $data['sourceChangeImpact'] = $this->sourceChangeImpact->toArray();
        }

        return $data;
    }
}
