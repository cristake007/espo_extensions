<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver;

final readonly class EntityPathResolver implements EntityResolver
{
    public function __construct(
        private DirectEntityResolver $direct,
        private RelatedEntityResolver $related,
    ) {}

    public function resolve(array $layout, string $recordId): EntityResolutionResult
    {
        return new EntityResolutionResult([
            ...$this->direct->resolve($layout, $recordId)->values,
            ...$this->related->resolve($layout, $recordId)->values,
        ]);
    }
}
