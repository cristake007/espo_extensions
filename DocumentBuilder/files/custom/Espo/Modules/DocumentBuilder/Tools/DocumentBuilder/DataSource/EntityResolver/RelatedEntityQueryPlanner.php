<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityCatalogueMetadata;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityFieldPolicy;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntitySourcePolicy;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\RelationshipDepthLimit;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableIdentity;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueType;
use InvalidArgumentException;

final readonly class RelatedEntityQueryPlanner
{
    private const SINGLE_LINK_TYPES = ['belongsTo', 'belongsToParent', 'hasOne'];

    public function __construct(
        private EntityCatalogueMetadata $metadata,
        private EntityFieldPolicy $fieldPolicy,
        private EntitySourcePolicy $sourcePolicy,
        private RelationshipDepthLimit $depthLimit,
        private EntityResolutionAccess $access,
    ) {}

    /** @param list<VariableIdentity> $identities @return list<RelatedPathPlan> */
    public function plan(string $rootEntityType, array $identities): array
    {
        $maximumDepth = min(2, $this->depthLimit->get());

        if ($maximumDepth < 1) {
            throw new InvalidArgumentException('The relationship depth configuration is invalid.');
        }

        $plans = [];

        foreach ($identities as $identity) {
            $segments = $identity->path->segments();
            $linkNames = array_slice($segments, 0, -1);

            if ($linkNames === [] || count($linkNames) > $maximumDepth) {
                throw new InvalidArgumentException('A related variable exceeds the relationship depth limit.');
            }

            $entityType = $rootEntityType;
            $visited = [$rootEntityType => true];
            $steps = [];

            foreach ($linkNames as $link) {
                $definition = $this->metadata->entityDefinition($entityType);
                $linkDefinition = is_array($definition['links'][$link] ?? null) ? $definition['links'][$link] : [];
                $type = $linkDefinition['type'] ?? null;
                $target = $linkDefinition['entity'] ?? null;

                if (!is_string($type) || !in_array($type, self::SINGLE_LINK_TYPES, true) ||
                    !is_string($target) || !$this->metadata->hasEntityDefinition($target)) {
                    throw new InvalidArgumentException('A related variable does not reference an approved single link.');
                }

                if (isset($visited[$target])) {
                    throw new InvalidArgumentException('A related variable contains a relationship cycle.');
                }

                $readable = $this->sourcePolicy->allows($target) &&
                    $this->access->canReadScope($target) &&
                    $this->access->canReadLink($entityType, $link);
                $steps[] = new RelatedLinkStep($entityType, $link, $target, $readable);
                $visited[$target] = true;
                $entityType = $target;
            }

            $field = $segments[array_key_last($segments)];
            $entityDefinition = $this->metadata->entityDefinition($entityType);
            $fieldDefinition = is_array($entityDefinition['fields'][$field] ?? null) ?
                $entityDefinition['fields'][$field] : null;

            if ($fieldDefinition === null || !$this->fieldPolicy->allows($field, $fieldDefinition)) {
                throw new InvalidArgumentException('A related variable field is unavailable.');
            }

            $currencyField = ($fieldDefinition['type'] ?? null) === 'currency' ?
                (is_string($fieldDefinition['currencyField'] ?? null) ?
                    $fieldDefinition['currencyField'] : $field . 'Currency') : null;

            if ($currencyField !== null && preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,99}\z/D', $currencyField) !== 1) {
                throw new InvalidArgumentException('A related currency field definition is invalid.');
            }

            $plans[] = new RelatedPathPlan($steps, new DirectFieldResolution(
                $identity,
                $field,
                $this->valueType((string) $fieldDefinition['type']),
                $this->access->canReadField($entityType, $field),
                $currencyField,
            ));
        }

        return $plans;
    }

    private function valueType(string $fieldType): VariableValueType
    {
        return match ($fieldType) {
            'date' => VariableValueType::Date,
            'datetime' => VariableValueType::DateTime,
            'bool' => VariableValueType::Boolean,
            'currency' => VariableValueType::Currency,
            'enum' => VariableValueType::Enum,
            'array', 'multiEnum' => VariableValueType::MultiValue,
            'duration', 'float', 'int', 'number', 'percent' => VariableValueType::Number,
            default => VariableValueType::Text,
        };
    }
}
