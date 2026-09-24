<?php

declare(strict_types=1);

namespace Espo\Modules\ContentFactory\Tools\ContentFactory\Generation;

final readonly class GenerationRequest
{
    public function __construct(
        public string $brief,
        public string $platform,
        public string $format,
        public string $style,
        public string $customInstructions,
        public string $locale,
        public string $contentType,
        /** @var array{id: string, name: string, slug: ?string, url: ?string}|null */
        public ?array $course = null,
    ) {}

    /** @return array<string, mixed> */
    public function toN8nPayload(string $requestId): array
    {
        return [
            'schemaVersion' => 1,
            'requestId' => $requestId,
            'contentType' => $this->contentType,
            'brief' => $this->brief,
            'platform' => $this->platform,
            'format' => $this->format,
            'style' => $this->style,
            'customInstructions' => $this->customInstructions,
            'locale' => $this->locale,
            'course' => $this->course,
        ];
    }
}
