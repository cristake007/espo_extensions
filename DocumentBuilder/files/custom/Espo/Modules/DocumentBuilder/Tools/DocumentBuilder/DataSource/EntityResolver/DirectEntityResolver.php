<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValue;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueState;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\PermissionDenied;
use InvalidArgumentException;

final readonly class DirectEntityResolver implements EntityResolver
{
    public function __construct(
        private DirectVariableCollector $collector,
        private DirectEntityQueryPlanner $planner,
        private EntityRecordReader $records,
        private EntityResolutionAccess $access,
        private EntityValueMapper $mapper,
    ) {}

    public function resolve(array $layout, string $recordId): EntityResolutionResult
    {
        $source = $layout['dataSource'] ?? null;

        if (!is_array($source) || array_is_list($source)) {
            throw new InvalidArgumentException('The entity resolution request is invalid.');
        }

        $entityType = $source['entityType'] ?? null;

        if (($source['type'] ?? null) !== 'entity' || !is_string($entityType) ||
            preg_match('/\A[A-Za-z][A-Za-z0-9]{0,99}\z/D', $entityType) !== 1 ||
            preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,63}\z/D', $recordId) !== 1) {
            throw new InvalidArgumentException('The entity resolution request is invalid.');
        }

        if (!$this->access->canReadScope($entityType)) {
            throw new PermissionDenied();
        }

        $references = $this->collector->collect($layout, $entityType);
        $plan = $this->planner->plan($entityType, $references);

        if ($plan->fields === []) {
            return new EntityResolutionResult([]);
        }

        $record = $this->records->find($entityType, $recordId, $plan->selectFields);

        if ($record !== null && $record->getEntityType() !== $entityType) {
            throw new InvalidArgumentException('The source record type is invalid.');
        }

        $recordReadable = $record !== null && $this->access->canReadRecord($record);
        $values = [];

        foreach ($plan->fields as $field) {
            $state = !$field->readable || ($record !== null && !$recordReadable) ?
                VariableValueState::Forbidden : VariableValueState::Missing;
            $value = $recordReadable && $field->readable ?
                $this->mapper->map($record, $field) : new VariableValue($field->valueType, $state);
            $values[] = new ResolvedEntityValue(
                $field->identity,
                $value,
                new SourceProvenance($entityType, $recordId, $field->field),
            );
        }

        return new EntityResolutionResult($values);
    }
}
