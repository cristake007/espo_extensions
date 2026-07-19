<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\PermissionDenied;
use InvalidArgumentException;

final class EntityMetadataTreeService
{
    private const SINGLE_LINK_TYPE_LIST = ['belongsTo', 'belongsToParent', 'hasOne'];
    private const COLLECTION_LINK_TYPE_LIST = ['hasChildren', 'hasMany', 'hasManyRight', 'manyMany'];

    /** @var array<string, EntityMetadataTree> Request-scoped and ACL-sensitive. */
    private array $cache = [];

    public function __construct(
        private EntityCatalogueService $catalogue,
        private EntityCatalogueMetadata $metadata,
        private EntityCatalogueAccess $access,
        private EntityLabelProvider $labels,
        private RelationshipDepthLimit $depthLimit,
        private EntityFieldPolicy $fieldPolicy,
    ) {}

    /** @param list<string> $path */
    public function get(string $rootEntityType, array $path = []): EntityMetadataTree
    {
        $this->assertIdentifier($rootEntityType);
        $maximumDepth = $this->depthLimit->get();

        if ($maximumDepth < 1 || $maximumDepth > 3 || count($path) > $maximumDepth) {
            throw new InvalidArgumentException('The relationship path depth is invalid.');
        }

        foreach ($path as $link) {
            $this->assertIdentifier($link);
        }

        $cacheKey = $rootEntityType . '/' . implode('/', $path);

        if (isset($this->cache[$cacheKey])) {
            $this->access->requireCatalogueAccess();

            return $this->cache[$cacheKey];
        }

        $eligible = [];

        foreach ($this->catalogue->get() as $item) {
            $eligible[$item->entityType] = true;
        }

        if (!isset($eligible[$rootEntityType])) {
            throw new PermissionDenied();
        }

        $entityType = $rootEntityType;
        $visited = [$rootEntityType => true];

        foreach ($path as $link) {
            $relationship = $this->relationshipDefinition($entityType, $link, $eligible);
            $target = $relationship['entity'];

            if (isset($visited[$target])) {
                throw new PermissionDenied();
            }

            $visited[$target] = true;
            $entityType = $target;
        }

        $definition = $this->metadata->entityDefinition($entityType);
        $fields = $this->fields($entityType, $definition);
        $relationships = $this->relationships(
            $entityType,
            $definition,
            $eligible,
            $visited,
            count($path) >= $maximumDepth,
        );

        return $this->cache[$cacheKey] = new EntityMetadataTree(
            $rootEntityType,
            $entityType,
            $path,
            $fields,
            $relationships,
        );
    }

    /** @param array<string, mixed> $definition @return list<EntityFieldItem> */
    private function fields(string $entityType, array $definition): array
    {
        $items = [];
        $fields = $definition['fields'] ?? [];

        if (!is_array($fields)) {
            return [];
        }

        foreach ($fields as $name => $field) {
            if (
                !is_string($name) ||
                !is_array($field) ||
                !$this->fieldPolicy->allows($name, $field) ||
                !$this->access->canReadField($entityType, $name)
            ) {
                continue;
            }

            $items[] = new EntityFieldItem(
                $name,
                $this->safeLabel($this->labels->fieldLabel($entityType, $name), $name),
                $field['type'],
                true,
                ($field['calculated'] ?? false) === true ||
                    ($field['isCalculated'] ?? false) === true ||
                    ($field['notStorable'] ?? false) === true,
                ($field['required'] ?? false) === true,
                ($field['readOnly'] ?? false) === true ||
                    ($field['readOnlyAfterCreate'] ?? false) === true,
                ($field['custom'] ?? false) === true || ($field['isCustom'] ?? false) === true,
            );
        }

        $this->sortItems($items);

        return $items;
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, true> $eligible
     * @param array<string, true> $visited
     * @return list<EntityRelationshipItem>
     */
    private function relationships(
        string $entityType,
        array $definition,
        array $eligible,
        array $visited,
        bool $depthLimited,
    ): array {
        $items = [];
        $links = $definition['links'] ?? [];
        $fields = is_array($definition['fields'] ?? null) ? $definition['fields'] : [];

        if (!is_array($links)) {
            return [];
        }

        foreach ($links as $name => $link) {
            if (!is_string($name) || !is_array($link)) {
                continue;
            }

            $type = $link['type'] ?? null;
            $target = $link['entity'] ?? null;
            $single = is_string($type) && in_array($type, self::SINGLE_LINK_TYPE_LIST, true);
            $collection = is_string($type) && in_array($type, self::COLLECTION_LINK_TYPE_LIST, true);

            if (
                preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,99}\z/D', $name) !== 1 ||
                (!$single && !$collection) ||
                !is_string($target) ||
                !isset($eligible[$target]) ||
                !$this->access->canReadLink($entityType, $name)
            ) {
                continue;
            }

            $circular = isset($visited[$target]);
            $field = is_array($fields[$name] ?? null) ? $fields[$name] : [];
            $items[] = new EntityRelationshipItem(
                $name,
                $this->safeLabel($this->labels->linkLabel($entityType, $name), $name),
                $type,
                $target,
                $single,
                $collection,
                ($link['custom'] ?? false) === true ||
                    ($link['isCustom'] ?? false) === true ||
                    ($field['custom'] ?? false) === true,
                !$circular && !$depthLimited,
                $circular,
                $depthLimited,
            );
        }

        $this->sortItems($items);

        return $items;
    }

    /** @param array<string, true> $eligible @return array{type: string, entity: string} */
    private function relationshipDefinition(string $entityType, string $link, array $eligible): array
    {
        $definition = $this->metadata->entityDefinition($entityType);
        $relationship = is_array($definition['links'][$link] ?? null) ? $definition['links'][$link] : [];
        $type = $relationship['type'] ?? null;
        $target = $relationship['entity'] ?? null;

        if (
            (!is_string($type) || !in_array($type, [
                ...self::SINGLE_LINK_TYPE_LIST,
                ...self::COLLECTION_LINK_TYPE_LIST,
            ], true)) ||
            !is_string($target) ||
            !isset($eligible[$target]) ||
            !$this->access->canReadLink($entityType, $link)
        ) {
            throw new PermissionDenied();
        }

        return ['type' => $type, 'entity' => $target];
    }

    private function assertIdentifier(string $value): void
    {
        if (preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,99}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('A metadata-tree identifier is invalid.');
        }
    }

    private function safeLabel(string $label, string $fallback): string
    {
        $label = trim($label);

        return $label === '' || strlen($label) > 200 || preg_match('/[\x00-\x1F\x7F]/', $label) === 1 ?
            $fallback : $label;
    }

    /** @param array<EntityFieldItem|EntityRelationshipItem> $items */
    private function sortItems(array &$items): void
    {
        usort($items, static function (object $left, object $right): int {
            $labelOrder = strnatcasecmp($left->label, $right->label);

            return $labelOrder !== 0 ? $labelOrder : strcmp($left->name, $right->name);
        });
    }
}
