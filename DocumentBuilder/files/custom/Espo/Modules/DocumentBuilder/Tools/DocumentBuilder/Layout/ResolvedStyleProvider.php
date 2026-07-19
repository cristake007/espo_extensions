<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

interface ResolvedStyleProvider
{
    /** @param array<string, mixed> $defaults @param array<string, mixed> ...$layers @return array<string, mixed> */
    public function resolve(array $defaults, array ...$layers): array;
}
