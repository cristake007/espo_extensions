<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\PermissionDenied;

final class EntityCatalogueService implements EntitySourceEligibility
{
    /** @var ?list<EntityCatalogueItem> Request-scoped; never shared across ACL contexts. */
    private ?array $cache = null;

    public function __construct(
        private EntityCatalogueMetadata $metadata,
        private EntityCatalogueAccess $access,
        private EntitySourcePolicy $policy,
        private EntityLabelProvider $labels,
    ) {}

    /** @return list<EntityCatalogueItem> */
    public function get(): array
    {
        $this->access->requireCatalogueAccess();

        if ($this->cache !== null) {
            return $this->cache;
        }

        $items = [];

        foreach ($this->metadata->scopeDefinitions() as $entityType => $definition) {
            if (
                !is_string($entityType) ||
                ($definition['entity'] ?? false) !== true ||
                ($definition['object'] ?? false) !== true ||
                ($definition['disabled'] ?? false) === true ||
                ($definition['acl'] ?? true) !== true ||
                !$this->policy->allows($entityType) ||
                !$this->metadata->hasEntityDefinition($entityType) ||
                !$this->access->canRead($entityType)
            ) {
                continue;
            }

            $items[] = new EntityCatalogueItem(
                $entityType,
                $this->safeLabel($entityType),
                $this->metadata->isCustom($entityType),
            );
        }

        usort($items, static function (EntityCatalogueItem $left, EntityCatalogueItem $right): int {
            $labelOrder = strnatcasecmp($left->label, $right->label);

            return $labelOrder !== 0 ? $labelOrder : strcmp($left->entityType, $right->entityType);
        });

        return $this->cache = $items;
    }

    public function requireEligible(string $entityType): void
    {
        foreach ($this->get() as $item) {
            if ($item->entityType === $entityType) {
                return;
            }
        }

        throw new PermissionDenied();
    }

    private function safeLabel(string $entityType): string
    {
        $label = trim($this->labels->label($entityType));

        if (
            $label === '' ||
            strlen($label) > 200 ||
            preg_match('/[\x00-\x1F\x7F]/', $label) === 1
        ) {
            return $entityType;
        }

        return $label;
    }
}
