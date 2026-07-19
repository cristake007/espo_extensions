<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Source;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Capability;

interface SourceDescriptor
{
    public function type(): SourceType;

    public function requiredCapability(): ?Capability;

    /** @return array<string, mixed> */
    public function toArray(): array;
}
