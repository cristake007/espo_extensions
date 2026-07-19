<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableIdentity;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableSource;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableType;
use InvalidArgumentException;

final class RelatedVariableCollector
{
    private const MAX_REFERENCES = 500;

    /** @param array<string, mixed> $layout @return list<VariableIdentity> */
    public function collect(array $layout, string $entityType): array
    {
        $identities = [];
        $seen = [];
        $this->walk($layout, $entityType, $identities, $seen);

        return $identities;
    }

    /** @param list<VariableIdentity> $identities @param array<string, true> $seen */
    private function walk(mixed $value, string $entityType, array &$identities, array &$seen): void
    {
        if (!is_array($value)) {
            return;
        }

        if (($value['type'] ?? null) === 'variable') {
            $raw = $value['identity'] ?? null;

            if (!is_array($raw) || array_is_list($raw)) {
                throw new InvalidArgumentException('A stored variable identity is invalid.');
            }

            $identity = VariableIdentity::fromArray($raw);

            if ($identity->source === VariableSource::Entity && $identity->entityType !== $entityType) {
                throw new InvalidArgumentException('An entity variable is incompatible with the source record.');
            }

            if ($identity->source === VariableSource::Entity && $identity->type === VariableType::Related) {
                $key = json_encode($identity->toArray(), JSON_THROW_ON_ERROR);

                if (!isset($seen[$key])) {
                    if (count($identities) >= self::MAX_REFERENCES) {
                        throw new InvalidArgumentException('The related variable plan exceeds its limit.');
                    }

                    $seen[$key] = true;
                    $identities[] = $identity;
                }
            }
        }

        foreach ($value as $child) {
            $this->walk($child, $entityType, $identities, $seen);
        }
    }
}
