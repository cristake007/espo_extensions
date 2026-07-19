<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityCatalogueMetadata;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityFieldPolicy;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueType;
use InvalidArgumentException;

final readonly class DirectEntityQueryPlanner
{
    private const MAX_SELECT_FIELDS = 250;

    public function __construct(
        private EntityCatalogueMetadata $metadata,
        private EntityFieldPolicy $fieldPolicy,
        private EntityResolutionAccess $access,
    ) {}

    /** @param list<DirectVariableReference> $references */
    public function plan(string $entityType, array $references): DirectEntityQueryPlan
    {
        $entityDefinition = $this->metadata->entityDefinition($entityType);
        $definitions = $entityDefinition['fields'] ?? null;

        if (!$this->metadata->hasEntityDefinition($entityType) || !is_array($definitions)) {
            throw new InvalidArgumentException('The entity source definition is unavailable.');
        }

        $select = ['id' => true];
        $planned = [];

        foreach ($references as $reference) {
            $definition = $definitions[$reference->field] ?? null;

            if (!is_array($definition) || !$this->fieldPolicy->allows($reference->field, $definition)) {
                throw new InvalidArgumentException('A direct variable field is unavailable.');
            }

            $readable = $this->access->canReadField($entityType, $reference->field);
            $currencyField = null;

            if (($definition['type'] ?? null) === 'currency') {
                $configured = $definition['currencyField'] ?? null;
                $currencyField = is_string($configured) ? $configured : $reference->field . 'Currency';

                if (preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,99}\z/D', $currencyField) !== 1) {
                    throw new InvalidArgumentException('A currency field definition is invalid.');
                }
            }

            if ($readable) {
                $select[$reference->field] = true;

                if ($currencyField !== null) {
                    $select[$currencyField] = true;
                }
            }

            $planned[] = new DirectFieldResolution(
                $reference->identity,
                $reference->field,
                $this->valueType((string) $definition['type']),
                $readable,
                $currencyField,
            );
        }

        if (count($select) > self::MAX_SELECT_FIELDS) {
            throw new InvalidArgumentException('The entity select plan exceeds its limit.');
        }

        return new DirectEntityQueryPlan(array_keys($select), $planned);
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
