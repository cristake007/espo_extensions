<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft;

use RuntimeException;

final class SourceChangeConfirmationRequired extends RuntimeException
{
    public function __construct(public readonly SourceChangeImpactReport $impactReport)
    {
        parent::__construct('The source change requires explicit confirmation.');
    }
}
