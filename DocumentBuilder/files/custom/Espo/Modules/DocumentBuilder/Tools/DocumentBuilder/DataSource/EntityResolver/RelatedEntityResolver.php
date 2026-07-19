<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValue;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueState;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\PermissionDenied;
use Espo\ORM\Entity;
use InvalidArgumentException;

final readonly class RelatedEntityResolver implements EntityResolver
{
    private const MAX_RELATION_QUERIES = 50;

    public function __construct(
        private RelatedVariableCollector $collector,
        private RelatedEntityQueryPlanner $planner,
        private EntityRecordReader $records,
        private RelatedRecordReader $relations,
        private EntityResolutionAccess $access,
        private EntityValueMapper $mapper,
    ) {}

    public function resolve(array $layout, string $recordId): EntityResolutionResult
    {
        $source = $layout['dataSource'] ?? null;

        if (!is_array($source) || array_is_list($source)) {
            throw new InvalidArgumentException('The related entity resolution request is invalid.');
        }

        $entityType = $source['entityType'] ?? null;

        if (($source['type'] ?? null) !== 'entity' || !is_string($entityType) ||
            preg_match('/\A[A-Za-z][A-Za-z0-9]{0,99}\z/D', $entityType) !== 1 ||
            preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,63}\z/D', $recordId) !== 1) {
            throw new InvalidArgumentException('The related entity resolution request is invalid.');
        }

        if (!$this->access->canReadScope($entityType)) {
            throw new PermissionDenied();
        }

        $plans = $this->planner->plan($entityType, $this->collector->collect($layout, $entityType));

        if ($plans === []) {
            return new EntityResolutionResult([]);
        }

        $root = $this->records->find($entityType, $recordId, ['id']);

        if ($root === null) {
            return new EntityResolutionResult($this->markers($plans, $entityType, $recordId, VariableValueState::Missing));
        }

        if ($root->getEntityType() !== $entityType || !$this->access->canReadRecord($root)) {
            return new EntityResolutionResult($this->markers($plans, $entityType, $recordId, VariableValueState::Forbidden));
        }

        $queryCount = 0;

        return new EntityResolutionResult($this->resolveLevel($root, $plans, 0, $entityType, $recordId, $queryCount));
    }

    /** @param list<RelatedPathPlan> $plans @return list<ResolvedEntityValue> */
    private function resolveLevel(
        Entity $source,
        array $plans,
        int $depth,
        string $rootEntityType,
        string $recordId,
        int &$queryCount,
    ): array {
        $results = [];
        $groups = [];

        foreach ($plans as $plan) {
            $groups[$plan->links[$depth]->link][] = $plan;
        }

        foreach ($groups as $group) {
            $step = $group[0]->links[$depth];
            $allowed = [];

            foreach ($group as $plan) {
                if (!$step->readable || !$plan->field->readable) {
                    $results[] = $this->marker($plan, $rootEntityType, $recordId, VariableValueState::Forbidden);
                } else {
                    $allowed[] = $plan;
                }
            }

            if ($allowed === []) {
                continue;
            }

            $select = ['id' => true];

            foreach ($allowed as $plan) {
                if (count($plan->links) === $depth + 1) {
                    $select[$plan->field->field] = true;

                    if ($plan->field->currencyField !== null) {
                        $select[$plan->field->currencyField] = true;
                    }
                }
            }

            if (++$queryCount > self::MAX_RELATION_QUERIES) {
                throw new InvalidArgumentException('The related query plan exceeds its limit.');
            }

            $related = $this->relations->find($source, $step->link, array_keys($select));

            if ($related === null) {
                array_push($results, ...$this->markers($allowed, $rootEntityType, $recordId, VariableValueState::Missing));

                continue;
            }

            if ($related->getEntityType() !== $step->targetEntityType || !$this->access->canReadRecord($related)) {
                array_push($results, ...$this->markers($allowed, $rootEntityType, $recordId, VariableValueState::Forbidden));

                continue;
            }

            $deeper = [];

            foreach ($allowed as $plan) {
                if (count($plan->links) === $depth + 1) {
                    $results[] = new ResolvedEntityValue(
                        $plan->field->identity,
                        $this->mapper->map($related, $plan->field),
                        new SourceProvenance($rootEntityType, $recordId, implode('.', $plan->field->identity->path->segments())),
                    );
                } else {
                    $deeper[] = $plan;
                }
            }

            if ($deeper !== []) {
                array_push($results, ...$this->resolveLevel(
                    $related,
                    $deeper,
                    $depth + 1,
                    $rootEntityType,
                    $recordId,
                    $queryCount,
                ));
            }
        }

        return $results;
    }

    /** @param list<RelatedPathPlan> $plans @return list<ResolvedEntityValue> */
    private function markers(array $plans, string $entityType, string $recordId, VariableValueState $state): array
    {
        return array_map(
            fn (RelatedPathPlan $plan): ResolvedEntityValue => $this->marker($plan, $entityType, $recordId, $state),
            $plans,
        );
    }

    private function marker(
        RelatedPathPlan $plan,
        string $entityType,
        string $recordId,
        VariableValueState $state,
    ): ResolvedEntityValue {
        return new ResolvedEntityValue(
            $plan->field->identity,
            new VariableValue($plan->field->valueType, $state),
            new SourceProvenance($entityType, $recordId, implode('.', $plan->field->identity->path->segments())),
        );
    }
}
