<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Migration;

interface LayoutMigration
{
    public function fromVersion(): int;

    public function toVersion(): int;

    /**
     * @param array<string, mixed> $layout
     * @return array<string, mixed>
     */
    public function migrate(array $layout): array;
}
