<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft;

/** Phase 12's canonical schema contains no variable-bearing nodes yet. */
final class NoopSourceReferenceImpactAnalyzer implements SourceReferenceImpactAnalyzer
{
    public function analyze(array $currentLayout, array $nextLayout): array
    {
        return [];
    }
}
