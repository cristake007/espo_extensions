<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft;

interface SourceReferenceImpactAnalyzer
{
    /**
     * @param array<string, mixed> $currentLayout
     * @param array<string, mixed> $nextLayout
     * @return list<UnresolvedSourceReference>
     */
    public function analyze(array $currentLayout, array $nextLayout): array;
}
