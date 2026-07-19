<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

final readonly class ProcessedLayout
{
    /** @param array<string, mixed> $layout */
    public function __construct(private array $layout, private string $canonicalJson)
    {}

    /** @return array<string, mixed> */
    public function layout(): array
    {
        return $this->layout;
    }

    public function canonicalJson(): string
    {
        return $this->canonicalJson;
    }

    public function checksum(): string
    {
        return hash('sha256', $this->canonicalJson);
    }
}
