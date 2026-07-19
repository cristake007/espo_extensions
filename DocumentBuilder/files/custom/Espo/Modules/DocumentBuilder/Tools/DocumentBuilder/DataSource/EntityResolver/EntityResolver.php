<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver;

interface EntityResolver
{
    /** @param array<string, mixed> $layout */
    public function resolve(array $layout, string $recordId): EntityResolutionResult;
}
