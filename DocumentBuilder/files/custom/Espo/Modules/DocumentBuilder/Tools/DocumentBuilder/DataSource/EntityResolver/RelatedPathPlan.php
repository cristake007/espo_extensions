<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver;

final readonly class RelatedPathPlan
{
    /** @param list<RelatedLinkStep> $links */
    public function __construct(
        public array $links,
        public DirectFieldResolution $field,
    ) {}
}
