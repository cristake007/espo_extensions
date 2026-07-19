<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Source;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Capability;
use InvalidArgumentException;

final readonly class EntitySourceDescriptor implements SourceDescriptor
{
    public function __construct(private string $entityType, private int $relationshipDepth = 2)
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9]{0,99}$/D', $entityType) !== 1) {
            throw new InvalidArgumentException('An entity source requires a safe Espo entity type.');
        }

        if ($relationshipDepth < 1 || $relationshipDepth > 3) {
            throw new InvalidArgumentException('Entity relationship depth is outside the hard boundary.');
        }
    }

    public function type(): SourceType
    {
        return SourceType::Entity;
    }

    public function requiredCapability(): Capability
    {
        return Capability::EntitySource;
    }

    /** @return array{type: string, entityType: string, relationshipDepth: int} */
    public function toArray(): array
    {
        return [
            'type' => $this->type()->value,
            'entityType' => $this->entityType,
            'relationshipDepth' => $this->relationshipDepth,
        ];
    }
}
