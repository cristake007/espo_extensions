<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview;

use RuntimeException;

final class PreviewRevisionConflict extends RuntimeException
{
    public function __construct(public readonly int $expectedRevision, public readonly int $actualRevision)
    {
        parent::__construct('The preview draft revision is stale.');
    }
}
