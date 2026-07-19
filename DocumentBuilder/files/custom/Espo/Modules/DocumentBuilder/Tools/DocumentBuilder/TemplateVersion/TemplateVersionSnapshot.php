<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\TemplateVersion;

final readonly class TemplateVersionSnapshot
{
    /** @param array<string, mixed> $attributes */
    public function __construct(private array $attributes)
    {}

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function checksum(): string
    {
        return $this->attributes['checksum'];
    }
}
