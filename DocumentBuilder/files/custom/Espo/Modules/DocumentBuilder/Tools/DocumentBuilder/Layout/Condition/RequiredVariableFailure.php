<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Condition;

use RuntimeException;

final class RequiredVariableFailure extends RuntimeException
{
    /** @param list<string> $identityKeys */
    public function __construct(public readonly array $identityKeys)
    {
        parent::__construct('Required variable values are unavailable.');
    }
}
