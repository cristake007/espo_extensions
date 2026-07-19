<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

final readonly class ValidationError
{
    public function __construct(
        private string $code,
        private string $path,
        private ?string $elementId = null,
    ) {}

    public function code(): string
    {
        return $this->code;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function elementId(): ?string
    {
        return $this->elementId;
    }

    /** @return array{code: string, path: string, elementId: ?string} */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'path' => $this->path,
            'elementId' => $this->elementId,
        ];
    }
}
