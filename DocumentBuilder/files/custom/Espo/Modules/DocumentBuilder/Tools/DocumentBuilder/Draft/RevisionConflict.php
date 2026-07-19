<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft;

use RuntimeException;

final class RevisionConflict extends RuntimeException
{
    public function __construct(public readonly int $expectedRevision, public readonly int $actualRevision)
    {
        parent::__construct('The draft revision is stale.');
    }
}
