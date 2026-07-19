<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle;

use InvalidArgumentException;

final readonly class TemplateLifecycleRequest
{
    public function __construct(public int $expectedRevision)
    {
        if ($expectedRevision < 0) {
            throw new InvalidArgumentException('A non-negative template revision is required.');
        }
    }
}
