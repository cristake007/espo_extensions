<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Source;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Capability;

final readonly class NoSourceDescriptor implements SourceDescriptor
{
    public function type(): SourceType
    {
        return SourceType::None;
    }

    public function requiredCapability(): ?Capability
    {
        return null;
    }

    /** @return array{type: string} */
    public function toArray(): array
    {
        return ['type' => $this->type()->value];
    }
}
