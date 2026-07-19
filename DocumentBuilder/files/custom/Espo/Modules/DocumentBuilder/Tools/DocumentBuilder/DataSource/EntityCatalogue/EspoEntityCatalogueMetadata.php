<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue;

use Espo\Core\Utils\Metadata;

final readonly class EspoEntityCatalogueMetadata implements EntityCatalogueMetadata
{
    public function __construct(private Metadata $metadata)
    {}

    public function scopeDefinitions(): array
    {
        $definitions = $this->metadata->get(['scopes'], []);

        return is_array($definitions) ? array_filter(
            $definitions,
            static fn (mixed $definition): bool => is_array($definition),
        ) : [];
    }

    public function hasEntityDefinition(string $entityType): bool
    {
        return is_array($this->metadata->get(['entityDefs', $entityType]));
    }

    public function isCustom(string $entityType): bool
    {
        $scope = $this->metadata->get(['scopes', $entityType], []);
        $entity = $this->metadata->get(['entityDefs', $entityType], []);

        return (is_array($scope) && ($scope['isCustom'] ?? false) === true) ||
            (is_array($entity) && ($entity['isCustom'] ?? $entity['custom'] ?? false) === true);
    }

    public function entityDefinition(string $entityType): array
    {
        $definition = $this->metadata->get(['entityDefs', $entityType], []);

        return is_array($definition) ? $definition : [];
    }
}
