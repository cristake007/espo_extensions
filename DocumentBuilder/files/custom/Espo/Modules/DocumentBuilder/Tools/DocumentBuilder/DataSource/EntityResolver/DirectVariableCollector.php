<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableIdentity;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableSource;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableType;
use InvalidArgumentException;

final class DirectVariableCollector
{
    private const MAX_REFERENCES = 500;

    /**
     * @param array<string, mixed> $layout
     * @return list<DirectVariableReference>
     */
    public function collect(array $layout, string $entityType): array
    {
        $references = [];
        $seen = [];
        $this->walk($layout, $entityType, $references, $seen);

        return $references;
    }

    /**
     * @param list<DirectVariableReference> $references
     * @param array<string, true> $seen
     */
    private function walk(mixed $value, string $entityType, array &$references, array &$seen): void
    {
        if (!is_array($value)) {
            return;
        }

        if (($value['type'] ?? null) === 'variable') {
            $rawIdentity = $value['identity'] ?? null;

            if (!is_array($rawIdentity) || array_is_list($rawIdentity)) {
                throw new InvalidArgumentException('A stored variable identity is invalid.');
            }

            $identity = VariableIdentity::fromArray($rawIdentity);

            if ($identity->source === VariableSource::Entity && $identity->entityType !== $entityType) {
                throw new InvalidArgumentException('An entity variable is incompatible with the source record.');
            }

            if ($identity->source === VariableSource::Entity && $identity->type === VariableType::Direct) {
                $key = json_encode(
                    $identity->toArray(),
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                );

                if (!isset($seen[$key])) {
                    if (count($references) >= self::MAX_REFERENCES) {
                        throw new InvalidArgumentException('The direct variable plan exceeds its limit.');
                    }

                    $seen[$key] = true;
                    $references[] = new DirectVariableReference($identity, $identity->path->segments()[0]);
                }
            }
        }

        foreach ($value as $child) {
            $this->walk($child, $entityType, $references, $seen);
        }
    }
}
