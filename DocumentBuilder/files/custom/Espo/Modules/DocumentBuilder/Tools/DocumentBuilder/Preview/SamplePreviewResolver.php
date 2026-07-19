<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\DirectEntityQueryPlanner;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\DirectVariableCollector;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\RelatedEntityQueryPlanner;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\RelatedVariableCollector;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValue;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueState;
use InvalidArgumentException;

final readonly class SamplePreviewResolver
{
    public function __construct(
        private DirectVariableCollector $directCollector,
        private DirectEntityQueryPlanner $directPlanner,
        private RelatedVariableCollector $relatedCollector,
        private RelatedEntityQueryPlanner $relatedPlanner,
        private TypeAwareSampleGenerator $samples,
    ) {}

    /** @param array<string, mixed> $layout @return list<PreviewValue> */
    public function resolve(array $layout): array
    {
        $source = $layout['dataSource'] ?? null;

        if (!is_array($source) || array_is_list($source)) {
            throw new InvalidArgumentException('Sample preview requires an entity source.');
        }

        $entityType = $source['entityType'] ?? null;

        if (($source['type'] ?? null) !== 'entity' || !is_string($entityType)) {
            throw new InvalidArgumentException('Sample preview requires an entity source.');
        }

        $values = [];
        $direct = $this->directPlanner->plan($entityType, $this->directCollector->collect($layout, $entityType));

        foreach ($direct->fields as $field) {
            $values[] = new PreviewValue(
                $field->identity,
                $field->readable ? $this->samples->generate($field->valueType) :
                    new VariableValue($field->valueType, VariableValueState::Forbidden),
                PreviewValueOrigin::Sample,
            );
        }

        $related = $this->relatedPlanner->plan(
            $entityType,
            $this->relatedCollector->collect($layout, $entityType),
        );

        foreach ($related as $path) {
            $readable = $path->field->readable;

            foreach ($path->links as $link) {
                $readable = $readable && $link->readable;
            }

            $values[] = new PreviewValue(
                $path->field->identity,
                $readable ? $this->samples->generate($path->field->valueType) :
                    new VariableValue($path->field->valueType, VariableValueState::Forbidden),
                PreviewValueOrigin::Sample,
            );
        }

        return $values;
    }
}
